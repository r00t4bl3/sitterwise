<?php

use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Events\BookingCreated;
use App\Events\BookingGroupCreated;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Services\Booking\GuestBookingService;
use Database\Seeders\PricingRulesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PricingRulesTableSeeder::class);
});

describe('Guest Multi-Date Group Booking', function () {
    test('submitting 2 dates creates 1 BookingGroup and 2 Bookings', function () {
        $start1 = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end1 = (clone $start1)->addHours(4);
        $start2 = now()->addDays(2)->setHour(18)->setMinute(0)->setSecond(0);
        $end2 = (clone $start2)->addHours(4);

        $pendingData = [
            'client_first_name' => 'Multi',
            'client_last_name' => 'Date',
            'client_email' => 'multi.date@example.com',
            'client_phone' => '+11234567890',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => $start1->toDateTimeString(),
            'end_datetime' => $end1->toDateTimeString(),
            'address_line1' => '456 Guest Ln',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'dates' => [
                ['start_datetime' => $start1->toDateTimeString(), 'end_datetime' => $end1->toDateTimeString()],
                ['start_datetime' => $start2->toDateTimeString(), 'end_datetime' => $end2->toDateTimeString()],
            ],
            'new_children' => [
                ['name' => 'Child 1', 'gender' => 'male', 'birth_month' => '1', 'birth_year' => '2020'],
            ],
        ];

        $mockService = mock(GuestBookingService::class)->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('attachPaymentMethod')->andReturnNull();

        Event::fake([BookingCreated::class, BookingGroupCreated::class]);

        $booking = $mockService->createBookingWithPayment($pendingData, 'pm_test_token');

        expect(BookingGroup::count())->toBe(1);
        $group = BookingGroup::first();
        expect($group->client_first_name)->toBe('Multi');
        expect($group->client_email)->toBe('multi.date@example.com');
        expect($group->service_type)->toBe(ServiceType::Babysitter->value);
        expect($group->submission_type)->toBe('guest');

        expect(Booking::count())->toBe(2);
        $group->load('bookings');
        expect($group->bookings)->toHaveCount(2);

        Event::assertDispatched(BookingGroupCreated::class);
        Event::assertNotDispatched(BookingCreated::class);
    });

    test('shared fields are on BookingGroup, per-date fields on Bookings', function () {
        $start1 = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end1 = (clone $start1)->addHours(4);
        $start2 = now()->addDays(2)->setHour(18)->setMinute(0)->setSecond(0);
        $end2 = (clone $start2)->addHours(6);

        $pendingData = [
            'client_first_name' => 'Fields',
            'client_last_name' => 'Test',
            'client_email' => 'fields.test@example.com',
            'client_phone' => '+19876543210',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => $start1->toDateTimeString(),
            'end_datetime' => $end1->toDateTimeString(),
            'address_line1' => '123 Field St',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'dates' => [
                ['start_datetime' => $start1->toDateTimeString(), 'end_datetime' => $end1->toDateTimeString()],
                ['start_datetime' => $start2->toDateTimeString(), 'end_datetime' => $end2->toDateTimeString()],
            ],
            'new_children' => [
                ['name' => 'Kid A', 'gender' => 'female', 'birth_month' => '3', 'birth_year' => '2021'],
            ],
            'sitter_preferences' => ['non_smoker'],
            'other_adults_present' => 'Grandparent',
        ];

        Event::fake([BookingCreated::class, BookingGroupCreated::class]);

        $mockService = mock(GuestBookingService::class)->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('attachPaymentMethod')->andReturnNull();

        $booking = $mockService->createBookingWithPayment($pendingData, 'pm_test_token');

        $group = $booking->bookingGroup;

        expect($group->client_first_name)->toBe('Fields');
        expect($group->location_type)->toBe(LocationType::PrivateHome->value);
        expect($group->sitter_preferences)->toBe(['non_smoker']);
        expect($group->other_adults_present)->toBe('Grandparent');

        $group->load('bookings');
        expect($group->bookings[0]->start_datetime->format('Y-m-d'))->not->toEqual($group->bookings[1]->start_datetime->format('Y-m-d'));

        expect($group->bookings[0]->total_working_hour)->not->toEqual($group->bookings[1]->total_working_hour);
    });

    test('single date still fires BookingCreated and not BookingGroupCreated', function () {
        $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(4);

        $pendingData = [
            'client_first_name' => 'Single',
            'client_last_name' => 'Date',
            'client_email' => 'single.date@example.com',
            'client_phone' => '+11234567890',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => $start->toDateTimeString(),
            'end_datetime' => $end->toDateTimeString(),
            'address_line1' => '456 Guest Ln',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'new_children' => [
                ['name' => 'Child 1', 'gender' => 'male', 'birth_month' => '1', 'birth_year' => '2020'],
            ],
        ];

        Event::fake([BookingCreated::class, BookingGroupCreated::class]);

        $mockService = mock(GuestBookingService::class)->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('attachPaymentMethod')->andReturnNull();

        $booking = $mockService->createBookingWithPayment($pendingData, 'pm_test_token');

        Event::assertDispatched(BookingCreated::class);
        Event::assertNotDispatched(BookingGroupCreated::class);

        expect(BookingGroup::count())->toBe(1);
        expect(Booking::count())->toBe(1);
    });

    test('bookings in group have distinct dates and times', function () {
        $start1 = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end1 = (clone $start1)->addHours(4);
        $start2 = now()->addDays(3)->setHour(9)->setMinute(0)->setSecond(0);
        $end2 = (clone $start2)->addHours(8);

        $pendingData = [
            'client_first_name' => 'Different',
            'client_last_name' => 'Dates',
            'client_email' => 'different.dates@example.com',
            'client_phone' => '+11234567890',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => $start1->toDateTimeString(),
            'end_datetime' => $end1->toDateTimeString(),
            'address_line1' => '456 Guest Ln',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'dates' => [
                ['start_datetime' => $start1->toDateTimeString(), 'end_datetime' => $end1->toDateTimeString()],
                ['start_datetime' => $start2->toDateTimeString(), 'end_datetime' => $end2->toDateTimeString()],
            ],
            'new_children' => [
                ['name' => 'Child 1', 'gender' => 'male', 'birth_month' => '1', 'birth_year' => '2020'],
            ],
        ];

        Event::fake([BookingCreated::class, BookingGroupCreated::class]);

        $mockService = mock(GuestBookingService::class)->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('attachPaymentMethod')->andReturnNull();

        $booking = $mockService->createBookingWithPayment($pendingData, 'pm_test_token');

        $group = $booking->bookingGroup;
        $group->load('bookings');

        expect($group->bookings)->toHaveCount(2);

        $dates = $group->bookings->pluck('start_datetime')->map(fn ($d) => $d->format('Y-m-d'));
        expect($dates->unique())->toHaveCount(2);

        $hours = $group->bookings->pluck('total_working_hour')->sort()->values();
        expect($hours[0])->toBe(4);
        expect($hours[1])->toBe(8);
    });
});
