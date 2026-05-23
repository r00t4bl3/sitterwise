<?php

use App\Enums\CaregiverStatus;

test('has all expected cases', function () {
    $cases = CaregiverStatus::cases();

    expect($cases)->toHaveCount(8);
    expect(CaregiverStatus::Applicant->value)->toBe('applicant');
    expect(CaregiverStatus::Active->value)->toBe('active');
    expect(CaregiverStatus::Inactive->value)->toBe('inactive');
    expect(CaregiverStatus::InProcess->value)->toBe('in_process');
    expect(CaregiverStatus::NonStarter->value)->toBe('non_starter');
    expect(CaregiverStatus::Fired->value)->toBe('fired');
    expect(CaregiverStatus::Ineligible->value)->toBe('ineligible');
    expect(CaregiverStatus::OnHold->value)->toBe('on_hold');
});

test('returns correct labels', function () {
    expect(CaregiverStatus::Applicant->label())->toBe('Applicant');
    expect(CaregiverStatus::Active->label())->toBe('Active');
    expect(CaregiverStatus::Inactive->label())->toBe('Inactive');
    expect(CaregiverStatus::InProcess->label())->toBe('In Process');
    expect(CaregiverStatus::NonStarter->label())->toBe('Non Starter');
    expect(CaregiverStatus::Fired->label())->toBe('Fired');
    expect(CaregiverStatus::Ineligible->label())->toBe('Ineligible');
    expect(CaregiverStatus::OnHold->label())->toBe('On Hold');
});

test('returns hex colors', function () {
    expect(CaregiverStatus::Applicant->color())->toBe('#F48A91');
    expect(CaregiverStatus::Active->color())->toBe('#22C55E');
    expect(CaregiverStatus::Inactive->color())->toBe('#6B7280');
    expect(CaregiverStatus::InProcess->color())->toBe('#F59E0B');
    expect(CaregiverStatus::NonStarter->color())->toBe('#EF4444');
    expect(CaregiverStatus::Fired->color())->toBe('#DC2626');
    expect(CaregiverStatus::Ineligible->color())->toBe('#991B1B');
    expect(CaregiverStatus::OnHold->color())->toBe('#8B5CF6');
});
