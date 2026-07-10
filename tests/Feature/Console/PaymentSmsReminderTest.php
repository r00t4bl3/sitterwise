<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientPaymentMethod;
use App\Notifications\ClientPaymentSmsReminderNotification;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\PricingRulesTableSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->seed([
        SettingsSeeder::class,
        PricingRulesTableSeeder::class,
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
});

function unpaidStripeBooking(Client $client): Booking
{
    // A booking-group hook derives payment_form from the service type's pricing
    // rule; babysitter settles via Stripe, so this yields a Stripe booking.
    $booking = Booking::factory()
        ->withBookingGroup(fn ($g) => $g->state([
            'client_id' => $client->id,
            'requires_payment' => true,
            'service_type' => 'babysitter',
        ]))
        ->create([
            'payment_status' => 'pending',
            'payment_reminder_sent_at' => null,
        ]);

    // Push it past the 24h age threshold the command requires.
    $booking->updateQuietly(['created_at' => now()->subDays(2)]);

    return $booking;
}

describe('Payment SMS reminders', function () {
    test('a client without a card on file gets the reminder', function () {
        $client = Client::factory()->create();
        $booking = unpaidStripeBooking($client);

        $this->artisan('app:send-payment-sms-reminders')
            ->expectsOutputToContain('Payment SMS reminders sent: 1');

        Notification::assertSentTo($client->user, ClientPaymentSmsReminderNotification::class);
        expect($booking->fresh()->payment_reminder_sent_at)->not->toBeNull();
    });

    test('a client who already has a card on file is not reminded', function () {
        $client = Client::factory()->create();
        ClientPaymentMethod::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
        ]);
        $booking = unpaidStripeBooking($client);

        $this->artisan('app:send-payment-sms-reminders')
            ->expectsOutputToContain('Payment SMS reminders sent: 0');

        Notification::assertNothingSent();
        // Not marked as sent, so it stays eligible if the card is later removed.
        expect($booking->fresh()->payment_reminder_sent_at)->toBeNull();
    });

    test('a cancelled booking never triggers a payment reminder', function () {
        $client = Client::factory()->create();
        $booking = unpaidStripeBooking($client);
        $booking->update(['status' => 'cancelled']);

        $this->artisan('app:send-payment-sms-reminders')
            ->expectsOutputToContain('Payment SMS reminders sent: 0');

        Notification::assertNothingSent();
    });

    test('an inactive (removed) card does not count as having a method on file', function () {
        $client = Client::factory()->create();
        ClientPaymentMethod::factory()->create([
            'client_id' => $client->id,
            'status' => 'inactive',
        ]);
        unpaidStripeBooking($client);

        $this->artisan('app:send-payment-sms-reminders')
            ->expectsOutputToContain('Payment SMS reminders sent: 1');

        Notification::assertSentTo($client->user, ClientPaymentSmsReminderNotification::class);
    });
});
