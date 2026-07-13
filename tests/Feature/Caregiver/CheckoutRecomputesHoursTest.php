<?php

use App\Enums\AssignmentResolution;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\PricingRule;
use App\Support\Settings;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\PricingRulesTableSeeder;
use Database\Seeders\SettingsSeeder;
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

it('recomputes worked hours on checkout even when the times are unchanged (prevents a $0 charge)', function () {
    $booking = Booking::factory()
        ->forClient($this->client)
        ->withBookingGroup(fn ($g) => $g->state(['service_type' => 'babysitter']))
        ->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Confirmed,
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T17:00:00Z',
            'pricing_rule_id' => PricingRule::first()?->id,
        ]);

    // Simulate an imported booking whose hours/total were never calculated
    // (import bulk-inserts, bypassing the model's hours calculation).
    $booking->updateQuietly([
        'total_working_hour' => 0,
        'total_service_amount' => 0,
        'total_amount' => 0,
    ]);

    $booking->assignments()->firstOrCreate([
        'caregiver_id' => $this->caregiver->id,
        'assigned_at' => now(),
    ]);

    // Caregiver checks out confirming the SAME times without editing them.
    $this->actingAs($this->caregiver->user)
        ->post(route('jobs.checkout', $booking), [
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T17:00:00Z',
        ])
        ->assertSessionHasNoErrors();

    $booking->refresh();

    // 09:00 -> 17:00 is 8 hours; total must no longer be $0.
    expect((float) $booking->total_working_hour)->toBe(8.0);
    expect((float) $booking->total_service_amount)->toBeGreaterThan(0.0);
    expect($booking->status)->toBe(BookingStatus::Completed->value);
});

it('saves the true sub-4h worked time but bills (and pays) a 4-hour minimum', function () {
    $booking = Booking::factory()
        ->forClient($this->client)
        ->withBookingGroup(fn ($g) => $g->state(['service_type' => 'babysitter']))
        ->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Confirmed,
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T17:00:00Z',
            'pricing_rule_id' => PricingRule::first()?->id,
        ]);

    $booking->assignments()->firstOrCreate([
        'caregiver_id' => $this->caregiver->id,
        'assigned_at' => now(),
    ]);

    // Caregiver actually worked only 2 hours (09:00 -> 11:00) — now allowed.
    $this->actingAs($this->caregiver->user)
        ->post(route('jobs.checkout', $booking), [
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T11:00:00Z',
        ])
        ->assertSessionHasNoErrors();

    $booking->refresh();

    // True elapsed time is stored…
    expect((float) $booking->total_working_hour)->toBe(2.0)
        // …but the money is floored to a 4-hour minimum.
        ->and((float) $booking->charge_to_client)
        ->toBe(round((float) $booking->charge_to_client_hourly * 4, 2))
        ->and((float) $booking->paid_to_caregiver)
        ->toBe(round((float) $booking->paid_to_caregiver_hourly * 4, 2))
        ->and((float) $booking->total_service_amount)
        ->toBe(round((float) $booking->charge_to_client_hourly * 4, 2));
});

it('floors billing at the configurable minimum-hours setting, not a hardcoded 4', function () {
    $this->seed(SettingsSeeder::class);
    Settings::set('bookings.minimum_hours', 3);

    $booking = Booking::factory()
        ->forClient($this->client)
        ->withBookingGroup(fn ($g) => $g->state(['service_type' => 'babysitter']))
        ->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Confirmed,
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T17:00:00Z',
            'pricing_rule_id' => PricingRule::first()?->id,
        ]);

    $booking->assignments()->firstOrCreate([
        'caregiver_id' => $this->caregiver->id,
        'assigned_at' => now(),
    ]);

    // Worked 2 hours; the floor is now 3 (from the setting), not 4.
    $this->actingAs($this->caregiver->user)
        ->post(route('jobs.checkout', $booking), [
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T11:00:00Z',
        ])
        ->assertSessionHasNoErrors();

    $booking->refresh();

    expect((float) $booking->total_working_hour)->toBe(2.0)
        ->and((float) $booking->charge_to_client)
        ->toBe(round((float) $booking->charge_to_client_hourly * 3, 2));
});

it('does not over-apply the floor: a >4h checkout bills the true hours', function () {
    $booking = Booking::factory()
        ->forClient($this->client)
        ->withBookingGroup(fn ($g) => $g->state(['service_type' => 'babysitter']))
        ->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Confirmed,
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T17:00:00Z',
            'pricing_rule_id' => PricingRule::first()?->id,
        ]);

    $booking->assignments()->firstOrCreate([
        'caregiver_id' => $this->caregiver->id,
        'assigned_at' => now(),
    ]);

    // 09:00 -> 14:00 = 5 hours (above the floor).
    $this->actingAs($this->caregiver->user)
        ->post(route('jobs.checkout', $booking), [
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T14:00:00Z',
        ])
        ->assertSessionHasNoErrors();

    $booking->refresh();

    expect((float) $booking->total_working_hour)->toBe(5.0)
        ->and((float) $booking->charge_to_client)
        ->toBe(round((float) $booking->charge_to_client_hourly * 5, 2));
});

it('floors billing when an admin adjusts a completed booking to a sub-4h window', function () {
    $booking = Booking::factory()
        ->forClient($this->client)
        ->withBookingGroup(fn ($g) => $g->state(['service_type' => 'babysitter']))
        ->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Confirmed,
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T17:00:00Z',
            'pricing_rule_id' => PricingRule::first()?->id,
        ]);

    // Admin completes/adjusts a finished job to a true 3-hour window; the model
    // hook recomputes hours (start/end dirty) and the amount (status Completed).
    $booking->update([
        'start_datetime' => '2026-05-28T09:00:00Z',
        'end_datetime' => '2026-05-28T12:00:00Z', // 3 hours
        'status' => BookingStatus::Completed->value,
    ]);

    $booking->refresh();

    expect((float) $booking->total_working_hour)->toBe(3.0)
        ->and((float) $booking->charge_to_client)
        ->toBe(round((float) $booking->charge_to_client_hourly * 4, 2));
});

it('still records correct hours when the caregiver edits the times at checkout', function () {
    $booking = Booking::factory()
        ->forClient($this->client)
        ->withBookingGroup(fn ($g) => $g->state(['service_type' => 'babysitter']))
        ->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Confirmed,
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T17:00:00Z',
            'pricing_rule_id' => PricingRule::first()?->id,
        ]);

    $booking->assignments()->firstOrCreate([
        'caregiver_id' => $this->caregiver->id,
        'assigned_at' => now(),
    ]);

    // Caregiver actually finished early: 09:00 -> 14:00 = 5 hours.
    $this->actingAs($this->caregiver->user)
        ->post(route('jobs.checkout', $booking), [
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T14:00:00Z',
        ])
        ->assertSessionHasNoErrors();

    $booking->refresh();
    expect((float) $booking->total_working_hour)->toBe(5.0);
});

it('creates and completes the assignment row on checkout when the caregiver never had one', function () {
    // Reproduces the #85 gap: the caregiver was set on the booking via a path
    // that left no assignment row. Checkout must self-heal (firstOrCreate) so
    // the completion is recorded instead of silently no-oping.
    $booking = Booking::factory()
        ->forClient($this->client)
        ->withBookingGroup(fn ($g) => $g->state(['service_type' => 'babysitter']))
        ->create([
            'caregiver_id' => null,
            'status' => BookingStatus::Confirmed,
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T17:00:00Z',
            'pricing_rule_id' => PricingRule::first()?->id,
        ]);

    // updateQuietly bypasses the saved-hook, so no assignment row is created.
    $booking->updateQuietly(['caregiver_id' => $this->caregiver->id]);
    expect($booking->assignments()->count())->toBe(0);

    $this->actingAs($this->caregiver->user)
        ->post(route('jobs.checkout', $booking), [
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T17:00:00Z',
        ])
        ->assertSessionHasNoErrors();

    $assignment = $booking->assignments()->where('caregiver_id', $this->caregiver->id)->first();
    expect($assignment)->not->toBeNull()
        ->and($assignment->resolution)->toBe(AssignmentResolution::Completed->value);
    expect($booking->assignments()->count())->toBe(1);
});

it('resolves the existing assignment row on checkout without creating a duplicate', function () {
    // Negative path: when the row already exists, checkout resolves it in place.
    $booking = Booking::factory()
        ->forClient($this->client)
        ->withBookingGroup(fn ($g) => $g->state(['service_type' => 'babysitter']))
        ->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Confirmed,
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T17:00:00Z',
            'pricing_rule_id' => PricingRule::first()?->id,
        ]);

    // The saved-hook created exactly one unresolved row on assignment.
    expect($booking->assignments()->where('caregiver_id', $this->caregiver->id)->count())->toBe(1);

    $this->actingAs($this->caregiver->user)
        ->post(route('jobs.checkout', $booking), [
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T17:00:00Z',
        ])
        ->assertSessionHasNoErrors();

    $rows = $booking->assignments()->where('caregiver_id', $this->caregiver->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->resolution)->toBe(AssignmentResolution::Completed->value);
});

it('rejects a checkout whose end time is at or before the start time', function () {
    $booking = Booking::factory()
        ->forClient($this->client)
        ->withBookingGroup(fn ($g) => $g->state(['service_type' => 'babysitter']))
        ->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Confirmed,
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T17:00:00Z',
            'pricing_rule_id' => PricingRule::first()?->id,
        ]);

    $booking->assignments()->firstOrCreate([
        'caregiver_id' => $this->caregiver->id,
        'assigned_at' => now(),
    ]);

    // An end that is not after the start is invalid — the job must not complete.
    $this->actingAs($this->caregiver->user)
        ->post(route('jobs.checkout', $booking), [
            'start_datetime' => '2026-05-28T09:00:00Z',
            'end_datetime' => '2026-05-28T09:00:00Z',
        ])
        ->assertSessionHasErrors('end_datetime');

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Confirmed->value)
        ->and($booking->checkout_at)->toBeNull();
});
