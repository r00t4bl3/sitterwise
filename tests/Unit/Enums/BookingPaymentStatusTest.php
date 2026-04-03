<?php

use App\Enums\BookingPaymentStatus;

describe('BookingPaymentStatus Enum', function () {
    test('has correct cases', function () {
        expect(BookingPaymentStatus::cases())->toHaveCount(4);
        expect(BookingPaymentStatus::Pending->value)->toBe('pending');
        expect(BookingPaymentStatus::Paid->value)->toBe('paid');
        expect(BookingPaymentStatus::Failed->value)->toBe('failed');
        expect(BookingPaymentStatus::Refunded->value)->toBe('refunded');
    });

    test('returns correct labels', function () {
        expect(BookingPaymentStatus::Pending->label())->toBe('Pending');
        expect(BookingPaymentStatus::Paid->label())->toBe('Paid');
        expect(BookingPaymentStatus::Failed->label())->toBe('Failed');
        expect(BookingPaymentStatus::Refunded->label())->toBe('Refunded');
    });

    test('can be created from value', function () {
        expect(BookingPaymentStatus::from('pending'))->toBe(BookingPaymentStatus::Pending);
        expect(BookingPaymentStatus::from('paid'))->toBe(BookingPaymentStatus::Paid);
        expect(BookingPaymentStatus::from('failed'))->toBe(BookingPaymentStatus::Failed);
        expect(BookingPaymentStatus::from('refunded'))->toBe(BookingPaymentStatus::Refunded);
    });
});
