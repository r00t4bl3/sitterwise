<?php

use App\Enums\BookingStatus;

describe('BookingStatus Enum', function () {
    test('has correct cases', function () {
        expect(BookingStatus::cases())->toHaveCount(5);
        expect(BookingStatus::Received->value)->toBe('received');
        expect(BookingStatus::Pending->value)->toBe('pending');
        expect(BookingStatus::Confirmed->value)->toBe('confirmed');
        expect(BookingStatus::Completed->value)->toBe('completed');
        expect(BookingStatus::Cancelled->value)->toBe('cancelled');
    });

    test('returns correct labels', function () {
        expect(BookingStatus::Received->label())->toBe('Received');
        expect(BookingStatus::Pending->label())->toBe('Pending');
        expect(BookingStatus::Confirmed->label())->toBe('Confirmed');
        expect(BookingStatus::Completed->label())->toBe('Completed');
        expect(BookingStatus::Cancelled->label())->toBe('Cancelled');
    });

    test('can be created from value', function () {
        expect(BookingStatus::from('received'))->toBe(BookingStatus::Received);
        expect(BookingStatus::from('pending'))->toBe(BookingStatus::Pending);
        expect(BookingStatus::from('confirmed'))->toBe(BookingStatus::Confirmed);
        expect(BookingStatus::from('completed'))->toBe(BookingStatus::Completed);
        expect(BookingStatus::from('cancelled'))->toBe(BookingStatus::Cancelled);
    });
});
