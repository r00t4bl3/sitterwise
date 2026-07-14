<?php

use App\Models\Booking;
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
});

/**
 * total_working_hour is the TRUE elapsed time (diffInMinutes on the stored UTC
 * instants), NOT the wall-clock span. Across a DST transition the two differ by
 * an hour — this is correct, and these tests lock it. See docs/datetime-and-dst.md.
 *
 * The naive datetimes below are interpreted as America/Los_Angeles by the model
 * setter (Booking::convertToUtc) and are all outside the ambiguous/gap window, so
 * the conversion is deterministic.
 */
describe('Booking duration across DST transitions', function () {
    test('spring-forward: a booking that reads 4h on the calendar is the true 3h elapsed', function () {
        // Mar 9 2025, clocks jump 2:00 -> 3:00 AM PT (an hour is skipped).
        // 01:00 PST = 09:00 UTC; 05:00 PDT = 12:00 UTC => 3 real hours.
        $booking = Booking::factory()->create([
            'start_datetime' => '2025-03-09 01:00:00',
            'end_datetime' => '2025-03-09 05:00:00',
        ]);

        expect((float) $booking->total_working_hour)->toBe(3.0);
    });

    test('fall-back: a booking that reads 4h on the calendar is the true 5h elapsed', function () {
        // Nov 2 2025, clocks fall 2:00 -> 1:00 AM PT (an hour repeats).
        // 00:00 PDT = 07:00 UTC; 04:00 PST = 12:00 UTC => 5 real hours.
        $booking = Booking::factory()->create([
            'start_datetime' => '2025-11-02 00:00:00',
            'end_datetime' => '2025-11-02 04:00:00',
        ]);

        expect((float) $booking->total_working_hour)->toBe(5.0);
    });

    test('control: a same-day daytime booking with no transition is a plain 4h', function () {
        $booking = Booking::factory()->create([
            'start_datetime' => '2025-06-15 09:00:00',
            'end_datetime' => '2025-06-15 13:00:00',
        ]);

        expect((float) $booking->total_working_hour)->toBe(4.0);
    });
});
