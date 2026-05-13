<?php

use App\Http\Requests\UpdateCaregiverRequest;
use App\Models\CaregiverStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Validator;

uses(RefreshDatabase::class);

function updateCaregiverRequestRules(): array
{
    return (new UpdateCaregiverRequest)->rules();
}

test('requires status id', function () {
    $data = ['status_id' => null];
    $validator = updateCaregiverRequestValidate($data);
    $this->assertFalse($validator->passes());
});

test('requires valid status id', function () {
    $data = ['status_id' => 999];
    $validator = updateCaregiverRequestValidate($data);
    $this->assertFalse($validator->passes());
});

test('valid status id passes', function () {
    $status = CaregiverStatus::factory()->create();
    $data = ['status_id' => $status->id];
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
    $status = CaregiverStatus::factory()->create();
    $data = [
        'status_id' => $status->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => null,
    ];
    $validator = updateCaregiverRequestValidate($data);
    $this->assertTrue($validator->passes());
});

test('rating within range', function () {
    $status = CaregiverStatus::factory()->create();
    $data = [
        'status_id' => $status->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'rating' => 5,
    ];
    $validator = updateCaregiverRequestValidate($data);
    $this->assertTrue($validator->passes());
});

test('rating out of range fails', function () {
    $status = CaregiverStatus::factory()->create();
    $data = [
        'status_id' => $status->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'rating' => 6,
    ];
    $validator = updateCaregiverRequestValidate($data);
    $this->assertFalse($validator->passes());
});

test('profile photo must be image', function () {
    $status = CaregiverStatus::factory()->create();
    $data = [
        'status_id' => $status->id,
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
