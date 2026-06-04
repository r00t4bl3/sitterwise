<?php

use App\Enums\ServiceType;
use App\Events\BookingAccepted;
use App\Listeners\SendBookingAcceptedNotifications;
use App\Mail\AdminBookingAcceptedMail;
use App\Mail\CaregiverBookingAcceptedMail;
use App\Mail\ClientBookingAcceptedMail;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\PricingRule;
use App\Models\User;
use App\Notifications\BookingAcceptedNotification;
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

function acceptedBooking(): array
{
    $client = Client::factory()->create();
    $caregiver = Caregiver::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);

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
        'status' => 'confirmed',
        'caregiver_id' => $caregiver->id,
        'confirmed_by' => $caregiver->id,
        'confirmed_at' => now(),
    ]);

    return [$booking, $caregiver, $client, $admin];
}

describe('Booking Accepted Notifications', function () {
    test('BookingAccepted event dispatches', function () {
        Event::fake();

        [$booking] = acceptedBooking();

        event(new BookingAccepted($booking));

        Event::assertDispatched(BookingAccepted::class);
    });

    test('SendBookingAcceptedNotifications listener is queued', function () {
        $listener = app(SendBookingAcceptedNotifications::class);

        expect($listener)->toBeInstanceOf(ShouldQueue::class);
    });

    test('sends notification to client user', function () {
        Notification::fake();

        [$booking] = acceptedBooking();

        event(new BookingAccepted($booking));

        Notification::assertSentTo(
            $booking->client->user,
            BookingAcceptedNotification::class,
        );
    });

    test('sends notification to caregiver user', function () {
        Notification::fake();

        [$booking] = acceptedBooking();

        event(new BookingAccepted($booking));

        Notification::assertSentTo(
            $booking->caregiver->user,
            BookingAcceptedNotification::class,
        );
    });

    test('sends notification to admin users', function () {
        Notification::fake();

        [$booking, , , $admin] = acceptedBooking();

        event(new BookingAccepted($booking));

        Notification::assertSentTo(
            $admin,
            BookingAcceptedNotification::class,
        );
    });

    test('handles missing client gracefully', function () {
        Notification::fake();

        [$booking, $caregiver, $client, $admin] = acceptedBooking();
        $client->delete();

        event(new BookingAccepted($booking));

        Notification::assertNotSentTo(
            $client->user,
            BookingAcceptedNotification::class,
        );
        Notification::assertSentTo(
            $caregiver->user,
            BookingAcceptedNotification::class,
        );
        Notification::assertSentTo(
            $admin,
            BookingAcceptedNotification::class,
        );
    });

    test('handles missing caregiver gracefully', function () {
        Notification::fake();

        [$booking, , $client] = acceptedBooking();
        $booking->caregiver_id = null;
        $booking->save();

        event(new BookingAccepted($booking));

        Notification::assertSentTo(
            $client->user,
            BookingAcceptedNotification::class,
        );
    });

    test('handles deleted client user gracefully', function () {
        Notification::fake();

        [$booking, $caregiver] = acceptedBooking();
        $booking->client->user->delete();

        event(new BookingAccepted($booking));

        Notification::assertSentTo(
            $caregiver->user,
            BookingAcceptedNotification::class,
        );
    });

    test('handles deleted caregiver user gracefully', function () {
        Notification::fake();

        [$booking, , $client] = acceptedBooking();
        $booking->caregiver->user->delete();

        event(new BookingAccepted($booking));

        Notification::assertSentTo(
            $client->user,
            BookingAcceptedNotification::class,
        );
    });

    test('admin database payload contains correct data', function () {
        [$booking, , , $admin] = acceptedBooking();

        $notification = new BookingAcceptedNotification($booking);
        $payload = $notification->toArray($admin);

        expect($payload['booking_id'])->toBe($booking->id);
        expect($payload['title'])->toBe('Booking Confirmed');
        expect($payload['type'])->toBe('booking_accepted');
        expect($payload['message'])->toContain('confirmed');
    });

    test('caregiver database payload contains correct data', function () {
        [$booking] = acceptedBooking();
        $caregiverUser = $booking->caregiver->user;
        $clientName = $booking->client->first_name.' '.$booking->client->last_name;

        $notification = new BookingAcceptedNotification($booking);
        $payload = $notification->toArray($caregiverUser);

        expect($payload['booking_id'])->toBe($booking->id);
        expect($payload['title'])->toBe('Assignment Confirmed');
        expect($payload['type'])->toBe('booking_accepted');
        expect($payload['message'])->toContain($clientName);
    });

    test('client database payload contains correct data', function () {
        [$booking] = acceptedBooking();
        $clientUser = $booking->client->user;
        $caregiverName = $booking->caregiver->first_name.' '.$booking->caregiver->last_name;

        $notification = new BookingAcceptedNotification($booking);
        $payload = $notification->toArray($clientUser);

        expect($payload['booking_id'])->toBe($booking->id);
        expect($payload['title'])->toBe('Sitter Matched');
        expect($payload['type'])->toBe('booking_accepted');
        expect($payload['message'])->toContain($caregiverName);
    });

    test('admin mail returns AdminBookingAcceptedMail', function () {
        [$booking, , , $admin] = acceptedBooking();

        $notification = new BookingAcceptedNotification($booking);
        $mail = $notification->toMail($admin);

        expect($mail)->toBeInstanceOf(AdminBookingAcceptedMail::class);
        expect($mail->booking->id)->toBe($booking->id);
    });

    test('caregiver mail returns CaregiverBookingAcceptedMail', function () {
        [$booking] = acceptedBooking();
        $caregiverUser = $booking->caregiver->user;

        $notification = new BookingAcceptedNotification($booking);
        $mail = $notification->toMail($caregiverUser);

        expect($mail)->toBeInstanceOf(CaregiverBookingAcceptedMail::class);
        expect($mail->booking->id)->toBe($booking->id);
    });

    test('client mail returns ClientBookingAcceptedMail', function () {
        [$booking] = acceptedBooking();
        $clientUser = $booking->client->user;

        $notification = new BookingAcceptedNotification($booking);
        $mail = $notification->toMail($clientUser);

        expect($mail)->toBeInstanceOf(ClientBookingAcceptedMail::class);
        expect($mail->booking->id)->toBe($booking->id);
    });

    test('sms content contains expected details', function () {
        [$booking] = acceptedBooking();
        $clientUser = $booking->client->user;

        $notification = new BookingAcceptedNotification($booking);
        $sms = $notification->toSms($clientUser);

        expect($sms->message)->toContain($booking->client->last_name);
        expect($sms->message)->toContain('caregiver');
    });
});
