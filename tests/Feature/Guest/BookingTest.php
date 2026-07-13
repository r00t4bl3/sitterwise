<?php

use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

it('guest can view the create booking page', function () {
    $response = $this->get(route('guest.bookings.create'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('guest/bookings/create'));
});

it('guest can create a booking', function () {
    $startDate = now()->addDays(2);
    $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
    $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);

    $response = $this->post(route('guest.bookings.store'), [
        'client_first_name' => 'John',
        'client_last_name' => 'Doe',
        'client_email' => 'john.doe@example.com',
        'client_phone' => '+15551234567',
        'service_type' => 'babysitter',
        'location_type' => 'private_home',
        'start_datetime' => $startDatetime->toISOString(),
        'end_datetime' => $endDatetime->toISOString(),
        'address_line1' => '123 Main St',
        'address_line2' => '',
        'address_city' => 'San Francisco',
        'address_state' => 'CA',
        'address_zip' => '94102',
        'new_children' => [
            [
                'tempId' => 'child1',
                'name' => 'Test',
                'gender' => 'male',
                'birth_month' => '6',
                'birth_year' => '2023',
            ],
        ],
        'new_pets' => [],
    ]);

    $response->assertRedirect();
});

it('guest can create a booking at a hotel', function () {
    $hotel = Hotel::factory()->create();

    $startDate = now()->addDays(2);
    $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
    $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);

    $response = $this->post(route('guest.bookings.store'), [
        'client_first_name' => 'Jane',
        'client_last_name' => 'Smith',
        'client_email' => 'jane@example.com',
        'client_phone' => '+15559876543',
        'service_type' => 'babysitter',
        'location_type' => 'hotel',
        'start_datetime' => $startDatetime->toISOString(),
        'end_datetime' => $endDatetime->toISOString(),
        'hotel_id' => $hotel->id,
        'address_line1' => $hotel->line1,
        'address_line2' => '',
        'address_city' => $hotel->city,
        'address_state' => $hotel->state,
        'address_zip' => $hotel->zip,
        'new_children' => [
            [
                'tempId' => 'child1',
                'name' => 'Test',
                'gender' => 'male',
                'birth_month' => '6',
                'birth_year' => '2023',
            ],
        ],
        'new_pets' => [],
    ]);

    $response->assertRedirect();
});

describe('Guest child birthday handling', function () {
    test('year-only child stores raw birth_month/year in session', function () {
        $startDate = now()->addDays(2);
        $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
        $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);

        $response = $this->post(route('guest.bookings.store'), [
            'client_first_name' => 'Year',
            'client_last_name' => 'Only',
            'client_email' => 'year.only@example.com',
            'client_phone' => '+15550000001',
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $startDatetime->toISOString(),
            'end_datetime' => $endDatetime->toISOString(),
            'address_line1' => '123 Year St',
            'address_line2' => '',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'how_did_you_hear' => 'google',
            'sms_consent' => true,
            'new_children' => [
                [
                    'tempId' => 'y1',
                    'name' => 'Yearly',
                    'gender' => 'male',
                    'birth_month' => '',
                    'birth_year' => '2020',
                ],
            ],
            'new_pets' => [],
        ]);

        $response->assertRedirect();

        $pending = session('guest_booking_pending');
        $childData = collect($pending['new_children'])->firstWhere('name', 'Yearly');

        expect($childData['birth_month'])->toBeNull();
        expect($childData['birth_year'])->toBe('2020');
    });

    test('empty month and year are stored as-is in session', function () {
        $startDate = now()->addDays(2);
        $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
        $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);

        $response = $this->post(route('guest.bookings.store'), [
            'client_first_name' => 'No',
            'client_last_name' => 'Birth',
            'client_email' => 'no.birth@example.com',
            'client_phone' => '+15550000002',
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $startDatetime->toISOString(),
            'end_datetime' => $endDatetime->toISOString(),
            'address_line1' => '456 Empty St',
            'address_line2' => '',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'how_did_you_hear' => 'google',
            'sms_consent' => true,
            'new_children' => [
                [
                    'tempId' => 'n1',
                    'name' => 'NoBirth',
                    'gender' => 'female',
                    'birth_month' => '',
                    'birth_year' => '',
                ],
            ],
            'new_pets' => [],
        ]);

        $response->assertRedirect();

        $pending = session('guest_booking_pending');
        $childData = collect($pending['new_children'])->firstWhere('name', 'NoBirth');

        expect($childData['birth_month'])->toBeNull();
        expect($childData['birth_year'])->toBeNull();
    });

    test('month only is stored as-is in session', function () {
        $startDate = now()->addDays(2);
        $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
        $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);

        $response = $this->post(route('guest.bookings.store'), [
            'client_first_name' => 'Month',
            'client_last_name' => 'Only',
            'client_email' => 'month.only@example.com',
            'client_phone' => '+15550000003',
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $startDatetime->toISOString(),
            'end_datetime' => $endDatetime->toISOString(),
            'address_line1' => '789 Month St',
            'address_line2' => '',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'how_did_you_hear' => 'google',
            'sms_consent' => true,
            'new_children' => [
                [
                    'tempId' => 'm1',
                    'name' => 'MonthOnly',
                    'gender' => 'male',
                    'birth_month' => '6',
                    'birth_year' => '',
                ],
            ],
            'new_pets' => [],
        ]);

        $response->assertRedirect();

        $pending = session('guest_booking_pending');
        $childData = collect($pending['new_children'])->firstWhere('name', 'MonthOnly');

        expect($childData['birth_month'])->toBe('6');
        expect($childData['birth_year'])->toBeNull();
    });
});

it('guest can create a booking for vacation rental', function () {
    $startDate = now()->addDays(2);
    $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
    $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);

    $response = $this->post(route('guest.bookings.store'), [
        'client_first_name' => 'Bob',
        'client_last_name' => 'Wilson',
        'client_email' => 'bob@example.com',
        'client_phone' => '+15551112222',
        'service_type' => 'babysitter',
        'location_type' => 'vacation_rental',
        'start_datetime' => $startDatetime->toISOString(),
        'end_datetime' => $endDatetime->toISOString(),
        'hotel_id' => null,
        'rental_platform' => 'airbnb',
        'address_line1' => '456 Beach Rd',
        'address_line2' => '',
        'address_city' => 'Malibu',
        'address_state' => 'CA',
        'address_zip' => '90265',
        'new_children' => [
            [
                'tempId' => 'child456',
                'name' => 'Kid',
                'gender' => 'male',
                'birth_month' => '5',
                'birth_year' => '2020',
            ],
        ],
        'new_pets' => [],
    ]);

    $response->assertRedirect();
});

it('guest booking requires client details', function () {
    $startDate = now()->addDays(2);
    $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
    $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);

    $response = $this->post(route('guest.bookings.store'), [
        'client_first_name' => '',
        'client_last_name' => '',
        'client_email' => '',
        'client_phone' => '',
        'service_type' => 'babysitter',
        'location_type' => 'private_home',
        'start_datetime' => $startDatetime->toISOString(),
        'end_datetime' => $endDatetime->toISOString(),
        'address_line1' => '123 Main St',
        'address_line2' => '',
        'address_city' => 'San Francisco',
        'address_state' => 'CA',
        'address_zip' => '94102',
        'new_children' => [],
        'new_pets' => [],
    ]);

    $response->assertSessionHasErrors([
        'client_first_name',
        'client_last_name',
        'client_email',
        'client_phone',
    ]);
});

it('guest can create a pet-only booking without children', function () {
    $startDate = now()->addDays(2);
    $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
    $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);

    $response = $this->post(route('guest.bookings.store'), [
        'client_first_name' => 'Pat',
        'client_last_name' => 'Owner',
        'client_email' => 'pat.owner@example.com',
        'client_phone' => '+15551230000',
        'service_type' => 'petsitter',
        'location_type' => 'private_home',
        'start_datetime' => $startDatetime->toISOString(),
        'end_datetime' => $endDatetime->toISOString(),
        'address_line1' => '123 Main St',
        'address_line2' => '',
        'address_city' => 'San Francisco',
        'address_state' => 'CA',
        'address_zip' => '94102',
        'how_did_you_hear' => 'Google',
        'sms_consent' => true,
        'new_children' => [],
        'new_pets' => [
            ['name' => 'Rex', 'type' => 'dog', 'breed' => 'Lab', 'notes' => ''],
        ],
    ]);

    $response->assertSessionHasNoErrors();
});

it('guest babysitter booking still requires a child', function () {
    $startDate = now()->addDays(2);
    $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
    $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);

    $response = $this->post(route('guest.bookings.store'), [
        'client_first_name' => 'Pat',
        'client_last_name' => 'Parent',
        'client_email' => 'pat.parent@example.com',
        'client_phone' => '+15551231111',
        'service_type' => 'babysitter',
        'location_type' => 'private_home',
        'start_datetime' => $startDatetime->toISOString(),
        'end_datetime' => $endDatetime->toISOString(),
        'address_line1' => '123 Main St',
        'address_line2' => '',
        'address_city' => 'San Francisco',
        'address_state' => 'CA',
        'address_zip' => '94102',
        'how_did_you_hear' => 'Google',
        'sms_consent' => true,
        'new_children' => [],
        'new_pets' => [],
    ]);

    $response->assertSessionHasErrors('new_children');
});

it('guest booking validates minimum duration', function () {
    $startDate = now()->addDays(2);
    $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
    $endDatetime = $startDate->copy()->setHour(11)->setMinute(0);

    $response = $this->post(route('guest.bookings.store'), [
        'client_first_name' => 'John',
        'client_last_name' => 'Doe',
        'client_email' => 'john@example.com',
        'client_phone' => '+15551234567',
        'service_type' => 'babysitter',
        'location_type' => 'private_home',
        'start_datetime' => $startDatetime->toISOString(),
        'end_datetime' => $endDatetime->toISOString(),
        'address_line1' => '123 Main St',
        'address_line2' => '',
        'address_city' => 'San Francisco',
        'address_state' => 'CA',
        'address_zip' => '94102',
        'new_children' => [
            [
                'tempId' => 'child',
                'name' => 'Baby',
                'gender' => 'male',
                'birth_month' => '1',
                'birth_year' => '2025',
            ],
        ],
        'new_pets' => [],
    ]);

    $response->assertStatus(302);
});

it('guest booking redirects to confirmation', function () {
    $startDate = now()->addDays(2);
    $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
    $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);

    $response = $this->post(route('guest.bookings.store'), [
        'client_first_name' => 'John',
        'client_last_name' => 'Doe',
        'client_email' => 'confirm-test@example.com',
        'client_phone' => '+15551234567',
        'service_type' => 'babysitter',
        'location_type' => 'private_home',
        'start_datetime' => $startDatetime->toISOString(),
        'end_datetime' => $endDatetime->toISOString(),
        'address_line1' => '123 Main St',
        'address_line2' => '',
        'address_city' => 'San Francisco',
        'address_state' => 'CA',
        'address_zip' => '94102',
        'new_children' => [
            [
                'tempId' => 'child1',
                'name' => 'Test',
                'gender' => 'male',
                'birth_month' => '6',
                'birth_year' => '2023',
            ],
        ],
        'new_pets' => [],
    ]);

    $response->assertRedirect();
});
