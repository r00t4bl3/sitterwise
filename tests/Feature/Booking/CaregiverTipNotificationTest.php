<?php

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientPaymentMethod;
use App\Models\PricingRule;
use App\Notifications\CaregiverTipReceivedNotification;
use App\Services\Billing\TipChargeService;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Stripe\Exception\ApiConnectionException;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
});

/**
 * A StripeClient double whose paymentIntents->create either succeeds or throws,
 * so TipChargeService::charge() runs its real success/failure branches without
 * touching Stripe.
 */
function mockStripeForTip(bool $succeeds = true): StripeClient
{
    $paymentIntents = Mockery::mock();

    if ($succeeds) {
        $paymentIntents->shouldReceive('create')->andReturn((object) ['id' => 'pi_test_123']);
    } else {
        $paymentIntents->shouldReceive('create')->andThrow(
            new ApiConnectionException('Simulated network failure')
        );
    }

    $stripe = Mockery::mock(StripeClient::class);
    $stripe->shouldReceive('getService')->with('paymentIntents')->andReturn($paymentIntents);
    $stripe->shouldReceive('getService')->andReturn(Mockery::mock()->shouldIgnoreMissing());

    return $stripe;
}

/**
 * @return array{0: Booking, 1: Caregiver}
 */
function tipNotificationBooking(): array
{
    $client = Client::factory()->create(['stripe_customer_id' => 'cus_'.uniqid()]);
    $caregiver = Caregiver::factory()->create();

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
        'caregiver_id' => $caregiver->id,
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
    ]);

    ClientPaymentMethod::create([
        'client_id' => $client->id,
        'provider_method_id' => 'pm_default_method',
        'provider' => 'stripe',
        'brand' => 'visa',
        'last4' => '4242',
        'exp_month' => 12,
        'exp_year' => 2030,
        'is_default' => true,
        'status' => 'active',
    ]);

    return [$booking, $caregiver];
}

test('a successful tip charge notifies the caregiver in-app', function () {
    Notification::fake();
    [$booking, $caregiver] = tipNotificationBooking();

    $result = (new TipChargeService(mockStripeForTip(true)))
        ->charge($booking, 20, 'pm_default_method');

    expect($result['success'])->toBeTrue();

    Notification::assertSentTo(
        $caregiver->user,
        CaregiverTipReceivedNotification::class,
        fn ($notification) => $notification->booking->id === $booking->id
            && (float) $notification->tipAmount === 20.0
    );
});

test('the tip notification is in-app (database) only', function () {
    Notification::fake();
    [$booking, $caregiver] = tipNotificationBooking();

    (new TipChargeService(mockStripeForTip(true)))->charge($booking, 20, 'pm_default_method');

    Notification::assertSentTo(
        $caregiver->user,
        CaregiverTipReceivedNotification::class,
        fn ($notification, $channels) => $channels === ['database']
    );
});

test('a failed tip charge does not notify the caregiver', function () {
    Notification::fake();
    [$booking, $caregiver] = tipNotificationBooking();

    $result = (new TipChargeService(mockStripeForTip(false)))
        ->charge($booking, 20, 'pm_default_method');

    expect($result['success'])->toBeFalse();

    Notification::assertNothingSentTo($caregiver->user);
});
