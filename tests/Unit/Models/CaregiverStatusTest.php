<?php

use App\Enums\CaregiverStatus;

test('has all expected cases', function () {
    $cases = CaregiverStatus::cases();

    expect($cases)->toHaveCount(11);
    expect(CaregiverStatus::Applicant->value)->toBe('applicant');
    expect(CaregiverStatus::UnderReview->value)->toBe('under_review');
    expect(CaregiverStatus::InterviewScheduled->value)->toBe('interview_scheduled');
    expect(CaregiverStatus::BackgroundCheck->value)->toBe('background_check');
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
    expect(CaregiverStatus::UnderReview->label())->toBe('Under Review');
    expect(CaregiverStatus::InterviewScheduled->label())->toBe('Interview Scheduled');
    expect(CaregiverStatus::BackgroundCheck->label())->toBe('Background Check');
    expect(CaregiverStatus::Active->label())->toBe('Active');
    expect(CaregiverStatus::Inactive->label())->toBe('Inactive');
    expect(CaregiverStatus::InProcess->label())->toBe('In Process');
    expect(CaregiverStatus::NonStarter->label())->toBe('Non Starter');
    expect(CaregiverStatus::Fired->label())->toBe('Fired');
    expect(CaregiverStatus::Ineligible->label())->toBe('Ineligible');
    expect(CaregiverStatus::OnHold->label())->toBe('On Hold');
});

test('returns terminal statuses', function () {
    $terminal = CaregiverStatus::terminal();
    expect($terminal)->toHaveCount(5);
    expect($terminal)->toContain(CaregiverStatus::Active);
    expect($terminal)->toContain(CaregiverStatus::Inactive);
    expect($terminal)->toContain(CaregiverStatus::NonStarter);
    expect($terminal)->toContain(CaregiverStatus::Fired);
    expect($terminal)->toContain(CaregiverStatus::Ineligible);
});

test('toArray returns all statuses with correct structure', function () {
    $statuses = CaregiverStatus::toArray();

    expect($statuses)->toHaveCount(11);

    foreach ($statuses as $item) {
        expect($item)->toHaveKeys(['value', 'label', 'color', 'is_terminal']);
        expect($item['value'])->toBeString();
        expect($item['label'])->toBeString();
        expect($item['color'])->toMatch('/^#[0-9A-Fa-f]{6}$/');
        expect($item['is_terminal'])->toBeBool();
    }

    $terminal = array_filter($statuses, fn ($s) => $s['is_terminal']);
    expect($terminal)->toHaveCount(5);

    $terminalValues = array_map(fn ($s) => $s['value'], $terminal);
    expect($terminalValues)->toContain('active', 'inactive', 'non_starter', 'fired', 'ineligible');
});

test('returns hex colors', function () {
    expect(CaregiverStatus::Applicant->color())->toBe('#F48A91');
    expect(CaregiverStatus::UnderReview->color())->toBe('#F59E0B');
    expect(CaregiverStatus::InterviewScheduled->color())->toBe('#8B5CF6');
    expect(CaregiverStatus::BackgroundCheck->color())->toBe('#3B82F6');
    expect(CaregiverStatus::Active->color())->toBe('#22C55E');
    expect(CaregiverStatus::Inactive->color())->toBe('#6B7280');
    expect(CaregiverStatus::InProcess->color())->toBe('#F59E0B');
    expect(CaregiverStatus::NonStarter->color())->toBe('#EF4444');
    expect(CaregiverStatus::Fired->color())->toBe('#DC2626');
    expect(CaregiverStatus::Ineligible->color())->toBe('#991B1B');
    expect(CaregiverStatus::OnHold->color())->toBe('#8B5CF6');
});
