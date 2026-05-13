<?php

use App\Models\CaregiverStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

test('passes with valid data', function () {
    $status = CaregiverStatus::factory()->create();

    $data = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '123-456-7890',
        'address' => '123 Test St',
        'date_of_birth' => '1990-01-01',
        'status_id' => $status->id,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'biography' => 'Experienced caregiver',
        'notes' => 'Some notes',
    ];

    $validator = Validator::make($data, storeCaregiverRequestRules());

    $this->assertTrue($validator->passes());
});

test('requires first name', function () {
    $validator = Validator::make(['first_name' => ''], storeCaregiverRequestRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('first_name', $validator->errors()->toArray());
});

test('requires last name', function () {
    $validator = Validator::make(['last_name' => ''], storeCaregiverRequestRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('last_name', $validator->errors()->toArray());
});

test('requires valid email', function () {
    $validator = Validator::make(['email' => 'not-an-email'], storeCaregiverRequestRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('email', $validator->errors()->toArray());
});

test('requires unique email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $validator = Validator::make(['email' => 'existing@example.com'], storeCaregiverRequestRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('email', $validator->errors()->toArray());
});

test('requires status id', function () {
    $validator = Validator::make(['status_id' => null], storeCaregiverRequestRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('status_id', $validator->errors()->toArray());
});

test('requires status id to exist', function () {
    $validator = Validator::make(['status_id' => 999], storeCaregiverRequestRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('status_id', $validator->errors()->toArray());
});

test('requires password', function () {
    $validator = Validator::make(['password' => ''], storeCaregiverRequestRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('password', $validator->errors()->toArray());
});

test('requires password minimum length', function () {
    $validator = Validator::make(['password' => 'short', 'password_confirmation' => 'short'], storeCaregiverRequestRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('password', $validator->errors()->toArray());
});

test('requires password confirmation', function () {
    $validator = Validator::make(['password' => 'password123', 'password_confirmation' => 'different'], storeCaregiverRequestRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('password', $validator->errors()->toArray());
});

test('phone is nullable', function () {
    $status = CaregiverStatus::factory()->create();
    $data = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'status_id' => $status->id,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone' => null,
    ];

    $validator = Validator::make($data, storeCaregiverRequestRules());
    $this->assertTrue($validator->passes());
});

test('address is nullable', function () {
    $status = CaregiverStatus::factory()->create();
    $data = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'status_id' => $status->id,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'address' => null,
    ];

    $validator = Validator::make($data, storeCaregiverRequestRules());
    $this->assertTrue($validator->passes());
});

test('date of birth is nullable', function () {
    $status = CaregiverStatus::factory()->create();
    $data = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'status_id' => $status->id,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'date_of_birth' => null,
    ];

    $validator = Validator::make($data, storeCaregiverRequestRules());
    $this->assertTrue($validator->passes());
});

test('biography is nullable', function () {
    $status = CaregiverStatus::factory()->create();
    $data = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'status_id' => $status->id,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'biography' => null,
    ];

    $validator = Validator::make($data, storeCaregiverRequestRules());
    $this->assertTrue($validator->passes());
});

test('notes is nullable', function () {
    $status = CaregiverStatus::factory()->create();
    $data = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'status_id' => $status->id,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'notes' => null,
    ];

    $validator = Validator::make($data, storeCaregiverRequestRules());
    $this->assertTrue($validator->passes());
});

function storeCaregiverRequestRules(): array
{
    return [
        'first_name' => ['required', 'string', 'max:255'],
        'last_name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'phone' => ['nullable', 'string', 'max:20'],
        'address' => ['nullable', 'string', 'max:500'],
        'date_of_birth' => ['nullable', 'date'],
        'status_id' => ['required', 'exists:caregiver_statuses,id'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
        'biography' => ['nullable', 'string'],
        'notes' => ['nullable', 'string'],
    ];
}
