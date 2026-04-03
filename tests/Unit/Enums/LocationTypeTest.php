<?php

use App\Enums\LocationType;

describe('LocationType Enum', function () {
    test('has correct cases', function () {
        expect(LocationType::cases())->toHaveCount(4);
        expect(LocationType::Hotel->value)->toBe('hotel');
        expect(LocationType::PrivateHome->value)->toBe('private_home');
        expect(LocationType::VacationRental->value)->toBe('vacation_rental');
        expect(LocationType::EventVenue->value)->toBe('event_venue');
    });

    test('returns correct labels', function () {
        expect(LocationType::Hotel->label())->toBe('Hotel');
        expect(LocationType::PrivateHome->label())->toBe('Private Home');
        expect(LocationType::VacationRental->label())->toBe('Vacation Rental');
        expect(LocationType::EventVenue->label())->toBe('Event Venue');
    });

    test('can be created from value', function () {
        expect(LocationType::from('hotel'))->toBe(LocationType::Hotel);
        expect(LocationType::from('private_home'))->toBe(LocationType::PrivateHome);
        expect(LocationType::from('vacation_rental'))->toBe(LocationType::VacationRental);
        expect(LocationType::from('event_venue'))->toBe(LocationType::EventVenue);
    });
});
