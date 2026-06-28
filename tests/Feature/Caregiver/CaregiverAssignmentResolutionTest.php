<?php

use App\Enums\AssignmentResolution;
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
    $this->booking = Booking::factory()->forClient($this->client)->create([
        'caregiver_id' => $this->caregiver->id,
        'status' => BookingStatus::Confirmed,
        'pricing_rule_id' => PricingRule::first()?->id,
    ]);
    $this->assignment = $this->booking->assignments()->firstOrCreate([
        'caregiver_id' => $this->caregiver->id,
        'assigned_at' => now(),
    ]);
});

describe('checkout resolves assignment as completed', function () {
    it('marks assignment as completed on successful checkout', function () {
        $this->actingAs($this->caregiver->user)
            ->post(route('jobs.checkout', $this->booking), [
                'start_datetime' => '2026-05-28T09:00',
                'end_datetime' => '2026-05-28T17:00',
            ])
            ->assertSessionHas('success');

        $this->assignment->refresh();
        expect($this->assignment->resolution)->toBe(AssignmentResolution::Completed->value);
        expect($this->assignment->resolution_at)->not->toBeNull();
    });

    it('sets booking status to completed on checkout', function () {
        $this->actingAs($this->caregiver->user)
            ->post(route('jobs.checkout', $this->booking), [
                'start_datetime' => '2026-05-28T09:00',
                'end_datetime' => '2026-05-28T17:00',
            ]);

        $this->booking->refresh();
        expect($this->booking->status)->toBe(BookingStatus::Completed->value);
        expect($this->booking->checkout_at)->not->toBeNull();
    });

    it('rejects checkout by another caregiver', function () {
        $other = Caregiver::factory()->create();

        $this->actingAs($other->user)
            ->post(route('jobs.checkout', $this->booking), [
                'start_datetime' => '2026-05-28T09:00',
                'end_datetime' => '2026-05-28T17:00',
            ])
            ->assertStatus(403);
    });

    it('requires start and end datetime for checkout', function () {
        $this->actingAs($this->caregiver->user)
            ->post(route('jobs.checkout', $this->booking), [])
            ->assertSessionHasErrors(['start_datetime', 'end_datetime']);
    });

    it('requires end datetime to be after start datetime', function () {
        $this->actingAs($this->caregiver->user)
            ->post(route('jobs.checkout', $this->booking), [
                'start_datetime' => '2026-05-28T17:00',
                'end_datetime' => '2026-05-28T09:00',
            ])
            ->assertSessionHasErrors('end_datetime');
    });

    it('stores reimbursement and bonus on checkout', function () {
        $this->actingAs($this->caregiver->user)
            ->post(route('jobs.checkout', $this->booking), [
                'start_datetime' => '2026-05-28T09:00',
                'end_datetime' => '2026-05-28T17:00',
                'reimbursement' => 25.50,
                'reimbursement_description' => 'Parking',
                'bonus' => 50.00,
            ]);

        $this->booking->refresh();
        expect((float) $this->booking->reimbursement)->toBe(25.5);
        expect($this->booking->reimbursement_description)->toBe('Parking');
        expect((float) $this->booking->bonus)->toBe(50.0);
    });
});

describe('checkout calculates paid_to_caregiver_total', function () {
    beforeEach(function () {
        $this->booking = Booking::factory()->forClient($this->client)->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->hourlyRate = (float) $this->booking->paid_to_caregiver_hourly;
        $this->chargeRate = (float) $this->booking->charge_to_client_hourly;

        $this->booking->assignments()->firstOrCreate([
            'caregiver_id' => $this->caregiver->id,
            'assigned_at' => now(),
        ]);
    });

    it('computes paid_to_caregiver_total from hours and reimbursements', function () {
        $this->actingAs($this->caregiver->user)
            ->post(route('jobs.checkout', $this->booking), [
                'start_datetime' => '2026-05-28T09:00',
                'end_datetime' => '2026-05-28T17:00',
                'reimbursement' => 15.00,
                'bonus' => 10.00,
            ]);

        $this->booking->refresh();

        $expectedCaregiver = $this->hourlyRate * 8;
        $expectedTotal = $expectedCaregiver + 15.00 + 10.00;
        $expectedService = ($this->chargeRate * 8) + 15.00 + 10.00;

        expect((float) $this->booking->paid_to_caregiver)->toBe($expectedCaregiver);
        expect((float) $this->booking->paid_to_caregiver_total)->toBe($expectedTotal);
        expect((float) $this->booking->total_service_amount)->toBe($expectedService);
    });
});

describe('assignment resolve model method', function () {
    it('can resolve with completed', function () {
        $this->assignment->resolve(AssignmentResolution::Completed);
        $this->assignment->refresh();
        expect($this->assignment->resolution)->toBe(AssignmentResolution::Completed->value);
        expect($this->assignment->resolution_at)->not->toBeNull();
    });

    it('can resolve with no_show', function () {
        $this->assignment->resolve(AssignmentResolution::NoShow, 'Never arrived');
        $this->assignment->refresh();
        expect($this->assignment->resolution)->toBe(AssignmentResolution::NoShow->value);
        expect($this->assignment->resolution_note)->toBe('Never arrived');
    });

    it('can resolve with backed_out', function () {
        $this->assignment->resolve(AssignmentResolution::BackedOut, 'Family emergency');
        $this->assignment->refresh();
        expect($this->assignment->resolution)->toBe(AssignmentResolution::BackedOut->value);
        expect($this->assignment->resolution_note)->toBe('Family emergency');
    });

    it('can resolve with backed_out_excused', function () {
        $this->assignment->resolve(AssignmentResolution::BackedOutExcused, 'Excused by admin');
        $this->assignment->refresh();
        expect($this->assignment->resolution)->toBe(AssignmentResolution::BackedOutExcused->value);
    });

    it('can resolve with cancelled_by_sitterwise', function () {
        $this->assignment->resolve(AssignmentResolution::CancelledBySitterwise, 'Admin decision');
        $this->assignment->refresh();
        expect($this->assignment->resolution)->toBe(AssignmentResolution::CancelledBySitterwise->value);
    });

    it('can resolve with reassigned', function () {
        $this->assignment->resolve(AssignmentResolution::Reassigned, 'Replaced by another caregiver');
        $this->assignment->refresh();
        expect($this->assignment->resolution)->toBe(AssignmentResolution::Reassigned->value);
    });

    it('stores resolution note when provided', function () {
        $this->assignment->resolve(AssignmentResolution::Completed, 'Great job!');
        $this->assignment->refresh();
        expect($this->assignment->resolution_note)->toBe('Great job!');
    });

    it('preserves existing note when not overridden', function () {
        $this->assignment->update(['resolution_note' => 'Existing note']);
        $this->assignment->resolve(AssignmentResolution::Completed);
        $this->assignment->refresh();
        expect($this->assignment->resolution_note)->toBe('Existing note');
    });
});
