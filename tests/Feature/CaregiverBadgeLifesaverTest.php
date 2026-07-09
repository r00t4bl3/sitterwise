<?php

use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Services\CaregiverBadgeService;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SpecialtyTypeSeeder;

beforeEach(function () {
    $this->seed([
        SettingsSeeder::class,
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->client = Client::factory()->create();
});

function lifesaverBadge(Caregiver $caregiver): ?array
{
    return collect(app(CaregiverBadgeService::class)->badgesFor($caregiver))
        ->firstWhere('slug', 'lifesaver');
}

test('the Lifesaver badge unlocks after 5 short-notice rescues', function () {
    $caregiver = Caregiver::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        // Created < 18h before start → a short-notice Lifesaver rescue.
        Booking::factory()->forClient($this->client)->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
            'start_datetime' => now()->addHours(5),
            'end_datetime' => now()->addHours(9),
        ]);
    }

    expect(lifesaverBadge($caregiver))->not->toBeNull()
        ->and(lifesaverBadge($caregiver)['earned'])->toBeTrue();
});

test('the Lifesaver badge stays locked for ordinary far-out jobs', function () {
    $caregiver = Caregiver::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        Booking::factory()->forClient($this->client)->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
            'start_datetime' => now()->addDays(10),
            'end_datetime' => now()->addDays(10)->addHours(4),
        ]);
    }

    expect(lifesaverBadge($caregiver)['earned'])->toBeFalse();
});
