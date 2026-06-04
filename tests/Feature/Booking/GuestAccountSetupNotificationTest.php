<?php

use App\Enums\ServiceType;
use App\Events\GuestAccountSetup;
use App\Listeners\SendGuestAccountSetupNotification;
use App\Mail\GuestAccountSetupMail;
use App\Models\Booking;
use App\Models\Client;
use App\Models\PricingRule;
use App\Notifications\GuestAccountSetupNotification;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        AttributeDefinitionSeeder::class,
        CertificationTypeSeeder::class,
        LocationSeeder::class,
        SpecialtyTypeSeeder::class,
    ]);
});

function setupBooking(): array
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
    $resetToken = Str::random(32);

    return [$booking, $client, $resetToken];
}

describe('Guest Account Setup Notifications', function () {
    test('GuestAccountSetup event dispatches', function () {
        Event::fake();

        [$booking, , $resetToken] = setupBooking();

        event(new GuestAccountSetup($booking, $resetToken));

        Event::assertDispatched(GuestAccountSetup::class);
    });

    test('SendGuestAccountSetupNotification listener is queued', function () {
        $listener = app(SendGuestAccountSetupNotification::class);

        expect($listener)->toBeInstanceOf(ShouldQueue::class);
    });

    test('sends notification to client user with reset token', function () {
        Notification::fake();

        [$booking, , $resetToken] = setupBooking();

        event(new GuestAccountSetup($booking, $resetToken));

        Notification::assertSentTo(
            $booking->client->user,
            GuestAccountSetupNotification::class,
        );
    });

    test('handles missing client gracefully', function () {
        Notification::fake();

        [$booking, $client, $resetToken] = setupBooking();
        $client->delete();

        event(new GuestAccountSetup($booking, $resetToken));

        Notification::assertNothingSent();
    });

    test('database payload contains correct data', function () {
        [$booking] = setupBooking();
        $clientUser = $booking->client->user;

        $notification = new GuestAccountSetupNotification($booking, 'test-token');
        $payload = $notification->toArray($clientUser);

        expect($payload['booking_id'])->toBe($booking->id);
        expect($payload['title'])->toBe('Account Created');
        expect($payload['type'])->toBe('account_setup');
    });

    test('mail returns GuestAccountSetupMail with reset token', function () {
        [$booking] = setupBooking();
        $clientUser = $booking->client->user;

        $notification = new GuestAccountSetupNotification($booking, 'test-reset-token');
        $mail = $notification->toMail($clientUser);

        expect($mail)->toBeInstanceOf(GuestAccountSetupMail::class);
        expect($mail->booking->id)->toBe($booking->id);
        expect($mail->resetToken)->toBe('test-reset-token');
    });
});
