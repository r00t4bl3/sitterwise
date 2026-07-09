<?php

use App\Mail\CaregiverOnHoldCheckinMail;
use App\Models\Caregiver;
use App\Models\CaregiverPause;
use App\Support\Settings;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->seed([
        SettingsSeeder::class,
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
});

test('on-hold check-in respects the editable start-days threshold', function () {
    $caregiver = Caregiver::factory()->create();
    CaregiverPause::create([
        'caregiver_id' => $caregiver->id,
        'paused_at' => now()->subDays(35),
    ]);

    // Default start = 30 days → a 35-day hold qualifies.
    $this->artisan('app:check-in-on-hold-caregivers')->assertOk();
    Mail::assertQueued(CaregiverOnHoldCheckinMail::class, 1);

    // Raise the threshold above 35 → it no longer qualifies.
    Mail::fake();
    Settings::set('caregiver.checkin_start_days', 40);
    $this->artisan('app:check-in-on-hold-caregivers')->assertOk();
    Mail::assertNothingQueued();
});
