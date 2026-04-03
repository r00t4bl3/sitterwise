<?php

use App\Enums\TimeSlot;

describe('TimeSlot Enum', function () {
    test('has correct cases', function () {
        expect(TimeSlot::cases())->toHaveCount(3);
        expect(TimeSlot::Morning->value)->toBe('morning');
        expect(TimeSlot::Afternoon->value)->toBe('afternoon');
        expect(TimeSlot::Evening->value)->toBe('evening');
    });

    test('returns correct labels', function () {
        expect(TimeSlot::Morning->label())->toBe('Morning');
        expect(TimeSlot::Afternoon->label())->toBe('Afternoon');
        expect(TimeSlot::Evening->label())->toBe('Evening');
    });

    test('can be created from value', function () {
        expect(TimeSlot::from('morning'))->toBe(TimeSlot::Morning);
        expect(TimeSlot::from('afternoon'))->toBe(TimeSlot::Afternoon);
        expect(TimeSlot::from('evening'))->toBe(TimeSlot::Evening);
    });
});
