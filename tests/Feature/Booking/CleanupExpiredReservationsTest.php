<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

describe('Cleanup Expired Reservations Command', function () {
    test('releases expired reservations back to received status', function () {
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Care',
            'slug' => 'test-care',
            'phone' => '6195550100',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'date_of_birth' => '2000-01-01',
        ]);
        $client = Client::factory()->create();

        $expiredReserved = Booking::factory()->forClient($client)->create([
            'status' => 'reserved',
            'reserved_by' => $caregiver->id,
            'reservation_expires_at' => now()->subHour(),
        ]);

        $stillReserved = Booking::factory()->forClient($client)->create([
            'status' => 'reserved',
            'reserved_by' => $caregiver->id,
            'reservation_expires_at' => now()->addHour(),
        ]);

        $received = Booking::factory()->forClient($client)->create([
            'status' => BookingStatus::Received->value,
            'reserved_by' => null,
            'reservation_expires_at' => null,
        ]);

        artisan('bookings:cleanup-expired-reservations')->assertSuccessful();

        $expiredReserved->refresh();
        $stillReserved->refresh();
        $received->refresh();

        expect($expiredReserved->status)->toBe(BookingStatus::Received->value);
        expect($expiredReserved->reserved_by)->toBeNull();
        expect($expiredReserved->reservation_expires_at)->toBeNull();

        expect($stillReserved->status)->toBe('reserved');
        expect($received->status)->toBe(BookingStatus::Received->value);
    });

    test('does not affect confirmed or completed bookings', function () {
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Care',
            'slug' => 'test-care-2',
            'phone' => '6195550199',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'date_of_birth' => '2000-01-01',
        ]);
        $client = Client::factory()->create();

        $confirmed = Booking::factory()->forClient($client)->create([
            'status' => BookingStatus::Confirmed->value,
            'reserved_by' => $caregiver->id,
            'reservation_expires_at' => now()->subHour(),
        ]);

        $completed = Booking::factory()->forClient($client)->create([
            'status' => BookingStatus::Completed->value,
            'reserved_by' => $caregiver->id,
            'reservation_expires_at' => now()->subDay(),
        ]);

        artisan('bookings:cleanup-expired-reservations')->assertSuccessful();

        $confirmed->refresh();
        $completed->refresh();

        expect($confirmed->status)->toBe(BookingStatus::Confirmed->value);
        expect($completed->status)->toBe(BookingStatus::Completed->value);
    });

    test('does not affect cancelled bookings', function () {
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Care',
            'slug' => 'test-care-3',
            'phone' => '6195550188',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'date_of_birth' => '2000-01-01',
        ]);
        $client = Client::factory()->create();

        $cancelled = Booking::factory()->forClient($client)->create([
            'status' => BookingStatus::Cancelled->value,
            'reserved_by' => $caregiver->id,
            'reservation_expires_at' => now()->subDay(),
        ]);

        artisan('bookings:cleanup-expired-reservations')->assertSuccessful();

        $cancelled->refresh();
        expect($cancelled->status)->toBe(BookingStatus::Cancelled->value);
    });
});
