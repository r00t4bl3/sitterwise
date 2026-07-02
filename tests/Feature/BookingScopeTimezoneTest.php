<?php

use App\Models\Booking;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Freeze at 7pm Pacific, which is already the next calendar day in UTC — the
 * exact condition where UTC-based "today" boundaries drift a day.
 */
function freezePacificEvening(): void
{
    // 2026-07-02 19:00 Pacific (PDT, -7) == 2026-07-03 02:00 UTC
    Carbon::setTestNow(Carbon::parse('2026-07-03 02:00:00', 'UTC'));
}

describe('Booking timezone-aware scopes (#150)', function () {
    test('inToday uses the Pacific day, not the UTC day', function () {
        freezePacificEvening();
        $client = Client::factory()->create();

        $todayPacific = Booking::factory()->forClient($client)->create([
            'start_datetime' => Carbon::parse('2026-07-02 10:00', 'America/Los_Angeles'),
            'end_datetime' => Carbon::parse('2026-07-02 14:00', 'America/Los_Angeles'),
        ]);
        $tomorrowPacific = Booking::factory()->forClient($client)->create([
            'start_datetime' => Carbon::parse('2026-07-03 10:00', 'America/Los_Angeles'),
            'end_datetime' => Carbon::parse('2026-07-03 14:00', 'America/Los_Angeles'),
        ]);

        $ids = Booking::inToday()->pluck('id');

        expect($ids)->toContain($todayPacific->id)
            ->and($ids)->not->toContain($tomorrowPacific->id);
    });

    test('inFuture counts from the start of the Pacific day', function () {
        freezePacificEvening();
        $client = Client::factory()->create();

        // Earlier the same Pacific day (this morning) — should still be "in future".
        $thisMorningPacific = Booking::factory()->forClient($client)->create([
            'start_datetime' => Carbon::parse('2026-07-02 06:00', 'America/Los_Angeles'),
            'end_datetime' => Carbon::parse('2026-07-02 10:00', 'America/Los_Angeles'),
        ]);
        // Yesterday Pacific — should not be.
        $yesterdayPacific = Booking::factory()->forClient($client)->create([
            'start_datetime' => Carbon::parse('2026-07-01 06:00', 'America/Los_Angeles'),
            'end_datetime' => Carbon::parse('2026-07-01 10:00', 'America/Los_Angeles'),
        ]);

        $ids = Booking::inFuture()->pluck('id');

        expect($ids)->toContain($thisMorningPacific->id)
            ->and($ids)->not->toContain($yesterdayPacific->id);
    });
});
