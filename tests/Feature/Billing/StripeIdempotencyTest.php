<?php

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\CaregiverPayoutMethod;
use App\Models\Client;
use App\Models\ClientPaymentMethod;
use App\Models\PricingRule;
use App\Services\Billing\JobBillingService;
use App\Services\Billing\TipChargeService;
use App\Services\CaregiverPayout\CaregiverPayoutService;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Stripe\Exception\ApiConnectionException;
use Tests\Support\FakeStripeHttpClient;

uses(RefreshDatabase::class);

function billingBooking(): Booking
{
    $client = Client::factory()->create([
        'stripe_customer_id' => 'cus_'.uniqid(),
    ]);

    ClientPaymentMethod::factory()->create([
        'client_id' => $client->id,
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
        'payment_status' => BookingPaymentStatus::Pending->value,
        'charge_attempt_count' => 0,
    ]);
}

afterEach(function () {
    FakeStripeHttpClient::reset();
});

describe('Stripe Idempotency Keys', function () {
    test('job charge sends an attempt-scoped idempotency key', function () {
        $stripe = FakeStripeHttpClient::install();
        $booking = billingBooking();

        $result = (new JobBillingService)->charge($booking);

        expect($result['success'])->toBeTrue();
        expect($stripe->idempotencyKeys())->toBe(["booking_{$booking->id}_charge_0"]);
        expect($booking->refresh()->payment_status)->toBe('charged');
    });

    test('a declined card advances the idempotency key for the next attempt', function () {
        Notification::fake();
        Queue::fake();

        $declined = new FakeStripeHttpClient(
            body: '{"error":{"type":"card_error","code":"card_declined","message":"Your card was declined."}}',
            status: 402,
        );
        $stripe = FakeStripeHttpClient::install($declined);
        $booking = billingBooking();

        $service = new JobBillingService;

        expect($service->charge($booking)['success'])->toBeFalse();
        expect($booking->refresh()->charge_attempt_count)->toBe(1);

        $service->charge($booking);

        expect($stripe->idempotencyKeys())->toBe([
            "booking_{$booking->id}_charge_0",
            "booking_{$booking->id}_charge_1",
        ]);
    });

    test('a connection error keeps the same idempotency key so the retry deduplicates', function () {
        Notification::fake();
        Queue::fake();

        $flaky = new FakeStripeHttpClient(
            throws: new ApiConnectionException('Request timed out'),
        );
        $stripe = FakeStripeHttpClient::install($flaky);
        $booking = billingBooking();

        $service = new JobBillingService;

        expect($service->charge($booking)['success'])->toBeFalse();
        expect($booking->refresh()->charge_attempt_count)->toBe(0);

        $flaky->throws = null;

        expect($service->charge($booking)['success'])->toBeTrue();
        expect($stripe->idempotencyKeys())->toBe([
            "booking_{$booking->id}_charge_0",
            "booking_{$booking->id}_charge_0",
        ]);
    });

    test('tip charge sends a booking-and-amount-scoped idempotency key', function () {
        $stripe = FakeStripeHttpClient::install();
        $booking = billingBooking();

        $result = (new TipChargeService)->charge($booking, 25.00);

        expect($result['success'])->toBeTrue();
        expect($stripe->idempotencyKeys())->toContain("booking_{$booking->id}_tip_2500");
    });

    test('caregiver transfer sends the caller-provided idempotency key', function () {
        $stripe = FakeStripeHttpClient::install();
        test()->seed(SpecialtyTypeSeeder::class);
        test()->seed(LocationSeeder::class);
        test()->seed(AttributeDefinitionSeeder::class);
        test()->seed(CertificationTypeSeeder::class);
        $caregiver = Caregiver::factory()->create(['stripe_account_id' => 'acct_fake']);
        CaregiverPayoutMethod::factory()->create([
            'caregiver_id' => $caregiver->id,
            'is_default' => true,
            'status' => 'active',
        ]);

        $result = (new CaregiverPayoutService)->transferFunds(
            $caregiver,
            5000,
            idempotencyKey: 'booking_42_payout_5000'
        );

        expect($result['success'])->toBeTrue();
        expect($stripe->idempotencyKeys())->toBe(['booking_42_payout_5000']);
    });
});
