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

    test('the reminder listener is queued', function () {
        expect(new SendBookingReminderNotifications)
            ->toBeInstanceOf(ShouldQueue::class);
    });
});
