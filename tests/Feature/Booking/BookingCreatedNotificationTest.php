<?php

use App\Enums\ServiceType;
use App\Events\BookingCreated;
use App\Listeners\SendBookingCreatedNotifications;
use App\Mail\AdminBookingCreatedMail;
use App\Mail\ClientBookingCreatedMail;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientPaymentMethod;
use App\Models\PricingRule;
use App\Models\User;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\ClientPaymentRequiredNotification;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
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

function createdBooking(): array
{
    $client = Client::factory()->create();
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

    $booking = Booking::factory()->forClient($client)->create();

    return [$booking, $client, $admin];
}

describe('Booking Created Notifications', function () {
    test('BookingCreated event dispatches', function () {
        Event::fake();

        [$booking] = createdBooking();

        event(new BookingCreated($booking));

        Event::assertDispatched(BookingCreated::class);
    });

    test('SendBookingCreatedNotifications listener is queued', function () {
        $listener = app(SendBookingCreatedNotifications::class);

        expect($listener)->toBeInstanceOf(ShouldQueue::class);
    });

    test('sends notification to client user', function () {
        Notification::fake();

        [$booking] = createdBooking();
        ClientPaymentMethod::factory()->create(['client_id' => $booking->client->id]);

        event(new BookingCreated($booking));

        Notification::assertSentTo(
            $booking->client->user,
            BookingCreatedNotification::class,
        );
    });

    test('defers BookingCreatedNotification when client has no payment method', function () {
        Notification::fake();

        [$booking] = createdBooking();

        event(new BookingCreated($booking));

        Notification::assertNotSentTo(
            $booking->client->user,
            BookingCreatedNotification::class,
        );
        Notification::assertSentTo(
            $booking->client->user,
            ClientPaymentRequiredNotification::class,
        );
    });

    test('sends deferred BookingCreatedNotification after payment method added', function () {
        Notification::fake();

        [$booking, $client] = createdBooking();

        // First event — no payment method → deferred
        event(new BookingCreated($booking));

        Notification::assertNotSentTo(
            $booking->client->user,
            BookingCreatedNotification::class,
        );

        // Add payment method
        ClientPaymentMethod::factory()->create(['client_id' => $client->id]);

        // Second event — now has payment method → sent
        event(new BookingCreated($booking));

        Notification::assertSentTo(
            $booking->client->user,
            BookingCreatedNotification::class,
        );
        Notification::assertSentTo(
            $booking->client->user,
            ClientPaymentRequiredNotification::class,
        );
    });

    test('sends notification to admin users', function () {
        Mail::fake();
        Notification::fake();

        [$booking] = createdBooking();

        event(new BookingCreated($booking));

        Mail::assertSent(AdminBookingCreatedMail::class, 1);
    });

    test('handles missing client gracefully', function () {
        Notification::fake();
        Mail::fake();

        [$booking, $client] = createdBooking();
        $client->delete();

        event(new BookingCreated($booking));

        Notification::assertNotSentTo(
            $client->user,
            BookingCreatedNotification::class,
        );
        Mail::assertSent(AdminBookingCreatedMail::class, 1);
    });

    test('handles deleted client user gracefully', function () {
        Notification::fake();
        Mail::fake();

        [$booking, $client] = createdBooking();
        $client->user->delete();

        event(new BookingCreated($booking));

        Notification::assertNotSentTo(
            $client->user,
            BookingCreatedNotification::class,
        );
        Mail::assertSent(AdminBookingCreatedMail::class, 1);
    });

    test('admin database payload contains correct data', function () {
        [$booking, , $admin] = createdBooking();

        $notification = new BookingCreatedNotification($booking);
        $payload = $notification->toArray($admin);

        expect($payload['booking_id'])->toBe($booking->id);
        expect($payload['title'])->toBe('New Booking Request');
        expect($payload['type'])->toBe('booking_created');
    });

    test('client database payload contains correct data', function () {
        [$booking] = createdBooking();
        $clientUser = $booking->client->user;

        $notification = new BookingCreatedNotification($booking);
        $payload = $notification->toArray($clientUser);

        expect($payload['booking_id'])->toBe($booking->id);
        expect($payload['title'])->toBe('Booking Received');
        expect($payload['type'])->toBe('booking_created');
    });

    test('admin mail returns AdminBookingCreatedMail', function () {
        [$booking, , $admin] = createdBooking();

        $notification = new BookingCreatedNotification($booking);
        $mail = $notification->toMail($admin);

        expect($mail)->toBeInstanceOf(AdminBookingCreatedMail::class);
        expect($mail->booking->id)->toBe($booking->id);
    });

    test('client mail returns ClientBookingCreatedMail', function () {
        [$booking] = createdBooking();
        $clientUser = $booking->client->user;

        $notification = new BookingCreatedNotification($booking);
        $mail = $notification->toMail($clientUser);

        expect($mail)->toBeInstanceOf(ClientBookingCreatedMail::class);
        expect($mail->booking->id)->toBe($booking->id);
    });
});
