<?php

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Client;
use App\Models\PricingRule;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Services\Billing\PaymentFailureHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function failedBooking(): array
{
    $client = Client::factory()->create([
        'stripe_customer_id' => 'cus_'.uniqid(),
    ]);

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
        'status' => BookingStatus::Completed->value,
        'charge_to_client_hourly' => 20,
        'total_working_hour' => 5,
        'charge_to_client' => 100,
        'total_service_amount' => 100,
        'total_amount' => 100,
        'reimbursement' => 0,
        'bonus' => 0,
        'tip' => 0,
        'payment_status' => BookingPaymentStatus::Pending->value,
        'charge_attempt_count' => 1,
    ]);

    return [$client, $user, $booking];
}

describe('Payment Failure Notifications', function () {
    test('client receives notification on payment failure', function () {
        Notification::fake();

        [$client, $user, $booking] = failedBooking();

        app(PaymentFailureHandler::class)->handle($booking, 'card_declined', 'Card declined');

        Notification::assertSentTo($client, PaymentFailedNotification::class);
    });

    test('admin receives notification on payment failure', function () {
        Notification::fake();

        [$client, $user, $booking] = failedBooking();
        $admin = User::factory()->create(['role' => 'admin']);

        app(PaymentFailureHandler::class)->handle($booking, 'card_declined', 'Card declined');

        Notification::assertSentTo($admin, PaymentFailedNotification::class);
    });

    test('client notification payload contains booking and attempt info', function () {
        Notification::fake();

        [$client, $user, $booking] = failedBooking();

        app(PaymentFailureHandler::class)->handle($booking, 'expired_card', 'Expired');

        Notification::assertSentTo($client, PaymentFailedNotification::class, function ($notification) use ($booking, $client) {
            $payload = $notification->toArray($client);

            return $payload['booking_id'] === $booking->id
                && $payload['attempt'] === 1
                && $payload['error'] === 'Expired'
                && str_contains($payload['message'], 'update your payment method');
        });
    });

    test('admin notification payload contains client name and booking info', function () {
        Notification::fake();

        [$client, $user, $booking] = failedBooking();
        $admin = User::factory()->create(['role' => 'admin']);

        app(PaymentFailureHandler::class)->handle($booking, 'lost_card', 'Lost card');

        Notification::assertSentTo($admin, PaymentFailedNotification::class, function ($notification) use ($booking, $client, $admin) {
            $payload = $notification->toArray($admin);

            return $payload['booking_id'] === $booking->id
                && $payload['client_id'] === $client->id
                && $payload['client_name'] === $client->full_name
                && $payload['attempt'] === 1
                && $payload['error'] === 'Lost card'
                && str_contains($payload['message'], $client->full_name);
        });
    });
});
