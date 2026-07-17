<?php

use App\Models\Booking;
use App\Models\Client;
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
        SettingsSeeder::class,
        PricingRulesTableSeeder::class,
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->client = Client::factory()->create();
});

test('a Lifesaver rescue adds the $15 bonus to caregiver pay AND the client charge, leaving the Sitterwise cut unchanged', function () {
    $booking = Booking::factory()->forClient($this->client)->completed()->create([
        'total_working_hour' => 4,
    ]);

    // Baseline: not a Lifesaver.
    $booking->lifesaver_override = false;
    $booking->calculateTotalAmount();
    $basePaid = (float) $booking->paid_to_caregiver_total;
    $baseService = (float) $booking->total_service_amount;
    $baseCut = (float) $booking->sitterwise_cut;

    // Flagged a Lifesaver -> +$15, billed to client and paid to caregiver.
    $booking->lifesaver_override = true;
    $booking->calculateTotalAmount();

    expect((float) $booking->paid_to_caregiver_total - $basePaid)->toBe(15.0);
    expect((float) $booking->total_service_amount - $baseService)->toBe(15.0);
    expect((float) $booking->sitterwise_cut)->toBe($baseCut);
});

test('a non-Lifesaver booking gets no bonus added to caregiver pay', function () {
    $booking = Booking::factory()->forClient($this->client)->completed()->create([
        'total_working_hour' => 4,
        'lifesaver_override' => false,
    ]);

    $booking->calculateTotalAmount();

    // With no reimbursement/bonus/tip and not a Lifesaver, the payout total equals
    // the plain hourly caregiver pay — no $15 added.
    expect((float) $booking->paid_to_caregiver_total)
        ->toBe((float) $booking->paid_to_caregiver);
});
