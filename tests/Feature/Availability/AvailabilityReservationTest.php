<?php

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\CaregiverStatus;
use App\Enums\ServiceType;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\BookingAvailabilitySlot;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Services\CaregiverRecommendation\AvailabilityReservationService;
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
    $this->reservationService = app(AvailabilityReservationService::class);
    $this->recommendationService = app(CaregiverRecommendationService::class);
});

function makeCaregiverWithSlots(array $slotOverrides = []): Caregiver
{
    $caregiver = Caregiver::factory()->create([
        'status' => CaregiverStatus::Active->value,
    ]);

    Availability::factory()->create(array_merge([
        'caregiver_id' => $caregiver->id,
        'date' => now()->addDays(5)->format('Y-m-d'),
        'time_slots' => ['morning', 'afternoon', 'evening'],
    ], $slotOverrides));

    return $caregiver;
}

function createReservationBooking(Caregiver $caregiver, int $startHour = 8, int $endHour = 14): Booking
{
    $group = BookingGroup::factory()->create([
        'service_type' => ServiceType::Babysitter->value,
    ]);

    return Booking::factory()->create([
        'booking_group_id' => $group->id,
        'caregiver_id' => $caregiver->id,
        'start_datetime' => now()->addDays(5)->setTime($startHour, 0, 0),
        'end_datetime' => now()->addDays(5)->setTime($endHour, 0, 0),
        'status' => BookingStatus::Received->value,
        'payment_status' => BookingPaymentStatus::Pending->value,
        'charge_to_client_hourly' => 25.00,
        'paid_to_caregiver' => 0,
    ]);
}

describe('AvailabilityReservationService', function () {
    test('reserve creates booking availability slot records', function () {
        $caregiver = makeCaregiverWithSlots();
        $availability = $caregiver->availabilities()->first();
        $group = BookingGroup::factory()->create();

        $booking = Booking::factory()->create([
            'booking_group_id' => $group->id,
            'caregiver_id' => null,
            'start_datetime' => now()->addDays(5)->setHour(8)->setMinute(0),
            'end_datetime' => now()->addDays(5)->setHour(14)->setMinute(0),
            'payment_status' => BookingPaymentStatus::Pending->value,
        ]);

        $booking->update(['caregiver_id' => $caregiver->id]);

        $this->reservationService->reserve($booking->fresh());

        expect(BookingAvailabilitySlot::where('booking_id', $booking->id)->count())->toBeGreaterThan(0);
    });

    test('reserve does nothing when booking has no caregiver', function () {
        $group = BookingGroup::factory()->create();
        $booking = Booking::factory()->create([
            'booking_group_id' => $group->id,
            'caregiver_id' => null,
            'start_datetime' => now()->addDays(5)->setHour(8)->setMinute(0),
            'end_datetime' => now()->addDays(5)->setHour(14)->setMinute(0),
            'payment_status' => BookingPaymentStatus::Pending->value,
        ]);

        $this->reservationService->reserve($booking);

        expect(BookingAvailabilitySlot::where('booking_id', $booking->id)->count())->toBe(0);
    });

    test('release removes all booking availability slot records', function () {
        $caregiver = makeCaregiverWithSlots();
        $booking = createReservationBooking($caregiver);

        expect(BookingAvailabilitySlot::where('booking_id', $booking->id)->count())->toBeGreaterThan(0);

        $this->reservationService->release($booking);
        expect(BookingAvailabilitySlot::where('booking_id', $booking->id)->count())->toBe(0);
    });

    test('reserve is idempotent when called multiple times', function () {
        $caregiver = makeCaregiverWithSlots();
        $booking = createReservationBooking($caregiver);

        $firstCount = BookingAvailabilitySlot::where('booking_id', $booking->id)->count();
        $this->reservationService->reserve($booking);

        expect(BookingAvailabilitySlot::where('booking_id', $booking->id)->count())->toBe($firstCount);
    });

    test('reserve skips dates with no availability record', function () {
        $caregiver = makeCaregiverWithSlots();
        $group = BookingGroup::factory()->create();
        $booking = Booking::factory()->create([
            'booking_group_id' => $group->id,
            'caregiver_id' => $caregiver->id,
            'start_datetime' => now()->addDays(10)->setHour(8)->setMinute(0),
            'end_datetime' => now()->addDays(10)->setHour(14)->setMinute(0),
            'payment_status' => BookingPaymentStatus::Pending->value,
        ]);

        $count = BookingAvailabilitySlot::where('booking_id', $booking->id)->count();

        expect($count)->toBe(0);
    });
});

describe('Recommendation service with used slots', function () {
    test('caregiver with fully booked slots shows no available icon', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeCaregiverWithSlots();

        $booking = createReservationBooking($caregiver, 8, 22);

        $dateRanges = [[
            'start' => $booking->start_datetime->format('Y-m-d H:i:s'),
            'end' => $booking->end_datetime->format('Y-m-d H:i:s'),
        ]];

        $recommended = $this->recommendationService->getRecommendedCaregivers(
            $client, null, 20, $dateRanges,
        );

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result)->not->toBeNull();
        expect($result['matchIcons'])->not->toContain('available');
    });

    test('caregiver with partially booked slots still recommended', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeCaregiverWithSlots();

        $booking = createReservationBooking($caregiver, 8, 10);

        $dateRanges = [[
            'start' => now()->addDays(5)->setHour(13)->setMinute(0)->format('Y-m-d H:i:s'),
            'end' => now()->addDays(5)->setHour(17)->setMinute(0)->format('Y-m-d H:i:s'),
        ]];

        $recommended = $this->recommendationService->getRecommendedCaregivers(
            $client, null, 20, $dateRanges,
        );

        expect($recommended->pluck('id'))->toContain($caregiver->id);
    });

    test('unassigning caregiver restores availability icon', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeCaregiverWithSlots();

        $booking = createReservationBooking($caregiver, 8, 22);

        $dateRanges = [[
            'start' => $booking->start_datetime->format('Y-m-d H:i:s'),
            'end' => $booking->end_datetime->format('Y-m-d H:i:s'),
        ]];

        $recommended = $this->recommendationService->getRecommendedCaregivers(
            $client, null, 20, $dateRanges,
        );
        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->not->toContain('available');

        $booking->update(['caregiver_id' => null]);

        $recommended = $this->recommendationService->getRecommendedCaregivers(
            $client, null, 20, $dateRanges,
        );
        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->toContain('available');
    });
});

describe('Booking model saved hook reservation', function () {
    test('setting caregiver_id on booking creates reservation slots', function () {
        $caregiver = makeCaregiverWithSlots();
        $group = BookingGroup::factory()->create();
        $booking = Booking::factory()->create([
            'booking_group_id' => $group->id,
            'caregiver_id' => null,
            'start_datetime' => now()->addDays(5)->setHour(8)->setMinute(0),
            'end_datetime' => now()->addDays(5)->setHour(14)->setMinute(0),
            'payment_status' => BookingPaymentStatus::Pending->value,
        ]);

        expect(BookingAvailabilitySlot::where('booking_id', $booking->id)->count())->toBe(0);

        $booking->update(['caregiver_id' => $caregiver->id]);

        expect(BookingAvailabilitySlot::where('booking_id', $booking->id)->count())->toBeGreaterThan(0);
    });

    test('removing caregiver_id releases reservation slots', function () {
        $caregiver = makeCaregiverWithSlots();
        $booking = createReservationBooking($caregiver);

        expect(BookingAvailabilitySlot::where('booking_id', $booking->id)->count())->toBeGreaterThan(0);

        $booking->update(['caregiver_id' => null]);

        expect(BookingAvailabilitySlot::where('booking_id', $booking->id)->count())->toBe(0);
    });

    test('cancelling a booking releases its reservation slots', function () {
        $caregiver = makeCaregiverWithSlots();
        $booking = createReservationBooking($caregiver);

        expect(BookingAvailabilitySlot::where('booking_id', $booking->id)->count())->toBeGreaterThan(0);

        $booking->update(['status' => 'cancelled']);

        expect(BookingAvailabilitySlot::where('booking_id', $booking->id)->count())->toBe(0);
    });

    test('changing booking dates releases old slots and reserves new ones', function () {
        $caregiver = makeCaregiverWithSlots();

        Availability::factory()->create([
            'caregiver_id' => $caregiver->id,
            'date' => now()->addDays(6)->format('Y-m-d'),
            'time_slots' => ['morning', 'afternoon'],
        ]);

        $booking = createReservationBooking($caregiver, 8, 14);

        expect(BookingAvailabilitySlot::where('booking_id', $booking->id)->count())->toBeGreaterThan(0);

        $booking->update([
            'start_datetime' => now()->addDays(6)->setHour(8)->setMinute(0),
            'end_datetime' => now()->addDays(6)->setHour(14)->setMinute(0),
        ]);

        $newSlots = BookingAvailabilitySlot::where('booking_id', $booking->id)->get();
        expect($newSlots->count())->toBeGreaterThan(0);
    });
});

describe('Buffer time between bookings', function () {
    test('buffer blocks booking too close to existing commitment', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeCaregiverWithSlots();

        createReservationBooking($caregiver, 17, 18);

        $dateRanges = [[
            'start' => now()->addDays(5)->setHour(18)->setMinute(30)->format('Y-m-d H:i:s'),
            'end' => now()->addDays(5)->setHour(19)->setMinute(0)->format('Y-m-d H:i:s'),
        ]];

        $recommended = $this->recommendationService->getRecommendedCaregivers(
            $client, null, 20, $dateRanges,
        );

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->not->toContain('available');
    });

    test('buffer allows booking far enough from existing commitment', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeCaregiverWithSlots();

        createReservationBooking($caregiver, 17, 18);

        $dateRanges = [[
            'start' => now()->addDays(5)->setHour(19)->setMinute(1)->format('Y-m-d H:i:s'),
            'end' => now()->addDays(5)->setHour(20)->setMinute(0)->format('Y-m-d H:i:s'),
        ]];

        $recommended = $this->recommendationService->getRecommendedCaregivers(
            $client, null, 20, $dateRanges,
        );

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->toContain('available');
    });

    test('buffer does not block booking on different date', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeCaregiverWithSlots();

        createReservationBooking($caregiver, 17, 18);

        Availability::factory()->create([
            'caregiver_id' => $caregiver->id,
            'date' => now()->addDays(6)->format('Y-m-d'),
            'time_slots' => ['morning', 'afternoon', 'evening'],
        ]);

        $dateRanges = [[
            'start' => now()->addDays(6)->setHour(18)->setMinute(30)->format('Y-m-d H:i:s'),
            'end' => now()->addDays(6)->setHour(19)->setMinute(0)->format('Y-m-d H:i:s'),
        ]];

        $recommended = $this->recommendationService->getRecommendedCaregivers(
            $client, null, 20, $dateRanges,
        );

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->toContain('available');
    });

    test('cancelled booking does not trigger buffer', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeCaregiverWithSlots();

        $booking = createReservationBooking($caregiver, 17, 18);
        $booking->update(['status' => BookingStatus::Cancelled->value]);

        $dateRanges = [[
            'start' => now()->addDays(5)->setHour(18)->setMinute(30)->format('Y-m-d H:i:s'),
            'end' => now()->addDays(5)->setHour(19)->setMinute(0)->format('Y-m-d H:i:s'),
        ]];

        $recommended = $this->recommendationService->getRecommendedCaregivers(
            $client, null, 20, $dateRanges,
        );

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->toContain('available');
    });

    test('buffer does not affect caregiver with no existing bookings', function () {
        $client = Client::factory()->create(['sitter_preferences' => []]);
        $caregiver = makeCaregiverWithSlots();

        $dateRanges = [[
            'start' => now()->addDays(5)->setHour(8)->setMinute(0)->format('Y-m-d H:i:s'),
            'end' => now()->addDays(5)->setHour(10)->setMinute(0)->format('Y-m-d H:i:s'),
        ]];

        $recommended = $this->recommendationService->getRecommendedCaregivers(
            $client, null, 20, $dateRanges,
        );

        $result = $recommended->firstWhere('id', $caregiver->id);
        expect($result['matchIcons'])->toContain('available');
    });
});
