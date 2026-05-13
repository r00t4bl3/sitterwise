<?php

use App\Models\Booking;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Booking ULID', function () {
    test('a booking has a ulid generated upon creation', function () {
        $booking = Booking::factory()->create();

        expect($booking->ulid)->not->toBeNull();
        expect((string) $booking->ulid)->toHaveLength(26);
        expect((string) $booking->ulid)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
    });

    test('a booking can be found by tests integer id', function () {
        $booking = Booking::factory()->create();

        $foundById = Booking::find($booking->id);

        expect($foundById)->not->toBeNull();
        expect($foundById->id)->toEqual($booking->id);
        expect($foundById->ulid)->toEqual($booking->ulid);
    });

    test('a booking can be found by tests ulid', function () {
        $booking = Booking::factory()->create();

        $foundByUlid = Booking::where('ulid', $booking->ulid)->first();

        expect($foundByUlid)->not->toBeNull();
        expect($foundByUlid->id)->toEqual($booking->id);
        expect($foundByUlid->ulid)->toEqual($booking->ulid);
    });

    test('two bookings have different ulids', function () {
        $booking1 = Booking::factory()->create();
        $booking2 = Booking::factory()->create();

        expect((string) $booking1->ulid)->not->toEqual((string) $booking2->ulid);
    });

    test('custom binder resolves booking by integer id', function () {
        $booking = Booking::factory()->create();

        $binder = function ($value) {
            return Booking::where('id', $value)
                ->orWhere('ulid', $value)
                ->firstOrFail();
        };

        $resolved = $binder($booking->id);

        expect($resolved->id)->toEqual($booking->id);
        expect($resolved->ulid)->toEqual($booking->ulid);
    });

    test('custom binder resolves booking by ulid', function () {
        $booking = Booking::factory()->create();

        $binder = function ($value) {
            return Booking::where('id', $value)
                ->orWhere('ulid', $value)
                ->firstOrFail();
        };

        $resolved = $binder($booking->ulid);

        expect($resolved->id)->toEqual($booking->id);
        expect($resolved->ulid)->toEqual($booking->ulid);
    });

    test('custom binder throws exception for invalid id', function () {
        $binder = function ($value) {
            return Booking::where('id', $value)
                ->orWhere('ulid', $value)
                ->firstOrFail();
        };

        expect(fn () => $binder(999999))->toThrow(ModelNotFoundException::class);
    });

    test('custom binder throws exception for invalid ulid', function () {
        $binder = function ($value) {
            return Booking::where('id', $value)
                ->orWhere('ulid', $value)
                ->firstOrFail();
        };

        expect(fn () => $binder('invalid-ulid'))->toThrow(ModelNotFoundException::class);
    });
});
