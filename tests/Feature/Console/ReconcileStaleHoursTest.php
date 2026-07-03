<?php

use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\PricingRule;
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
    $this->caregiver = Caregiver::factory()->create();
});

function staleBooking($client, $caregiver, array $overrides = []): Booking
{
    $booking = Booking::factory()
        ->forClient($client)
        ->withBookingGroup(fn ($g) => $g->state(['service_type' => 'babysitter']))
        ->create(array_merge([
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
            'checkout_at' => now(),
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T17:00:00Z',
            'pricing_rule_id' => PricingRule::first()?->id,
        ], $overrides));

    // Simulate imported stale data.
    $booking->updateQuietly(['total_working_hour' => 0, 'total_service_amount' => 0, 'total_amount' => 0]);

    return $booking;
}

it('recomputes hours and totals with --apply', function () {
    $booking = staleBooking($this->client, $this->caregiver);

    $this->artisan('app:reconcile-stale-hours', ['--apply' => true])->assertExitCode(0);

    $booking->refresh();
    expect((float) $booking->total_working_hour)->toBe(8.0);
    expect((float) $booking->total_service_amount)->toBeGreaterThan(0.0);
});

it('changes nothing on a dry run', function () {
    $booking = staleBooking($this->client, $this->caregiver);

    $this->artisan('app:reconcile-stale-hours')->assertExitCode(0);

    $booking->refresh();
    expect((float) $booking->total_working_hour)->toBe(0.0);
    expect((float) $booking->total_service_amount)->toBe(0.0);
});

it('never touches an already-charged booking', function () {
    $booking = staleBooking($this->client, $this->caregiver, ['payment_status' => 'charged']);

    $this->artisan('app:reconcile-stale-hours', ['--apply' => true])->assertExitCode(0);

    $booking->refresh();
    expect((float) $booking->total_working_hour)->toBe(0.0);
});

it('is idempotent — a second run finds nothing to change', function () {
    staleBooking($this->client, $this->caregiver);

    $this->artisan('app:reconcile-stale-hours', ['--apply' => true]);
    // Second run: the row now has hours > 0, so it should no longer match.
    $this->artisan('app:reconcile-stale-hours', ['--apply' => true])
        ->expectsOutputToContain('0 booking(s) matched')
        ->assertExitCode(0);
});
