<?php

use App\Enums\ServiceType;
use App\Events\BookingReceipt;
use App\Listeners\SendBookingReceiptNotification;
use App\Mail\ClientReceiptMail;
use App\Models\Booking;
use App\Models\Client;
use App\Models\PricingRule;
use App\Notifications\BookingReceiptNotification;
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

function receiptBooking(): array
{
    $client = Client::factory()->create();

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

    return [$booking, $client];
}

describe('Booking Receipt Notifications', function () {
    test('BookingReceipt event dispatches', function () {
        Event::fake();

        [$booking] = receiptBooking();

        event(new BookingReceipt($booking));

        Event::assertDispatched(BookingReceipt::class);
    });

    test('SendBookingReceiptNotification listener is queued', function () {
        $listener = app(SendBookingReceiptNotification::class);

        expect($listener)->toBeInstanceOf(ShouldQueue::class);
    });

    test('sends notification to client user', function () {
        Notification::fake();

        [$booking] = receiptBooking();

        event(new BookingReceipt($booking));

        Notification::assertSentTo(
            $booking->client->user,
            BookingReceiptNotification::class,
        );
    });

    test('handles missing client gracefully', function () {
        Notification::fake();

        [$booking, $client] = receiptBooking();
        $client->delete();

        event(new BookingReceipt($booking));

        Notification::assertNothingSent();
    });

    test('database payload contains correct data', function () {
        [$booking] = receiptBooking();
        $clientUser = $booking->client->user;

        $notification = new BookingReceiptNotification($booking);
        $payload = $notification->toArray($clientUser);

        expect($payload['booking_id'])->toBe($booking->id);
        expect($payload['title'])->toBe('Booking Receipt');
        expect($payload['type'])->toBe('booking_receipt');
    });

    test('mail returns ClientReceiptMail', function () {
        [$booking] = receiptBooking();
        $clientUser = $booking->client->user;

        $notification = new BookingReceiptNotification($booking);
        $mail = $notification->toMail($clientUser);

        expect($mail)->toBeInstanceOf(ClientReceiptMail::class);
        expect($mail->booking->id)->toBe($booking->id);
    });
});
