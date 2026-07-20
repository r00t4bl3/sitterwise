<?php

use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\PricingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pricingRuleWithForm(string $paymentForm): void
{
    PricingRule::create([
        'service_type' => ServiceType::Babysitter->value,
        'number_of_children' => 0,
        'is_for_pets' => false,
        'charge_to_client' => 100,
        'paid_to_caregiver' => 70,
        'sitterwise_cut' => 30,
        'payment_form' => $paymentForm,
    ]);
}

test('audit report flags a Stripe booking whose charge PI is actually a tip, and mutates nothing', function () {
    pricingRuleWithForm('Stripe');
    $client = Client::factory()->create();
    $tipPiId = 'pi_tip_'.uniqid();

    // Corrupted state: a tip PI recorded as the booking's charge, no service payment.
    $booking = Booking::factory()->forClient($client)->create([
        'status' => 'completed',
        'payment_status' => 'charged',
        'stripe_payment_intent_id' => $tipPiId,
        'actual_amount' => 20,
    ]);

    ClientPayment::create([
        'booking_id' => $booking->id,
        'client_id' => $client->id,
        'amount' => 20,
        'currency' => 'usd',
        'status' => 'succeeded',
        'provider' => 'stripe',
        'provider_payment_id' => $tipPiId,
        'metadata' => ['type' => 'tip', 'booking_id' => $booking->id],
    ]);

    $this->artisan('payments:audit-tip-charges')
        ->expectsOutputToContain('SERVICE_NEVER_CHARGED')
        ->assertExitCode(0);

    // Read-only: nothing changed.
    $booking->refresh();
    expect($booking->payment_status)->toBe('charged');
    expect($booking->status)->toBe('completed');
});

test('audit report is clean when no tip PI is mis-recorded as a charge', function () {
    $client = Client::factory()->create();
    Booking::factory()->forClient($client)->create([
        'status' => 'paid',
        'payment_status' => 'charged',
        'stripe_payment_intent_id' => 'pi_service_'.uniqid(),
    ]);

    $this->artisan('payments:audit-tip-charges')
        ->expectsOutputToContain('Nothing to reconcile')
        ->assertExitCode(0);
});

/** A mislabeled booking: service genuinely charged, but the denormalized fields point at the tip. */
function mislabeledBooking(): array
{
    $client = Client::factory()->create();
    $servicePi = 'pi_service_'.uniqid();
    $tipPi = 'pi_tip_'.uniqid();

    $booking = Booking::factory()->forClient($client)->create([
        'status' => 'paid',
        'payment_status' => 'charged',
        'stripe_payment_intent_id' => $tipPi,   // wrong: the tip's PI
        'actual_amount' => 40,                  // wrong: the tip amount
    ]);

    ClientPayment::create([
        'booking_id' => $booking->id, 'client_id' => $client->id,
        'amount' => 172, 'currency' => 'usd', 'status' => 'succeeded',
        'provider' => 'stripe', 'provider_payment_id' => $servicePi,
        'metadata' => ['base_amount' => 172], // no type => service
    ]);
    ClientPayment::create([
        'booking_id' => $booking->id, 'client_id' => $client->id,
        'amount' => 40, 'currency' => 'usd', 'status' => 'succeeded',
        'provider' => 'stripe', 'provider_payment_id' => $tipPi,
        'metadata' => ['type' => 'tip'],
    ]);

    return [$booking, $servicePi];
}

test('--apply repoints a mislabeled booking to its real service charge', function () {
    [$booking, $servicePi] = mislabeledBooking();

    $this->artisan('payments:audit-tip-charges --apply')->assertExitCode(0);

    $booking->refresh();
    expect($booking->stripe_payment_intent_id)->toBe($servicePi);
    expect((float) $booking->actual_amount)->toBe(172.0);
    // Status/payment_status were already correct — untouched.
    expect($booking->payment_status)->toBe('charged');
    expect($booking->status)->toBe('paid');
});

test('--apply is idempotent (a repointed booking drops out of the candidate set)', function () {
    mislabeledBooking();

    $this->artisan('payments:audit-tip-charges --apply')->assertExitCode(0);
    $this->artisan('payments:audit-tip-charges --apply')
        ->expectsOutputToContain('Nothing to reconcile')
        ->assertExitCode(0);
});

test('--apply never touches a Stripe never-charged (money-at-risk) booking', function () {
    pricingRuleWithForm('Stripe');
    $client = Client::factory()->create();
    $tipPi = 'pi_tip_'.uniqid();

    $booking = Booking::factory()->forClient($client)->create([
        'status' => 'completed',
        'payment_status' => 'charged',
        'stripe_payment_intent_id' => $tipPi,
        'actual_amount' => 20,
    ]);
    ClientPayment::create([
        'booking_id' => $booking->id, 'client_id' => $client->id,
        'amount' => 20, 'currency' => 'usd', 'status' => 'succeeded',
        'provider' => 'stripe', 'provider_payment_id' => $tipPi,
        'metadata' => ['type' => 'tip'],
    ]);

    $this->artisan('payments:audit-tip-charges --apply')
        ->expectsOutputToContain('SERVICE_NEVER_CHARGED')
        ->assertExitCode(0);

    $booking->refresh();
    expect($booking->stripe_payment_intent_id)->toBe($tipPi); // unchanged
    expect($booking->payment_status)->toBe('charged');
});

test('a non-Stripe (payroll) booking corrupted by a tip is NON_STRIPE_OK and untouched by --apply', function () {
    pricingRuleWithForm('OnPay (Payroll)');
    $client = Client::factory()->create();
    $tipPi = 'pi_tip_'.uniqid();

    $booking = Booking::factory()->forClient($client)->create([
        'status' => 'completed',
        'payment_status' => 'charged',
        'stripe_payment_intent_id' => $tipPi,
        'actual_amount' => 20,
    ]);
    ClientPayment::create([
        'booking_id' => $booking->id, 'client_id' => $client->id,
        'amount' => 20, 'currency' => 'usd', 'status' => 'succeeded',
        'provider' => 'stripe', 'provider_payment_id' => $tipPi,
        'metadata' => ['type' => 'tip'],
    ]);

    $this->artisan('payments:audit-tip-charges --apply')
        ->expectsOutputToContain('NON_STRIPE_OK')
        ->assertExitCode(0);

    $booking->refresh();
    expect($booking->stripe_payment_intent_id)->toBe($tipPi); // untouched
    expect($booking->payment_status)->toBe('charged');
});
