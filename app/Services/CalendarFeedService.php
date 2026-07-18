<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Support\BusinessTime;
use Illuminate\Support\Str;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Enums\EventStatus;

class CalendarFeedService
{
    /**
     * Return the caregiver's feed token, generating and persisting one on first use.
     */
    public function ensureToken(Caregiver $caregiver): string
    {
        if (! $caregiver->calendar_feed_token) {
            $caregiver->forceFill(['calendar_feed_token' => $this->newToken()])->save();
        }

        return $caregiver->calendar_feed_token;
    }

    /**
     * Replace the caregiver's feed token, invalidating any existing subscriptions.
     */
    public function regenerateToken(Caregiver $caregiver): string
    {
        $caregiver->forceFill(['calendar_feed_token' => $this->newToken()])->save();

        return $caregiver->calendar_feed_token;
    }

    /**
     * Build the .ics body for the caregiver's upcoming confirmed jobs.
     */
    public function buildCalendar(Caregiver $caregiver): string
    {
        $bookings = $caregiver->bookings()
            ->where('status', BookingStatus::Confirmed->value)
            ->where('end_datetime', '>', now())
            ->with(['client', 'hotel', 'address'])
            ->orderBy('start_datetime')
            ->get();

        $calendar = Calendar::create('Sitterwise Jobs')
            ->productIdentifier('-//Sitterwise//Calendar//EN')
            ->refreshInterval(15);

        foreach ($bookings as $booking) {
            $calendar->event($this->buildEvent($booking));
        }

        return $calendar->get();
    }

    private function buildEvent(Booking $booking): Event
    {
        // Stored datetimes are UTC; convert to the business timezone so the iCal
        // library emits the correct TZID=America/Los_Angeles (+ VTIMEZONE).
        $start = $booking->start_datetime->copy()->setTimezone(BusinessTime::TZ);
        $end = $booking->end_datetime->copy()->setTimezone(BusinessTime::TZ);

        $clientName = trim(($booking->client?->first_name ?? '').' '.($booking->client?->last_name ?? ''));
        $clientName = $clientName !== '' ? $clientName : 'Client';
        $service = $booking->service_type_label ?? $booking->service_type;

        $event = Event::create()
            ->uniqueIdentifier("booking-{$booking->ulid}@sitterwise.com")
            ->startsAt($start)
            ->endsAt($end)
            ->name(trim(($service ?? 'Job').' - '.$clientName))
            ->description("Booking #{$booking->ulid}\nService: ".($service ?? 'N/A'))
            ->status(EventStatus::Confirmed);

        $location = $this->locationFor($booking);

        if ($location !== '') {
            $event->address($location);
        }

        return $event;
    }

    private function locationFor(Booking $booking): string
    {
        if ($booking->hotel?->name) {
            return $booking->hotel->name;
        }

        return trim(trim(($booking->address_city ?? '').', '.($booking->address_state ?? '')), ', ');
    }

    private function newToken(): string
    {
        return Str::random(32);
    }
}
