<?php

use App\Enums\ServiceType;

describe('ServiceType Enum', function () {
    test('has correct cases', function () {
        expect(ServiceType::cases())->toHaveCount(6);
        expect(ServiceType::Babysitter->value)->toBe('babysitter');
        expect(ServiceType::Petsitter->value)->toBe('petsitter');
        expect(ServiceType::CompanionCare->value)->toBe('companion_care');
        expect(ServiceType::GroupChildcareInvoiced->value)->toBe('group_childcare_invoiced');
        expect(ServiceType::CorporateInvoiced->value)->toBe('corporate_invoiced');
        expect(ServiceType::Comped->value)->toBe('comped');
    });

    test('returns correct labels', function () {
        expect(ServiceType::Babysitter->label())->toBe('Babysitter');
        expect(ServiceType::Petsitter->label())->toBe('Petsitter');
        expect(ServiceType::CompanionCare->label())->toBe('Companion Care');
        expect(ServiceType::GroupChildcareInvoiced->label())->toBe('Group Childcare (Invoiced)');
        expect(ServiceType::CorporateInvoiced->label())->toBe('Corporate (Invoiced)');
        expect(ServiceType::Comped->label())->toBe('Comped');
    });

    test('can be created from value', function () {
        expect(ServiceType::from('babysitter'))->toBe(ServiceType::Babysitter);
        expect(ServiceType::from('petsitter'))->toBe(ServiceType::Petsitter);
        expect(ServiceType::from('companion_care'))->toBe(ServiceType::CompanionCare);
        expect(ServiceType::from('group_childcare_invoiced'))->toBe(ServiceType::GroupChildcareInvoiced);
        expect(ServiceType::from('corporate_invoiced'))->toBe(ServiceType::CorporateInvoiced);
        expect(ServiceType::from('comped'))->toBe(ServiceType::Comped);
    });
});
