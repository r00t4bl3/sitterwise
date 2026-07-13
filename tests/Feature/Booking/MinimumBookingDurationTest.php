<?php

use App\Rules\MinimumBookingDuration;
use App\Support\Settings;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Run the rule against an end time and return the failure message, or null when
 * it passes. Start is fixed at 09:00; the span is set by $end.
 */
function runMinimumDuration(string $end): ?string
{
    $rule = (new MinimumBookingDuration)->setData(['start_datetime' => '2026-05-28 09:00:00']);
    $message = null;
    $rule->validate('end_datetime', $end, function (string $m) use (&$message) {
        $message = $m;
    });

    return $message;
}

describe('MinimumBookingDuration honors the setting', function () {
    test('falls back to a 4-hour minimum when the setting row is absent', function () {
        // No SettingsSeeder → Settings::get returns the hardcoded 4 fallback.
        expect(runMinimumDuration('2026-05-28 12:00:00')) // 3h
            ->toBe('The booking must be at least 4 hours long.')
            ->and(runMinimumDuration('2026-05-28 13:00:00')) // 4h
            ->toBeNull();
    });

    test('uses the configured minimum when the setting is lowered', function () {
        $this->seed(SettingsSeeder::class);
        Settings::set('bookings.minimum_hours', 3);

        expect(runMinimumDuration('2026-05-28 12:00:00')) // 3h — now allowed
            ->toBeNull()
            ->and(runMinimumDuration('2026-05-28 11:00:00')) // 2h — still too short
            ->toBe('The booking must be at least 3 hours long.');
    });
});
