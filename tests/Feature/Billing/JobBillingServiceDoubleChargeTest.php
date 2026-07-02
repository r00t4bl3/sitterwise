<?php

use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
use App\Models\PricingRule;
use App\Services\Billing\JobBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->client = Client::factory()->create(['stripe_customer_id' => 'cus_test123']);
    ClientPaymentMethod::factory()->create([
        'client_id' => $this->client->id,
        'is_default' => true,
        'status' => 'active',
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
});

function chargeableBooking(Client $client, array $overrides = []): Booking
{
    return Booking::factory()->forClient($client)->create(array_merge([
        'status' => BookingStatus::Completed->value,
        'charge_to_client_hourly' => 20,
        'total_working_hour' => 5,
        'charge_to_client' => 100,
        'total_service_amount' => 100,
        'total_amount' => 100,
        'reimbursement' => 0,
        'bonus' => 0,
        'tip' => 0,
        'payment_status' => 'pending',
        'charge_attempt_count' => 0,
    ], $overrides));
}

/**
 * Build a JobBillingService whose Stripe client is the given mock, injected
 * through the protected $stripe property (mirrors ClientPaymentSyncTest).
 */
function billingServiceWithStripe(object $stripeMock): JobBillingService
{
    $service = new JobBillingService;

    $ref = new ReflectionProperty(JobBillingService::class, 'stripe');
    $ref->setAccessible(true);
    $ref->setValue($service, $stripeMock);

    return $service;
}

describe('JobBillingService double-charge protection', function () {
    test('passes a stable idempotency key to Stripe when charging', function () {
        $booking = chargeableBooking($this->client);

        $paymentIntents = Mockery::mock();
        $paymentIntents->shouldReceive('create')
            ->once()
            ->withArgs(function ($params, $opts = null) use ($booking) {
                return is_array($opts)
                    && ($opts['idempotency_key'] ?? null) === "booking_{$booking->id}_charge_0";
            })
            ->andReturn((object) ['id' => 'pi_test_123']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->paymentIntents = $paymentIntents;

        $result = billingServiceWithStripe($stripe)->charge($booking);

        expect($result['success'])->toBeTrue();
        expect($booking->fresh()->payment_status)->toBe('charged');
    });

    test('does not reach Stripe when a charge is already in flight', function () {
        $booking = chargeableBooking($this->client, [
            'payment_status' => 'charging',
            'last_charge_attempt_at' => now(),
        ]);

        $paymentIntents = Mockery::mock();
        $paymentIntents->shouldNotReceive('create');

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->paymentIntents = $paymentIntents;

        $result = billingServiceWithStripe($stripe)->charge($booking);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('already');
        expect(ClientPayment::where('booking_id', $booking->id)->count())->toBe(0);
        expect($booking->fresh()->payment_status)->toBe('charging');
    });

    test('rejects a booking that has already been charged', function () {
        $booking = chargeableBooking($this->client, ['payment_status' => 'charged']);

        $paymentIntents = Mockery::mock();
        $paymentIntents->shouldNotReceive('create');

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->paymentIntents = $paymentIntents;

        $result = billingServiceWithStripe($stripe)->charge($booking);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('already');
    });

    test('allows a retry once a stale charging claim has expired', function () {
        $booking = chargeableBooking($this->client, [
            'payment_status' => 'charging',
            'last_charge_attempt_at' => now()->subMinutes(5),
        ]);

        $paymentIntents = Mockery::mock();
        $paymentIntents->shouldReceive('create')
            ->once()
            ->andReturn((object) ['id' => 'pi_retry_123']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->paymentIntents = $paymentIntents;

        $result = billingServiceWithStripe($stripe)->charge($booking);

        expect($result['success'])->toBeTrue();
        expect($booking->fresh()->payment_status)->toBe('charged');
    });
});
