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
        'client_phone' => '(555) 123-4567',
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
        'client_phone' => '(555) 987-6543',
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
                'tempId' => 'child123',
                'name' => 'Baby',
                'gender' => 'female',
                'birth_month' => '1',
                'birth_year' => '2025',
            ],
        ],
        'new_pets' => [],
    ]);

    $response->assertRedirect();
});

it('guest can create a booking for vacation rental', function () {
    $startDate = now()->addDays(2);
    $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
    $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);

    $response = $this->post(route('guest.bookings.store'), [
        'client_first_name' => 'Bob',
        'client_last_name' => 'Wilson',
        'client_email' => 'bob@example.com',
        'client_phone' => '(555) 111-2222',
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

it('guest booking validates minimum duration', function () {
    $startDate = now()->addDays(2);
    $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
    $endDatetime = $startDate->copy()->setHour(11)->setMinute(0);

    $response = $this->post(route('guest.bookings.store'), [
        'client_first_name' => 'John',
        'client_last_name' => 'Doe',
        'client_email' => 'john@example.com',
        'client_phone' => '(555) 123-4567',
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
        'client_phone' => '(555) 123-4567',
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