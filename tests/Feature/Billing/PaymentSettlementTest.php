<?php

use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Client;
use App\Models\PricingRule;
use App\Services\Billing\JobBillingService;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\PricingRulesTableSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        PricingRulesTableSeeder::class,
        SpecialtyTypeSeeder::class,
        CertificationTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->client = Client::factory()->create();
});

describe('pricing-rule derivation', function () {
    it('derives requires_payment from charge_to_client (> 0 is billable)', function () {
        expect(PricingRule::requiresPaymentFor('babysitter'))->toBeTrue();
        expect(PricingRule::requiresPaymentFor('corporate_invoiced'))->toBeTrue();
        expect(PricingRule::requiresPaymentFor('group_childcare_invoiced'))->toBeTrue();
        expect(PricingRule::requiresPaymentFor('comped'))->toBeFalse();
    });

    it('resolves the settlement rail from payment_form', function () {
        expect(PricingRule::paymentFormFor('babysitter'))->toBe('Stripe');
        expect(PricingRule::paymentFormFor('corporate_invoiced'))->toBe('OnPay (Payroll)');
    });

    it('snapshots payment_form onto the booking group on save', function () {
        $stripe = BookingGroup::factory()->create(['service_type' => 'babysitter', 'client_id' => $this->client->id]);
        $onpay = BookingGroup::factory()->create(['service_type' => 'corporate_invoiced', 'client_id' => $this->client->id]);

        expect($stripe->payment_form)->toBe('Stripe');
        expect($onpay->payment_form)->toBe('OnPay (Payroll)');
    });
});

describe('Stripe charge gate', function () {
    it('refuses to card-charge an OnPay (invoiced) booking', function () {
        $booking = Booking::factory()
            ->withBookingGroup(fn ($g) => $g->state([
                'client_id' => $this->client->id,
                'service_type' => 'corporate_invoiced',
                'requires_payment' => true,
            ]))
            ->create(['status' => 'completed', 'total_service_amount' => 200]);

        expect($booking->payment_form)->toBe('OnPay (Payroll)');
        expect($booking->requires_payment)->toBeTrue();

        $result = app(JobBillingService::class)->charge($booking);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('not a card charge');
    });

    it('treats comped as non-billable', function () {
        $booking = Booking::factory()
            ->withBookingGroup(fn ($g) => $g->state([
                'client_id' => $this->client->id,
                'service_type' => 'comped',
                'requires_payment' => false,
            ]))
            ->create(['status' => 'completed']);

        $result = app(JobBillingService::class)->charge($booking);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('does not require payment');
    });
});

describe('backfill command', function () {
    it('fixes legacy corporate groups and is idempotent', function () {
        $group = BookingGroup::factory()->create(['service_type' => 'corporate_invoiced', 'client_id' => $this->client->id]);
        // Simulate legacy state: not billable, no rail.
        $group->requires_payment = false;
        $group->payment_form = null;
        $group->saveQuietly();

        $this->artisan('app:backfill-payment-settlement', ['--apply' => true])->assertExitCode(0);

        $group->refresh();
        expect($group->requires_payment)->toBeTrue();
        expect($group->payment_form)->toBe('OnPay (Payroll)');

        // Second run: idempotent — nothing left to change.
        $this->artisan('app:backfill-payment-settlement', ['--apply' => true])
            ->expectsOutputToContain('Updated 0 group(s)')
            ->assertExitCode(0);
    });
});
