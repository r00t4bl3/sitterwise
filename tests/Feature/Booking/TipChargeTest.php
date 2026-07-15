<?php

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
use App\Models\PricingRule;
use App\Models\User;
use App\Services\Billing\TipChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

function tipBooking(): Booking
{
    $client = Client::factory()->create([
        'stripe_customer_id' => 'cus_'.uniqid(),
    ]);

    PricingRule::create([
        'service_type' => ServiceType::Babysitter->value,
        'number_of_children' => 0,
        'is_for_pets' => false,
        'charge_to_client' => 20,
        'paid_to_caregiver' => 15,
        'sitterwise_cut' => 5,
        'payment_form' => 'Stripe',
    ]);

    return Booking::factory()->forClient($client)->create([
        'status' => BookingStatus::Completed->value,
        'charge_to_client_hourly' => 20,
        'total_working_hour' => 5,
        'charge_to_client' => 100,
        'total_service_amount' => 100,
        'total_amount' => 100,
        'reimbursement' => 0,
        'bonus' => 0,
        'tip' => 0,
        'payment_status' => BookingPaymentStatus::Paid->value,
        'charge_attempt_count' => 1,
        'stripe_payment_intent_id' => 'pi_'.uniqid(),
        'actual_amount' => 100,
    ]);
}

describe('Tip Charge Service', function () {
    test('rejects zero or negative tip amounts', function () {
        $booking = tipBooking();
        $tipService = new TipChargeService;

        $result = $tipService->charge($booking, 0, 'pm_mock');
        expect($result['success'])->toBeFalse();

        $result = $tipService->charge($booking, -5, 'pm_mock');
        expect($result['success'])->toBeFalse();
    });

    test('does not allow duplicate pending tip for same booking', function () {
        $booking = tipBooking();

        $paymentMethod = ClientPaymentMethod::create([
            'client_id' => $booking->client_id,
            'provider_method_id' => 'pm_existing',
            'provider' => 'stripe',
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'is_default' => true,
            'status' => 'active',
        ]);

        ClientPayment::create([
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 15,
            'currency' => 'usd',
            'status' => 'pending',
            'provider' => 'stripe',
            'metadata' => [
                'type' => 'tip',
                'booking_id' => $booking->id,
            ],
        ]);

        $tipService = new TipChargeService;
        $result = $tipService->charge($booking, 10.00, 'pm_mock_method');

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('already pending');
    });

    test('returns error when client has no payment method', function () {
        $booking = tipBooking();

        ClientPaymentMethod::where('client_id', $booking->client_id)->delete();
        $booking->client->update(['stripe_customer_id' => null]);

        $tipService = new TipChargeService;
        $result = $tipService->charge($booking, 20);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('No default payment method');
    });

    test('uses provided payment method id when available', function () {
        $booking = tipBooking();

        ClientPaymentMethod::create([
            'client_id' => $booking->client_id,
            'provider_method_id' => 'pm_provided_method',
            'provider' => 'stripe',
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'is_default' => false,
            'status' => 'active',
        ]);

        $tipService = new TipChargeService;
        $result = $tipService->charge($booking, 5, 'pm_provided_method');

        expect($result)->toHaveKey('success');
    });

    test('tip description names the caregiver, not the job number', function () {
        $booking = tipBooking();
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'first_name' => 'Carla',
            'last_name' => 'Sitter',
            'phone' => '+16195551212',
            'status' => 'active',
        ]);
        $booking->update(['caregiver_id' => $caregiver->id]);

        expect((new TipChargeService)->tipDescription($booking->fresh()))
            ->toBe('Tip for Carla Sitter');
    });

    test('tip description falls back to the job number when no caregiver assigned', function () {
        $booking = tipBooking();
        $booking->update(['caregiver_id' => null]);

        expect((new TipChargeService)->tipDescription($booking->fresh()))
            ->toBe("Booking #{$booking->id} - Tip for Caregiver");
    });

    test('falls back to default payment method when no provided id', function () {
        $booking = tipBooking();

        ClientPaymentMethod::create([
            'client_id' => $booking->client_id,
            'provider_method_id' => 'pm_default_method',
            'provider' => 'stripe',
            'brand' => 'visa',
            'last4' => '1111',
            'exp_month' => 12,
            'exp_year' => 2030,
            'is_default' => true,
            'status' => 'active',
        ]);

        $tipService = new TipChargeService;
        $result = $tipService->charge($booking, 25);

        expect($result)->toHaveKey('success');
    });

    test('does not charge again when a tip already succeeded (idempotent)', function () {
        $booking = tipBooking();

        $paymentMethod = ClientPaymentMethod::create([
            'client_id' => $booking->client_id,
            'provider_method_id' => 'pm_prior_tip',
            'provider' => 'stripe',
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'is_default' => true,
            'status' => 'active',
        ]);

        ClientPayment::create([
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 10,
            'currency' => 'usd',
            'status' => 'succeeded',
            'provider' => 'stripe',
            'metadata' => ['type' => 'tip', 'booking_id' => $booking->id],
        ]);

        // A bare Stripe mock with no expectations: if charge() tried to create a
        // PaymentIntent this test would fail, proving the guard short-circuits
        // before any charge.
        $stripe = Mockery::mock(StripeClient::class);

        $result = (new TipChargeService($stripe))->charge($booking, 10, 'pm_prior_tip');

        expect($result['success'])->toBeTrue()
            ->and($result['skipped'] ?? false)->toBeTrue();

        expect(ClientPayment::where('booking_id', $booking->id)
            ->whereJsonContains('metadata->type', 'tip')
            ->count())->toBe(1);
    });
});
