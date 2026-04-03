<?php

use App\Enums\SubmissionType;

describe('SubmissionType Enum', function () {
    test('has correct cases', function () {
        expect(SubmissionType::cases())->toHaveCount(3);
        expect(SubmissionType::Guest->value)->toBe('guest');
        expect(SubmissionType::LoggedIn->value)->toBe('logged_in');
        expect(SubmissionType::Admin->value)->toBe('admin');
    });

    test('returns correct labels', function () {
        expect(SubmissionType::Guest->label())->toBe('Guest');
        expect(SubmissionType::LoggedIn->label())->toBe('Logged In');
        expect(SubmissionType::Admin->label())->toBe('Admin');
    });

    test('can be created from value', function () {
        expect(SubmissionType::from('guest'))->toBe(SubmissionType::Guest);
        expect(SubmissionType::from('logged_in'))->toBe(SubmissionType::LoggedIn);
        expect(SubmissionType::from('admin'))->toBe(SubmissionType::Admin);
    });
});
