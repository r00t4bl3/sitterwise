<?php

use App\Enums\BookingStatus;
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
