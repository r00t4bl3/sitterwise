<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Enums\LocationType;
use App\Mail\CaregiverBookingInvitationMail;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class BookingInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', SmsChannel::class, WebPushChannel::class];
    }

    public function toMail(object $notifiable)
    {
        $address = $notifiable->routeNotificationFor('mail', $this);

        return (new CaregiverBookingInvitationMail($this->booking))->to($address);
    }

    public function toSms(object $notifiable): object
    {
        $timezone = 'America/Los_Angeles';
        $start = $this->booking->start_datetime->copy()->setTimezone($timezone);
        $end = $this->booking->end_datetime->copy()->setTimezone($timezone);

        $group = $this->booking->bookingGroup;
        $isHotel = $group?->location_type === LocationType::Hotel->value;
        $location = $isHotel
            ? ($group->hotel_name ?: $group->hotel?->name ?: 'Hotel')
            : ($group?->address_city ?: '');
        $day = $start->format('D');
        $startTime = $start->format('g:ia');
        $endTime = $end->format('g:ia');

        $isMultiDay = $group && ($group->bookings_count ?? $group->bookings()->count()) > 1;

        $line1 = $isMultiDay
            ? $this->buildMultiDayLine1($group, $start, $end, $day, $startTime, $endTime)
            : "New job – {$day} {$start->format('n/j')}, {$startTime}–{$endTime}";

        $children = $group?->children ?? [];
        $kidsCount = count($children);
        $kidLabel = $kidsCount === 1 ? '1 child' : "{$kidsCount} children";
        $ages = collect($children)
            ->map(fn ($c) => $this->childAge($c))
            ->filter()
            ->values();

        $ageList = $ages->count() > 2
            ? $ages->slice(0, -1)->join(', ').' & '.$ages->last()
            : $ages->join(' & ');

        $line2 = $ageList !== ''
            ? "{$location} · {$kidLabel} ({$ageList})"
            : "{$location} · {$kidLabel}";

        $link = route('jobs.short', $this->booking);
        $line3 = "View & claim: {$link}";

        $message = "{$line1}\n{$line2}\n{$line3}";

        if (mb_strlen($message) > 160 && $isMultiDay) {
            $message = "New job – {$start->format('n/j')}–{$end->format('n/j')} (multi-day) · {$location} · {$kidLabel} · tap for hours/ages: {$link}";
        }

        return (object) ['message' => $message];
    }

    private function buildMultiDayLine1($group, $start, $end, string $day, string $startTime, string $endTime): string
    {
        $allBookings = $group->bookings()->orderBy('start_datetime')->get(['start_datetime', 'end_datetime']);
        $tz = 'America/Los_Angeles';
        $firstDay = $allBookings->first()->start_datetime->copy()->setTimezone($tz);
        $lastDay = $allBookings->last()->start_datetime->copy()->setTimezone($tz);

        $dateRange = $firstDay->format('n') === $lastDay->format('n')
            ? "{$firstDay->format('D n/j')}–{$lastDay->format('D n/j')}"
            : "{$firstDay->format('D n/j')}–{$lastDay->format('D n/j')}";

        $crossesMidnight = (int) $start->format('Hi') > (int) $end->format('Hi');
        $timeDisplay = $crossesMidnight ? 'overnight' : "{$startTime}–{$endTime} daily";

        return "New job – {$dateRange}, {$timeDisplay}";
    }

    private function childAge(array $child): ?int
    {
        // Guard against birth_year 0 (junk import data) which would otherwise
        // report an age of ~2026.
        if (! empty($child['birth_year'])) {
            return now()->year - (int) $child['birth_year'];
        }

        if (! empty($child['birth_date'])) {
            return Carbon::parse($child['birth_date'])->diffInYears(now());
        }

        return ! empty($child['age']) ? (int) $child['age'] : null;
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        $clientName = ($this->booking->client?->first_name ?? $this->booking->client_first_name)
            .' '.($this->booking->client?->last_name ?? $this->booking->client_last_name);

        $city = $this->booking->bookingGroup?->address_city;

        $start = $this->booking->start_datetime->copy()->setTimezone('America/Los_Angeles');
        $end = $this->booking->end_datetime->copy()->setTimezone('America/Los_Angeles');

        $startFormatted = $start->format('n/j/y g:ia');
        $endFormatted = $end->format('g:ia');

        $location = $city ? " in {$city}" : '';

        return (new WebPushMessage)
            ->title("New {$this->booking->service_type_label} job available")
            ->body("{$clientName}{$location}: {$startFormatted}-{$endFormatted}")
            ->icon('/icon-192.png')
            ->badge('/icon-72.png')
            ->data(['url' => '/caregiver/jobs'])
            ->options(['TTL' => 43200]);
    }

    public function toArray(object $notifiable): array
    {
        $clientName = ($this->booking->client?->first_name ?? $this->booking->client_first_name);

        return [
            'booking_id' => $this->booking->id,
            'title' => 'New Job Invitation',
            'message' => "You have a new job invitation for {$clientName}. Click to view and claim.",
            'type' => 'booking_invitation',
        ];
    }
}
