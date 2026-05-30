<?php

use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Hotel;
use App\Models\Traits\Phone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

describe('normalizePhone', function () {
    it('normalizes 10-digit US number', function () {
        expect(Phone::normalizePhone('6195551212'))->toBe('+16195551212');
    });

    it('normalizes formatted US number', function () {
        expect(Phone::normalizePhone('(619) 555-1212'))->toBe('+16195551212');
    });

    it('normalizes dot-separated US number', function () {
        expect(Phone::normalizePhone('619.555.1212'))->toBe('+16195551212');
    });

    it('normalizes 11-digit number starting with 1', function () {
        expect(Phone::normalizePhone('16195551212'))->toBe('+16195551212');
    });

    it('normalizes 11-digit formatted number starting with 1', function () {
        expect(Phone::normalizePhone('1 (619) 555-1212'))->toBe('+16195551212');
    });

    it('normalizes international number with plus', function () {
        expect(Phone::normalizePhone('+447900123456'))->toBe('+447900123456');
    });

    it('normalizes international number without plus', function () {
        expect(Phone::normalizePhone('447900123456'))->toBe('+447900123456');
    });

    it('returns null for null input', function () {
        expect(Phone::normalizePhone(null))->toBeNull();
    });

    it('returns empty string for empty input', function () {
        expect(Phone::normalizePhone(''))->toBe('');
    });

    it('normalizes already E.164 US number unchanged', function () {
        expect(Phone::normalizePhone('+16195551212'))->toBe('+16195551212');
    });

    it('normalizes short number by prepending plus', function () {
        expect(Phone::normalizePhone('555'))->toBe('+555');
    });
});

describe('Phone mutator on models', function () {
    it('normalizes phone on Caregiver creation', function () {
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '(619) 555-1212',
            'status' => 'active',
        ]);

        expect($caregiver->phone)->toBe('+16195551212');
        assertDatabaseHas('caregivers', ['id' => $caregiver->id, 'phone' => '+16195551212']);
    });

    it('normalizes phone on Client creation', function () {
        $user = User::factory()->create(['role' => 'client']);
        $client = Client::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '6195551212',
        ]);

        expect($client->phone)->toBe('+16195551212');
        assertDatabaseHas('clients', ['id' => $client->id, 'phone' => '+16195551212']);
    });

    it('normalizes client_phone on Booking creation', function () {
        $client = Client::factory()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'client_phone' => '(619) 555-1212',
        ]);

        expect($booking->client_phone)->toBe('+16195551212');
        assertDatabaseHas('bookings', ['id' => $booking->id, 'client_phone' => '+16195551212']);
    });

    it('normalizes contact_phone on Hotel creation', function () {
        $hotel = Hotel::factory()->create(['contact_phone' => '619.555.1212']);

        expect($hotel->contact_phone)->toBe('+16195551212');
        assertDatabaseHas('hotels', ['id' => $hotel->id, 'contact_phone' => '+16195551212']);
    });

    it('normalizes phone on Caregiver update', function () {
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '+16195551212',
            'status' => 'active',
        ]);

        $caregiver->update(['phone' => '(858) 123-4567']);

        expect($caregiver->fresh()->phone)->toBe('+18581234567');
    });

    it('stores empty string as-is', function () {
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '',
            'status' => 'active',
        ]);

        expect($caregiver->phone)->toBe('');
    });

    it('stores null as-is', function () {
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => null,
            'status' => 'active',
        ]);

        expect($caregiver->phone)->toBeNull();
    });
});
