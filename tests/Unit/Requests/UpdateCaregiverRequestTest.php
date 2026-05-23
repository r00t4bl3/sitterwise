<?php

use App\Enums\CaregiverStatus;
use App\Http\Requests\UpdateCaregiverRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Validator;

uses(RefreshDatabase::class);

function updateCaregiverRequestRules(): array
{
    return (new UpdateCaregiverRequest)->rules();
}

test('requires status', function () {
    $data = ['status' => null];
    $validator = updateCaregiverRequestValidate($data);
    $this->assertFalse($validator->passes());
});

test('requires valid status', function () {
    $data = ['status' => 'invalid_status'];
    $validator = updateCaregiverRequestValidate($data);
    $this->assertFalse($validator->passes());
});

test('valid status passes', function () {
    $data = ['status' => CaregiverStatus::Active->value];
    $validator = updateCaregiverRequestValidate($data);
    $this->assertTrue($validator->passes());
});

test('first name requires last name when present', function () {
    $data = [
        'first_name' => 'John',
        'last_name' => '',
    ];
    $validator = updateCaregiverRequestValidate($data);
    $this->assertFalse($validator->passes());
});

test('phone is nullable', function () {
    $data = [
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => null,
    ];
    $validator = updateCaregiverRequestValidate($data);
    $this->assertTrue($validator->passes());
});

test('rating within range', function () {
    $data = [
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'rating' => 5,
    ];
    $validator = updateCaregiverRequestValidate($data);
    $this->assertTrue($validator->passes());
});

test('rating out of range fails', function () {
    $data = [
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'rating' => 6,
    ];
    $validator = updateCaregiverRequestValidate($data);
    $this->assertFalse($validator->passes());
});

test('profile photo must be image', function () {
    $data = [
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'profile_photo' => 'not-an-image',
    ];
    $validator = updateCaregiverRequestValidate($data);
    $this->assertFalse($validator->passes());
});

function updateCaregiverRequestValidate(array $data): Validator
{
    return Illuminate\Support\Facades\Validator::make($data, updateCaregiverRequestRules());
}
