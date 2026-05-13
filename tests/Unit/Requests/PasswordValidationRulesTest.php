<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

uses(RefreshDatabase::class);

test('requires current password', function () {
    $validator = Validator::make(['current_password' => ''], passwordValidationRulesPasswordRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('current_password', $validator->errors()->toArray());
});

test('current password must match', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-password')]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['current_password' => 'wrong-password'],
        passwordValidationRulesPasswordRules()
    );
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('current_password', $validator->errors()->toArray());
});

test('requires new password', function () {
    $validator = Validator::make(['password' => ''], passwordValidationRulesPasswordRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('password', $validator->errors()->toArray());
});

test('password meets minimum requirements', function () {
    $validator = Validator::make(['password' => 'short'], passwordValidationRulesPasswordRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('password', $validator->errors()->toArray());
});

test('password requires confirmation', function () {
    $validator = Validator::make([
        'password' => 'ValidPassword123!',
        'password_confirmation' => 'DifferentPassword123!',
    ], passwordValidationRulesPasswordRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('password', $validator->errors()->toArray());
});

test('passes with valid password', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);
    $this->actingAs($user);

    $validator = Validator::make([
        'current_password' => 'OldPassword123!',
        'password' => 'NewValidPassword123!',
        'password_confirmation' => 'NewValidPassword123!',
    ], passwordValidationRulesPasswordRules());
    $this->assertTrue($validator->passes());
});

function passwordValidationRulesPasswordRules(): array
{
    return [
        'current_password' => ['required', 'string', 'current_password'],
        'password' => ['required', 'string', Password::default(), 'confirmed'],
    ];
}
