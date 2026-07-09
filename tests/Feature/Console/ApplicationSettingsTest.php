<?php

use App\Models\IncompleteApplication;
use App\Support\Settings;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

test('incomplete-application stale window is settings-driven', function () {
    IncompleteApplication::create([
        'email' => 'applicant@example.com',
        'last_activity_at' => now()->subDays(20),
    ]);

    // Default stale = 14 days → a 20-day-idle draft is stale.
    expect(IncompleteApplication::stale()->count())->toBe(1);

    // Raise the archive threshold past 20 → no longer stale.
    Settings::set('applications.stale_days', 30);
    expect(IncompleteApplication::stale()->count())->toBe(0);
});

test('incomplete-application expired window is settings-driven', function () {
    IncompleteApplication::create([
        'email' => 'old@example.com',
        'last_activity_at' => now()->subDays(100),
    ]);

    // Default expired = 90 days → a 100-day-idle draft is expired.
    expect(IncompleteApplication::expired()->count())->toBe(1);

    Settings::set('applications.expired_days', 120);
    expect(IncompleteApplication::expired()->count())->toBe(0);
});
