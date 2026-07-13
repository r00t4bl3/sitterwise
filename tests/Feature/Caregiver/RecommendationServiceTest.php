<?php

use App\Enums\CaregiverStatus;
use App\Enums\ServiceType;
use App\Enums\SitterPreference;
use App\Models\AttributeDefinition;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Location;
use App\Models\SpecialtyType;
use App\Models\ZipCode;
use App\Services\CaregiverRecommendation\CaregiverRecommendationService;
use Carbon\CarbonImmutable;
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

    $this->defaultAvailDate = now()->addDays(5)->format('Y-m-d');

    // La Jolla (92037) -> South County, used by the location-scoring tests.
    ZipCode::factory()->create([
        'zip_code' => '92037',
        'area' => 'La Jolla',
        'location_id' => Location::where('name', 'South County')->value('id'),
    ]);
});

function makeActiveCaregiver(array $overrides = []): Caregiver
{
    $caregiver = Caregiver::factory()->create(array_merge([
        'status' => CaregiverStatus::Active->value,
    ], $overrides));

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
            'id', 'name', 'age', 'score', 'matchIcons', 'hasBeenNotified', 'speaksSpanish',
        ]);
    });

    test('flags caregivers who speak Spanish', function () {
        $client = Client::factory()->create();
        $spanishSpeaker = makeActiveCaregiver(['languages' => ['spanish', 'english']]);
        $englishOnly = makeActiveCaregiver(['languages' => ['english']]);
        $noLanguages = makeActiveCaregiver(['languages' => null]);

        $recommended = $this->service->getRecommendedCaregivers($client)->keyBy('id');

        expect($recommended[$spanishSpeaker->id]['speaksSpanish'])->toBeTrue()
            ->and($recommended[$englishOnly->id]['speaksSpanish'])->toBeFalse()
            ->and($recommended[$noLanguages->id]['speaksSpanish'])->toBeFalse();
    });

    test('blocked caregiver is excluded from recommendations', function () {
        $client = Client::factory()->create();
        $blocked = makeActiveCaregiver(['rating' => 4.5]);

        $client->blockedCaregivers()->attach($blocked->id);

        $recommended = $this->service->getRecommendedCaregivers($client);

        expect($recommended->pluck('id')->contains($blocked->id))->toBeFalse();
    });

    test('previous work includes previous_work icon and score contribution', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        Booking::factory()->forClient($client)->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->toContain('previous_work')
            ->and($result['score'])->toBeGreaterThanOrEqual(2);
    });

    test('excellent match when available, specialty, and preferred location', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $southCounty = Location::where('name', 'South County')->first();
        $caregiver = makeActiveCaregiver(['rating' => 4.5]);

        $caregiver->locations()->sync([$southCounty->id => ['is_preferred' => true]]);

        $babies = SpecialtyType::where('name', 'Babies')->first();
        $caregiver->specialtyTypes()->sync([$babies->id]);

        $startDate = now()->addDays(5)->setHour(9)->setMinute(0);
        $endDate = (clone $startDate)->addHours(4);

        $booking = Booking::factory()->forClient($client)->create([
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
            'caregiver_id' => null,
        ]);
        $booking->bookingGroup->update([
            'service_type' => ServiceType::Babysitter->value,
            'address_zip' => '92037',
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['score'])->toBe(11100)
            ->and($result['matchIcons'])->toContain('available')
            ->and($result['matchIcons'])->toContain('specialty_babies')
            ->and($result['matchIcons'])->toContain('location_preferred');
    });

    test('resolves the booking region from its zip code (no city needed)', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $southCounty = Location::where('name', 'South County')->first();
        // 92037 -> South County is seeded in beforeEach.
        $caregiver = makeActiveCaregiver(['rating' => 4.5]);
        $caregiver->locations()->sync([$southCounty->id => ['is_preferred' => true]]);

        $startDate = now()->addDays(5)->setHour(9)->setMinute(0);
        $endDate = (clone $startDate)->addHours(4);

        $booking = Booking::factory()->forClient($client)->create([
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
            'caregiver_id' => null,
        ]);
        // Zip only — no city — so the match can only come from the zip lookup.
        $booking->bookingGroup->update([
            'service_type' => ServiceType::Babysitter->value,
            'address_city' => null,
            'address_zip' => '92037',
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->toContain('location_preferred');
    });

    test('good match when specialty and willing location (adjacent fit)', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $southCounty = Location::where('name', 'South County')->first();
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        $caregiver->locations()->sync([$southCounty->id => ['is_preferred' => false]]);

        $babies = SpecialtyType::where('name', 'Babies')->first();
        $caregiver->specialtyTypes()->sync([$babies->id]);

        $startDate = now()->addDays(5)->setHour(9)->setMinute(0);
        $endDate = (clone $startDate)->addHours(4);

        $booking = Booking::factory()->forClient($client)->create([
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
            'caregiver_id' => null,
        ]);
        $booking->bookingGroup->update([
            'service_type' => ServiceType::Babysitter->value,
            'address_zip' => '92037',
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['score'])->toBe(11010)
            ->and($result['matchIcons'])->toContain('available')
            ->and($result['matchIcons'])->toContain('specialty_babies')
            ->and($result['matchIcons'])->toContain('location_willing');
    });

    test('fair match when recent work (3mo) plus specialty and location fit', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $southCounty = Location::where('name', 'South County')->first();
        $otherClient = Client::factory()->create();
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        $caregiver->locations()->sync([$southCounty->id => ['is_preferred' => true]]);
        $babies = SpecialtyType::where('name', 'Babies')->first();
        $caregiver->specialtyTypes()->sync([$babies->id]);

        Booking::factory()->forClient($otherClient)->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
            'start_datetime' => now()->subMonth(),
            'end_datetime' => now()->subMonth()->addHours(4),
        ]);

        $startDate = now()->addDays(5)->setHour(9)->setMinute(0);
        $endDate = (clone $startDate)->addHours(4);

        $booking = Booking::factory()->forClient($client)->create([
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
            'caregiver_id' => null,
        ]);
        $booking->bookingGroup->update([
            'service_type' => ServiceType::Babysitter->value,
            'address_zip' => '92037',
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['score'])->toBe(11104)
            ->and($result['matchIcons'])->toContain('available')
            ->and($result['matchIcons'])->toContain('recent_work');
    });

    test('potential match when recent work (6mo) with partial fit', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $otherClient = Client::factory()->create();
        $caregiver = makeActiveCaregiver(['rating' => 3.5]);

        $babies = SpecialtyType::where('name', 'Babies')->first();
        $caregiver->specialtyTypes()->sync([$babies->id]);

        Booking::factory()->forClient($otherClient)->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
            'start_datetime' => now()->subMonths(4),
            'end_datetime' => now()->subMonths(4)->addHours(4),
        ]);

        $startDate = now()->addDays(5)->setHour(9)->setMinute(0);
        $endDate = (clone $startDate)->addHours(4);

        $booking = Booking::factory()->forClient($client)->create([
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
            'caregiver_id' => null,
        ]);
        $booking->bookingGroup->update([
            'service_type' => ServiceType::Babysitter->value,
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['score'])->toBe(11001)
            ->and($result['matchIcons'])->toContain('available')
            ->and($result['matchIcons'])->toContain('specialty_babies')
            ->and($result['matchIcons'])->toContain('recent_work');
    });

    test('no match when caregiver has no matching criteria', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeActiveCaregiver(['rating' => 3.0]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['score'])->toBe(0)
            ->and($result['matchIcons'])->toBeEmpty();
    });

    test('respects limit parameter', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        makeActiveCaregiver();
        makeActiveCaregiver();
        makeActiveCaregiver();
        makeActiveCaregiver();
        makeActiveCaregiver();

        $recommended = $this->service->getRecommendedCaregivers($client, limit: 3);

        expect($recommended)->toHaveCount(3);
    });

    test('hasBeenNotified returns true when caregiver was notified for booking', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        $startDate = now()->addDays(5)->setHour(9)->setMinute(0);
        $endDate = (clone $startDate)->addHours(4);

        $booking = Booking::factory()->forClient($client)->create([
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
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
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        $startDate = now()->addDays(5)->setHour(9)->setMinute(0);
        $endDate = (clone $startDate)->addHours(4);

        $booking = Booking::factory()->forClient($client)->create([
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
            'caregiver_id' => null,
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['hasBeenNotified'])->toBeFalse();
    });

    test('hasBeenNotified returns false when no booking provided', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['hasBeenNotified'])->toBeFalse();
    });

    test('returns empty collection when no active caregivers', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        expect($recommended)->toHaveCount(0);
    });

    test('caregiver without availability appears with zero score', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = Caregiver::factory()->create(['status' => CaregiverStatus::Active->value]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        expect($recommended)->toHaveCount(1);
        expect($recommended[0])->toMatchArray([
            'id' => $caregiver->id,
            'score' => 0,
            'matchIcons' => [],
        ]);
    });

    test('caregivers are sorted by score descending then name ascending', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);

        $cg2 = makeActiveCaregiver(['rating' => 4.0, 'first_name' => 'Alpha']);
        $cg1 = makeActiveCaregiver(['rating' => 4.5, 'first_name' => 'Beta']);

        Booking::factory()->forClient($client)->create([
            'caregiver_id' => $cg1->id,
            'status' => 'completed',
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        $scores = $recommended->pluck('score')->toArray();
        expect($scores[0])->toBeGreaterThan($scores[1]);
    });

    test('baby_specialist preference matches Babies specialty without service type', function () {
        $client = Client::factory()->create([
            'sitter_preferences' => [SitterPreference::BabySpecialist->value],
        ]);
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        $babies = SpecialtyType::where('name', 'Babies')->first();
        $caregiver->specialtyTypes()->sync([$babies->id]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->toContain('specialty_babies');
    });

    test('a caregiver with only toddler and preschool specialties shows those icons, not the baby icon', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        $toddlers = SpecialtyType::where('name', 'Toddlers')->first();
        $preschool = SpecialtyType::where('name', 'Preschool')->first();
        $caregiver->specialtyTypes()->sync([$toddlers->id, $preschool->id]);

        $startDate = now()->addDays(5)->setHour(9)->setMinute(0);
        $endDate = (clone $startDate)->addHours(4);

        $booking = Booking::factory()->forClient($client)->create([
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
            'caregiver_id' => null,
        ]);
        $booking->bookingGroup->update(['service_type' => ServiceType::Babysitter->value]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->toContain('specialty_toddlers')
            ->and($result['matchIcons'])->toContain('specialty_preschool')
            ->and($result['matchIcons'])->not->toContain('specialty_babies');
    });

    test('special_needs_care preference matches EAV special_needs attribute', function () {
        $client = Client::factory()->create([
            'sitter_preferences' => [SitterPreference::SpecialNeedsCare->value],
        ]);
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        $specialNeedsAttr = AttributeDefinition::where('slug', 'special_needs')->first();
        $caregiver->attributes()->sync([$specialNeedsAttr->id => ['value' => 'true']]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->toContain('special_needs');
    });

    test('special_needs_care preference does not match without special_needs attribute', function () {
        $client = Client::factory()->create([
            'sitter_preferences' => [SitterPreference::SpecialNeedsCare->value],
        ]);
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);
        $caregiver->attributes()->sync([]);

        $recommended = $this->service->getRecommendedCaregivers($client);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->not->toContain('special_needs');
    });

    test('favorited caregiver gets favorited icon', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        $client->favoriteCaregivers()->attach($caregiver->id);

        $recommended = $this->service->getRecommendedCaregivers($client);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->toContain('favorited');
    });

    test('available when caregiver has availability for all sibling booking dates', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        Availability::factory()->create([
            'caregiver_id' => $caregiver->id,
            'date' => now()->addDays(6)->format('Y-m-d'),
            'time_slots' => ['morning', 'afternoon'],
        ]);

        $startDate1 = now()->addDays(5)->setHour(9)->setMinute(0);
        $endDate1 = (clone $startDate1)->addHours(4);
        $startDate2 = now()->addDays(6)->setHour(9)->setMinute(0);
        $endDate2 = (clone $startDate2)->addHours(4);

        $bookingGroup = BookingGroup::factory()->create([
            'client_id' => $client->id,
        ]);

        Booking::factory()->create([
            'booking_group_id' => $bookingGroup->id,
            'start_datetime' => $startDate1,
            'end_datetime' => $endDate1,
            'caregiver_id' => null,
        ]);
        Booking::factory()->create([
            'booking_group_id' => $bookingGroup->id,
            'start_datetime' => $startDate2,
            'end_datetime' => $endDate2,
            'caregiver_id' => null,
        ]);

        $dateRanges = $bookingGroup->bookings->map(fn (Booking $b) => [
            'start' => $b->start_datetime,
            'end' => $b->end_datetime,
        ])->values()->toArray();

        $recommended = $this->service->getRecommendedCaregivers(
            $client,
            $bookingGroup->bookings->first(),
            dateRanges: $dateRanges,
        );

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->toContain('available');
    });

    test('not available when caregiver has availability for only some sibling dates', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeActiveCaregiver(['rating' => 4.0]);

        $startDate1 = now()->addDays(5)->setHour(9)->setMinute(0);
        $endDate1 = (clone $startDate1)->addHours(4);
        $startDate2 = now()->addDays(6)->setHour(9)->setMinute(0);
        $endDate2 = (clone $startDate2)->addHours(4);

        $bookingGroup = BookingGroup::factory()->create([
            'client_id' => $client->id,
        ]);

        Booking::factory()->create([
            'booking_group_id' => $bookingGroup->id,
            'start_datetime' => $startDate1,
            'end_datetime' => $endDate1,
            'caregiver_id' => null,
        ]);
        Booking::factory()->create([
            'booking_group_id' => $bookingGroup->id,
            'start_datetime' => $startDate2,
            'end_datetime' => $endDate2,
            'caregiver_id' => null,
        ]);

        $dateRanges = $bookingGroup->bookings->map(fn (Booking $b) => [
            'start' => $b->start_datetime,
            'end' => $b->end_datetime,
        ])->values()->toArray();

        $recommended = $this->service->getRecommendedCaregivers(
            $client,
            $bookingGroup->bookings->first(),
            dateRanges: $dateRanges,
        );

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->not->toContain('available');
    });

    test('available and favorited caregiver scores highest', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $southCounty = Location::where('name', 'South County')->first();
        $caregiver = makeActiveCaregiver(['rating' => 4.5]);

        $caregiver->locations()->sync([$southCounty->id => ['is_preferred' => true]]);
        $babies = SpecialtyType::where('name', 'Babies')->first();
        $caregiver->specialtyTypes()->sync([$babies->id]);
        $client->favoriteCaregivers()->attach($caregiver->id);

        $startDate = now()->addDays(5)->setHour(9)->setMinute(0);
        $endDate = (clone $startDate)->addHours(4);

        $booking = Booking::factory()->forClient($client)->create([
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
            'caregiver_id' => null,
        ]);
        $booking->bookingGroup->update([
            'service_type' => ServiceType::Babysitter->value,
            'address_zip' => '92037',
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['score'])->toBe(111100)
            ->and($result['matchIcons'])->toContain('favorited')
            ->and($result['matchIcons'])->toContain('available')
            ->and($result['matchIcons'])->toContain('specialty_babies')
            ->and($result['matchIcons'])->toContain('location_preferred');
    });

    test('matches caregiver for evening PT booking that crosses UTC midnight', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = Caregiver::factory()->create(['status' => CaregiverStatus::Active->value]);

        Availability::factory()->create([
            'caregiver_id' => $caregiver->id,
            'date' => '2026-06-20',
            'time_slots' => ['evening'],
        ]);

        // 7–10 PM PT on June 20 = 2–5 AM UTC June 21
        $startDate = CarbonImmutable::parse('2026-06-20 19:00:00', 'America/Los_Angeles');
        $endDate = CarbonImmutable::parse('2026-06-20 22:00:00', 'America/Los_Angeles');

        $booking = Booking::factory()->forClient($client)->create([
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
            'caregiver_id' => null,
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result)->not->toBeNull()
            ->and($result['matchIcons'])->toContain('available');
    });

    test('daytime PT booking still matches (no regression)', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = Caregiver::factory()->create(['status' => CaregiverStatus::Active->value]);

        Availability::factory()->create([
            'caregiver_id' => $caregiver->id,
            'date' => '2026-06-20',
            'time_slots' => ['morning'],
        ]);

        // 9 AM–12 PM PT on June 20 = 4–7 PM UTC June 20 (same UTC date)
        $startDate = CarbonImmutable::parse('2026-06-20 09:00:00', 'America/Los_Angeles');
        $endDate = CarbonImmutable::parse('2026-06-20 12:00:00', 'America/Los_Angeles');

        $booking = Booking::factory()->forClient($client)->create([
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
            'caregiver_id' => null,
        ]);

        $recommended = $this->service->getRecommendedCaregivers($client, $booking);

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result)->not->toBeNull()
            ->and($result['matchIcons'])->toContain('available');
    });
});
