<?php

use App\Enums\BookingStatus;
use App\Events\BookingReminderTriggered;
use App\Listeners\SendBookingReminderNotifications;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Notifications\BookingReminderNotification;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->seed([
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->client = Client::factory()->create();
    $this->caregiver = Caregiver::factory()->create();
});

function reminderBooking(Client $client, Caregiver $caregiver, string $status): Booking
{
    return Booking::factory()->forClient($client)->create([
        'caregiver_id' => $caregiver->id,
        'status' => $status,
        'start_datetime' => now()->addHours(23)->addMinutes(30),
        'end_datetime' => now()->addHours(27),
    ]);
}

describe('Booking reminder re-check', function () {
    test('sends a reminder for a still-confirmed booking', function () {
        $booking = reminderBooking($this->client, $this->caregiver, BookingStatus::Confirmed->value);

        (new SendBookingReminderNotifications)->handle(new BookingReminderTriggered($booking));

        Notification::assertSentTo($this->caregiver->user, BookingReminderNotification::class);
    });

    test('does not send when the booking was cancelled after dispatch', function () {
        $booking = reminderBooking($this->client, $this->caregiver, BookingStatus::Confirmed->value);

        // Simulate the cancel landing between the hourly dispatch and this job.
        $event = new BookingReminderTriggered($booking);
        $booking->update(['status' => BookingStatus::Cancelled->value]);

        (new SendBookingReminderNotifications)->handle($event);

        Notification::assertNothingSent();
    });

    test('does not send to a caregiver who was unassigned after dispatch', function () {
        $booking = reminderBooking($this->client, $this->caregiver, BookingStatus::Confirmed->value);

        // Capture the event, then unassign the caregiver (job returns to the pool)
        // between the hourly dispatch and this queued listener running.
        $event = new BookingReminderTriggered($booking);
        $booking->update([
            'caregiver_id' => null,
            'status' => BookingStatus::Received->value,
        ]);

        (new SendBookingReminderNotifications)->handle($event);

        Notification::assertNothingSent();
    });

    test('reminder follows a replacement to the new caregiver only', function () {
        $original = $this->caregiver;
        $replacement = Caregiver::factory()->create();

        $booking = reminderBooking($this->client, $original, BookingStatus::Confirmed->value);

        $event = new BookingReminderTriggered($booking);
        $booking->update(['caregiver_id' => $replacement->id]);

        (new SendBookingReminderNotifications)->handle($event);

        Notification::assertSentTo($replacement->user, BookingReminderNotification::class);
        Notification::assertNotSentTo($original->user, BookingReminderNotification::class);
    });

    test('the reminder listener is queued', function () {
        expect(new SendBookingReminderNotifications)
            ->toBeInstanceOf(ShouldQueue::class);
    });
});

describe('Booking reminder command window', function () {
    test('dispatches for a confirmed booking starting in ~23.5 hours', function () {
        Event::fake([BookingReminderTriggered::class]);

        $booking = reminderBooking($this->client, $this->caregiver, BookingStatus::Confirmed->value);

        $this->artisan('app:send-booking-reminders')
            ->expectsOutputToContain('Booking reminders sent: 1');

        Event::assertDispatched(BookingReminderTriggered::class, fn ($event) => $event->booking->is($booking));
    });

    test('does not dispatch for a booking starting in ~16.5 hours (timezone regression)', function () {
        Event::fake([BookingReminderTriggered::class]);

        // Before the fix the window bounds were LA-local strings compared against
        // UTC-stored datetimes, shifting the window ~7 hours and matching this booking.
        Booking::factory()->forClient($this->client)->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Confirmed->value,
            'start_datetime' => now()->addHours(16)->addMinutes(30),
            'end_datetime' => now()->addHours(20),
        ]);

        $this->artisan('app:send-booking-reminders')
            ->expectsOutputToContain('Booking reminders sent: 0');

        Event::assertNotDispatched(BookingReminderTriggered::class);
    });

    test('does not dispatch for unconfirmed or out-of-window bookings', function () {
        Event::fake([BookingReminderTriggered::class]);

        reminderBooking($this->client, $this->caregiver, BookingStatus::Received->value);

        Booking::factory()->forClient($this->client)->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Confirmed->value,
            'start_datetime' => now()->addHours(30),
            'end_datetime' => now()->addHours(34),
        ]);

        $this->artisan('app:send-booking-reminders')
            ->expectsOutputToContain('Booking reminders sent: 0');

        Event::assertNotDispatched(BookingReminderTriggered::class);
    });
});
