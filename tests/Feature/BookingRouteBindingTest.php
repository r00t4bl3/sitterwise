<?php

use App\Models\Booking;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Booking Route Binding', function () {
    test('app service provider binder resolves booking by integer id', function () {
        $booking = Booking::factory()->create();

        $resolved = (new Booking)->resolveRouteBinding($booking->id);

        expect($resolved->id)->toEqual($booking->id);
        expect($resolved->ulid)->toEqual($booking->ulid);
    });

    test('app service provider binder resolves booking by ulid', function () {
        $booking = Booking::factory()->create();

        $resolved = (new Booking)->resolveRouteBinding($booking->ulid);

        expect($resolved->id)->toEqual($booking->id);
        expect($resolved->ulid)->toEqual($booking->ulid);
    });

    test('app service provider binder throws exception for invalid id', function () {
        expect(fn () => (new Booking)->resolveRouteBinding(999999))
            ->toThrow(ModelNotFoundException::class);
    });

    test('app service provider binder throws exception for invalid ulid', function () {
        expect(fn () => (new Booking)->resolveRouteBinding('invalid-ulid'))
            ->toThrow(ModelNotFoundException::class);
    });
});
