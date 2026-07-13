<?php

use App\Models\Booking;
use App\Models\BookingGroup;
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
});

describe('Booking::toEmailData() dates', function () {
    test('a multi-day group lists every day in chronological order', function () {
        $group = BookingGroup::factory()->create();

        // Create the days out of order to prove the method sorts them.
        $day3 = Booking::factory()->for($group, 'bookingGroup')->create([
            'start_datetime' => '2026-07-15 09:00:00',
            'end_datetime' => '2026-07-15 17:00:00',
        ]);
        $day1 = Booking::factory()->for($group, 'bookingGroup')->create([
            'start_datetime' => '2026-07-13 09:00:00',
            'end_datetime' => '2026-07-13 17:00:00',
        ]);
        $day2 = Booking::factory()->for($group, 'bookingGroup')->create([
            'start_datetime' => '2026-07-14 09:00:00',
            'end_datetime' => '2026-07-14 17:00:00',
        ]);

        // Calling on any sibling must surface the whole group.
        $data = $day2->fresh()->toEmailData();

        expect($data['is_multi_day'])->toBeTrue()
            ->and($data['dates'])->toHaveCount(3)
            ->and(array_column($data['dates'], 'date'))->toBe([
                'Monday, July 13, 2026',
                'Tuesday, July 14, 2026',
                'Wednesday, July 15, 2026',
            ]);
    });

    test('a single-day booking has one date entry and is not multi-day', function () {
        // Naive strings are interpreted as America/Los_Angeles wall-clock and
        // stored UTC, so this is a 9 AM - 5 PM LA day.
        $booking = Booking::factory()->create([
            'start_datetime' => '2026-07-13 09:00:00',
            'end_datetime' => '2026-07-13 17:00:00',
        ]);

        $data = $booking->fresh()->toEmailData();

        expect($data['is_multi_day'])->toBeFalse()
            ->and($data['dates'])->toHaveCount(1)
            ->and($data['dates'][0])->toBe([
                'date' => 'Monday, July 13, 2026',
                'start_time' => '9:00 AM',
                'end_time' => '5:00 PM',
            ]);
    });
});
