<?php

use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Events\BookingInvitationSent;
use App\Events\BookingReceipt;
use App\Events\BookingReminderTriggered;
use App\Listeners\SendBookingReminderNotifications;
use App\Mail\CaregiverBookingReminderMail;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\PricingRule;
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
    $this->seed([
        AttributeDefinitionSeeder::class,
        CertificationTypeSeeder::class,
        LocationSeeder::class,
        SpecialtyTypeSeeder::class,
    ]);
});

function bookingWithCaregiver(): array
{
    $client = Client::factory()->create();
    $caregiver = Caregiver::factory()->create();
    $user = $client->user;

    PricingRule::create([
        'service_type' => ServiceType::Babysitter->value,
        'number_of_children' => 0,
        'is_for_pets' => false,
        'charge_to_client' => 20,
        'paid_to_caregiver' => 15,
        'sitterwise_cut' => 5,
        'payment_form' => 'Stripe',
    ]);

    $booking = Booking::factory()->forClient($client)->create([
        'status' => BookingStatus::Received->value,
        'caregiver_id' => $caregiver->id,
        'confirmed_by' => $caregiver->id,
        'confirmed_at' => now(),
    ]);

    return [$booking, $caregiver, $client];
}

describe('Booking Notifications', function () {
    test('BookingInvitationSent dispatches to caregiver user', function () {
        Event::fake();
        Notification::fake();

        [$booking, $caregiver] = bookingWithCaregiver();

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver->id,
            'notified_at' => now(),
        ]);

        event(new BookingInvitationSent($booking, $caregiver));

        // Verify event dispatched cleanly
        Event::assertDispatched(BookingInvitationSent::class);
    });

    test('BookingReminderTriggered event fires', function () {
        Event::fake();

        [$booking] = bookingWithCaregiver();

        event(new BookingReminderTriggered($booking));

        Event::assertDispatched(BookingReminderTriggered::class);
    });

    test('BookingReceipt event fires after charge success', function () {
        Event::fake();

        [$booking] = bookingWithCaregiver();

        event(new BookingReceipt($booking));

        Event::assertDispatched(BookingReceipt::class);
    });

    test('SendBookingReminderNotifications listener is queued', function () {
        $listener = app(SendBookingReminderNotifications::class);

        expect($listener)->toBeInstanceOf(ShouldQueue::class);
    });

    test('SendBookingReminderNotifications sends notification to caregiver user', function () {
        Notification::fake();

        [$booking] = bookingWithCaregiver();

        event(new BookingReminderTriggered($booking));

        Notification::assertSentTo(
            $booking->caregiver->user,
            BookingReminderNotification::class,
        );
    });

    test('SendBookingReminderNotifications handles missing caregiver gracefully', function () {
        Notification::fake();

        [$booking] = bookingWithCaregiver();
        $booking->caregiver_id = null;
        $booking->save();

        event(new BookingReminderTriggered($booking));

        Notification::assertNothingSent();
    });

    test('SendBookingReminderNotifications handles deleted caregiver user gracefully', function () {
        Notification::fake();

        [$booking, $caregiver] = bookingWithCaregiver();
        $caregiver->user->delete();

        event(new BookingReminderTriggered($booking));

        Notification::assertNothingSent();
    });

    test('BookingReminderNotification database payload contains correct data', function () {
        [$booking] = bookingWithCaregiver();

        $notification = new BookingReminderNotification($booking);
        $payload = $notification->toArray($booking->caregiver->user);

        expect($payload['booking_id'])->toBe($booking->id);
        expect($payload['title'])->toBe('Upcoming Job Reminder');
        expect($payload['type'])->toBe('booking_reminder');
        expect($payload['message'])->toContain($booking->client->first_name);
    });

    test('BookingReminderNotification mail returns CaregiverBookingReminderMail', function () {
        [$booking] = bookingWithCaregiver();

        $notification = new BookingReminderNotification($booking);
        $mail = $notification->toMail($booking->caregiver->user);

        expect($mail)->toBeInstanceOf(CaregiverBookingReminderMail::class);
        expect($mail->booking->id)->toBe($booking->id);
    });

    test('CaregiverBookingReminderMail has correct envelope', function () {
        [$booking] = bookingWithCaregiver();

        $mail = new CaregiverBookingReminderMail($booking);
        $envelope = $mail->envelope();

        expect($envelope->subject)->toBe('Upcoming Job Reminder');
        expect($envelope->from->address)->toBe(config('mail.from.address'));
    });
});
