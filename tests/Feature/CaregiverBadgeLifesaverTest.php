<?php

use App\Enums\CaregiverStatus;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\OnboardingChecklistItem;
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

function badgeBySlug(Caregiver $caregiver, string $slug): ?array
{
    return collect(app(CaregiverBadgeService::class)->badgesFor($caregiver))
        ->firstWhere('slug', $slug);
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

test('tenure badges use real job history, not the import date', function () {
    // Migrated caregiver: created_at is the import date (now), but their first
    // booking is 2 years ago — so they should have "One Year In" but not the
    // 3- or 5-year tenure badges.
    $caregiver = Caregiver::factory()->create(['created_at' => now()]);

    Booking::factory()->forClient($this->client)->create([
        'caregiver_id' => $caregiver->id,
        'status' => 'completed',
        'start_datetime' => now()->subYears(2),
        'end_datetime' => now()->subYears(2)->addHours(4),
    ]);

    expect(badgeBySlug($caregiver, 'one-year-in')['earned'])->toBeTrue()
        ->and(badgeBySlug($caregiver, 'three-and-thriving')['earned'])->toBeFalse()
        ->and(badgeBySlug($caregiver, 'heart-of-the-house')['earned'])->toBeFalse();
});

test('a brand-new caregiver with no bookings has no tenure badges', function () {
    $caregiver = Caregiver::factory()->create(['created_at' => now()]);

    expect(badgeBySlug($caregiver, 'one-year-in')['earned'])->toBeFalse()
        ->and(badgeBySlug($caregiver, 'three-and-thriving')['earned'])->toBeFalse()
        ->and(badgeBySlug($caregiver, 'heart-of-the-house')['earned'])->toBeFalse();
});

test('Infant Specialist ignores jobs whose children are older than 2', function () {
    $caregiver = Caregiver::factory()->create();

    // 10 completed jobs whose only child is ~11 years old — must NOT count.
    for ($i = 0; $i < 10; $i++) {
        $booking = Booking::factory()->forClient($this->client)->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
        ]);
        $booking->bookingGroup->update(['children' => [['birth_year' => 2015, 'birth_month' => 1]]]);
    }

    expect(badgeBySlug($caregiver, 'infant-specialist')['earned'])->toBeFalse();
});

test('Infant Specialist unlocks after 10 jobs with a child under 2', function () {
    $caregiver = Caregiver::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $booking = Booking::factory()->forClient($this->client)->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
        ]);
        $booking->bookingGroup->update(['children' => [['birth_year' => now()->year, 'birth_month' => 1]]]);
    }

    expect(badgeBySlug($caregiver, 'infant-specialist')['earned'])->toBeTrue();
});

test('Ready, Set, Sit is earned by an Active caregiver even without checklist rows', function () {
    $caregiver = Caregiver::factory()->create(['status' => CaregiverStatus::Active]);

    expect(badgeBySlug($caregiver, 'ready-set-sit')['earned'])->toBeTrue();
});

test('Ready, Set, Sit is earned when all onboarding checklist items are complete', function () {
    $caregiver = Caregiver::factory()->create(['status' => CaregiverStatus::HiredOnboarding]);
    OnboardingChecklistItem::create([
        'caregiver_id' => $caregiver->id,
        'item_key' => 'training_quiz',
        'label' => 'Training Quiz Passed',
        'completed_at' => now(),
    ]);

    expect(badgeBySlug($caregiver, 'ready-set-sit')['earned'])->toBeTrue();
});

test('Ready, Set, Sit stays locked with a pending checklist item and non-active status', function () {
    $caregiver = Caregiver::factory()->create(['status' => CaregiverStatus::HiredOnboarding]);
    OnboardingChecklistItem::create([
        'caregiver_id' => $caregiver->id,
        'item_key' => 'training_quiz',
        'label' => 'Training Quiz Passed',
        'completed_at' => null,
    ]);

    expect(badgeBySlug($caregiver, 'ready-set-sit')['earned'])->toBeFalse();
});

test('Ready, Set, Sit stays locked for a non-active caregiver with no checklist rows', function () {
    $caregiver = Caregiver::factory()->create(['status' => CaregiverStatus::Applicant]);

    expect(badgeBySlug($caregiver, 'ready-set-sit')['earned'])->toBeFalse();
});

test('The Daymaker counts jobs by local (Pacific) time, not UTC', function () {
    $caregiver = Caregiver::factory()->create();

    // 10 jobs from 10 AM–2 PM Pacific (naive strings are stored as Pacific->UTC).
    // In UTC these start at 17:00, so the old hour-in-UTC check would miss them.
    for ($i = 0; $i < 10; $i++) {
        Booking::factory()->forClient($this->client)->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
            'start_datetime' => '2026-07-0'.($i % 9 + 1).' 10:00:00',
            'end_datetime' => '2026-07-0'.($i % 9 + 1).' 14:00:00',
        ]);
    }

    expect(badgeBySlug($caregiver, 'daymaker')['earned'])->toBeTrue();
});

test('The Daymaker excludes overnight jobs even when their UTC hours fall in 8–16', function () {
    $caregiver = Caregiver::factory()->create();

    // 1 AM–5 AM Pacific = 08:00–12:00 UTC. The old UTC-hour check counted these
    // as "daytime"; by local time they are overnight and must not count.
    for ($i = 0; $i < 10; $i++) {
        Booking::factory()->forClient($this->client)->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
            'start_datetime' => '2026-07-0'.($i % 9 + 1).' 01:00:00',
            'end_datetime' => '2026-07-0'.($i % 9 + 1).' 05:00:00',
        ]);
    }

    expect(badgeBySlug($caregiver, 'daymaker')['earned'])->toBeFalse();
});

test('backfilled jobs created after their start do not unlock the Lifesaver badge', function () {
    $caregiver = Caregiver::factory()->create();

    for ($i = 0; $i < 6; $i++) {
        // Historical import: created now, but the job happened months ago.
        Booking::factory()->forClient($this->client)->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
            'start_datetime' => now()->subMonths(2)->subDays($i),
            'end_datetime' => now()->subMonths(2)->subDays($i)->addHours(4),
        ]);
    }

    expect(lifesaverBadge($caregiver)['earned'])->toBeFalse();
});
