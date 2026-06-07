<?php

use App\Enums\CaregiverStatus;
use App\Enums\ServiceType;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Location;
use App\Models\SpecialtyType;
use App\Services\CaregiverRecommendation\CaregiverRecommendationService;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->service = app(CaregiverRecommendationService::class);
    $this->activeStatus = CaregiverStatus::Active;

    // Ensure caregivers have at least one availability record (required by default filter)
    $this->defaultAvailDate = now()->addDays(5)->format('Y-m-d');
});

/**
 * Helper: create an active caregiver with a basic availability record.
 */
function makeActiveCaregiver(array $overrides = []): Caregiver
{
    $caregiver = Caregiver::factory()->create(array_merge([
        'status' => CaregiverStatus::Active->value,
    ], $overrides));

    // Every caregiver needs at least one availability to pass the default filter
    Availability::factory()->create([
        'caregiver_id' => $caregiver->id,
        'date' => now()->addDays(5)->format('Y-m-d'),
        'time_slots' => ['morning', 'afternoon'],
    ]);

    return $caregiver;
}

describe('Recommendation Service - Caregiver', function () {
    test('returns only active caregivers', function () {
        $client = Client::factory()->create();
        $active = makeActiveCaregiver();
        $inactive = Caregiver::factory()->create(['status' => CaregiverStatus::Inactive->value]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        $ids = $recommended->pluck('id')->toArray();
        expect(in_array($active->id, $ids))->toBeTrue()
            ->and(in_array($inactive->id, $ids))->toBeFalse();
    });

    test('returns expected keys for each caregiver', function () {
        $client = Client::factory()->create();
        makeActiveCaregiver();

        $recommended = $this->service->getRecommendedCaregivers($client);

        expect($recommended->first())->toHaveKeys([
            'id', 'name', 'age', 'tier', 'tierLabel', 'matchIcons', 'hasBeenNotified',
        ]);
    });

    test('blocked caregiver is excluded from recommendations', function () {
        $client = Client::factory()->create();
        $blocked = makeActiveCaregiver(['rating' => 4.5]);

        $client->blockedCaregivers()->attach($blocked->id);

        $recommended = $this->service->getRecommendedCaregivers($client);

        expect($recommended->pluck('id')->contains($blocked->id))->toBeFalse();
    });

    test('tier 1 when caregiver previously worked with client', function () {
        $client = Client::factory()->create();
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        Booking::factory()->forClient($client)->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['tier'])->toBe(1)
            ->and($result['matchIcons'])->toContain('previous_work');
    });

    test('tier 2 when caregiver has availability, specialty, and preferred location', function () {
        $client = Client::factory()->create();
        $southCounty = Location::where('name', 'South County')->first();
        $caregiver = makeActiveCaregiver(['rating' => 4.5]);

        // Give caregiver preferred location in South County
        $caregiver->locations()->sync([$southCounty->id => ['is_preferred' => true]]);

        // Give caregiver the Babies specialty (matching babysitter service type)
        $babies = SpecialtyType::where('name', 'Babies')->first();
        $caregiver->specialtyTypes()->sync([$babies->id]);

        $startDate = now()->addDays(5)->setHour(9)->setMinute(0);
        $endDate = (clone $startDate)->addHours(4);

        // Availability already created in makeActiveCaregiver at same date
        $booking = Booking::factory()->forClient($client)->create([
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
        ]);
        // Set booking group service_type + address city
        $booking->bookingGroup->update([
            'service_type' => ServiceType::Babysitter->value,
            'address_city' => 'La Jolla',
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['tier'])->toBe(2)
            ->and($result['matchIcons'])->toContain('available')
            ->and($result['matchIcons'])->toContain('specialty')
            ->and($result['matchIcons'])->toContain('location_preferred');
    });

    test('tier 3 when caregiver has specialty and willing location (adjacent fit)', function () {
        $client = Client::factory()->create();
        $southCounty = Location::where('name', 'South County')->first();
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        // Willing (non-preferred) location
        $caregiver->locations()->sync([$southCounty->id => ['is_preferred' => false]]);

        // Matching specialty
        $babies = SpecialtyType::where('name', 'Babies')->first();
        $caregiver->specialtyTypes()->sync([$babies->id]);

        $booking = Booking::factory()->forClient($client)->create();
        $booking->bookingGroup->update([
            'service_type' => ServiceType::Babysitter->value,
            'address_city' => 'La Jolla',
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['tier'])->toBe(3)
            ->and($result['matchIcons'])->toContain('specialty')
            ->and($result['matchIcons'])->toContain('location_willing');
    });

    test('tier 4 when caregiver has recent work (3mo) plus specialty and location fit', function () {
        $client = Client::factory()->create();
        $southCounty = Location::where('name', 'South County')->first();
        $otherClient = Client::factory()->create();
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        $caregiver->locations()->sync([$southCounty->id => ['is_preferred' => true]]);
        $babies = SpecialtyType::where('name', 'Babies')->first();
        $caregiver->specialtyTypes()->sync([$babies->id]);

        // Recent work with a different client in last 3 months
        Booking::factory()->forClient($otherClient)->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
            'start_datetime' => now()->subMonth(),
            'end_datetime' => now()->subMonth()->addHours(4),
        ]);

        $booking = Booking::factory()->forClient($client)->create();
        $booking->bookingGroup->update([
            'service_type' => ServiceType::Babysitter->value,
            'address_city' => 'La Jolla',
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['tier'])->toBe(4)
            ->and($result['matchIcons'])->toContain('recent_work');
    });

    test('tier 5 when caregiver has recent work (6mo) with partial fit', function () {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $caregiver = makeActiveCaregiver(['rating' => 3.5]);

        // Give caregiver a matching specialty (needed for "partial fit")
        $babies = SpecialtyType::where('name', 'Babies')->first();
        $caregiver->specialtyTypes()->sync([$babies->id]);

        // Recent work in last 6 months (4 months ago, not 3mo, so recentWork6mo only)
        Booking::factory()->forClient($otherClient)->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
            'start_datetime' => now()->subMonths(4),
            'end_datetime' => now()->subMonths(4)->addHours(4),
        ]);

        // Pass a booking with service_type to trigger specialty check
        $booking = Booking::factory()->forClient($client)->create();
        $booking->bookingGroup->update([
            'service_type' => ServiceType::Babysitter->value,
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['tier'])->toBe(5)
            ->and($result['matchIcons'])->toContain('recent_work');
    });

    test('tier 6 for caregivers with no special matching', function () {
        $client = Client::factory()->create();
        $caregiver = makeActiveCaregiver(['rating' => 3.0]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['tier'])->toBe(6)
            ->and($result['matchIcons'])->toBeEmpty();
    });

    test('respects limit parameter', function () {
        $client = Client::factory()->create();
        makeActiveCaregiver();
        makeActiveCaregiver();
        makeActiveCaregiver();
        makeActiveCaregiver();
        makeActiveCaregiver();

        $recommended = $this->service->getRecommendedCaregivers($client, limit: 3);

        expect($recommended)->toHaveCount(3);
    });

    test('hasBeenNotified returns true when caregiver was notified for booking', function () {
        $client = Client::factory()->create();
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        $booking = Booking::factory()->forClient($client)->create([
            'caregiver_id' => null,
        ]);

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver->id,
            'notified_at' => now(),
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['hasBeenNotified'])->toBeTrue();
    });

    test('hasBeenNotified returns false when no notification exists', function () {
        $client = Client::factory()->create();
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        $booking = Booking::factory()->forClient($client)->create([
            'caregiver_id' => null,
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['hasBeenNotified'])->toBeFalse();
    });

    test('hasBeenNotified returns false when no booking provided', function () {
        $client = Client::factory()->create();
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['hasBeenNotified'])->toBeFalse();
    });

    test('returns empty collection when no active caregivers', function () {
        $client = Client::factory()->create();

        $recommended = $this->service->getRecommendedCaregivers($client);

        expect($recommended)->toHaveCount(0);
    });

    test('caregiver without availability is excluded', function () {
        $client = Client::factory()->create();
        Caregiver::factory()->create(['status' => CaregiverStatus::Active->value]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        expect($recommended)->toHaveCount(0);
    });

    test('caregivers are sorted by tier then name', function () {
        $client = Client::factory()->create();

        $cg2 = makeActiveCaregiver(['rating' => 4.0, 'first_name' => 'Alpha']);
        $cg1 = makeActiveCaregiver(['rating' => 4.5, 'first_name' => 'Beta']);

        // Give cg1 previous work to put it in tier 1
        Booking::factory()->forClient($client)->create([
            'caregiver_id' => $cg1->id,
            'status' => 'completed',
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        // cg1 (tier 1) should come before cg2 (tier 6)
        $tiers = $recommended->pluck('tier')->toArray();
        expect($tiers[0])->toBeLessThan($tiers[1]);
    });
});
