<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

uses(RefreshDatabase::class);

test('requires name', function () {
    $validator = Validator::make(['name' => ''], profileValidationRulesProfileRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('name', $validator->errors()->toArray());
});

test('name max length', function () {
    $validator = Validator::make(['name' => str_repeat('a', 256)], profileValidationRulesProfileRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('name', $validator->errors()->toArray());
});

test('requires email', function () {
    $validator = Validator::make(['email' => ''], profileValidationRulesProfileRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('email', $validator->errors()->toArray());
});

test('requires valid email format', function () {
    $validator = Validator::make(['email' => 'not-an-email'], profileValidationRulesProfileRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('email', $validator->errors()->toArray());
});

test('email max length', function () {
    $validator = Validator::make(['email' => str_repeat('a', 256).'@example.com'], profileValidationRulesProfileRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('email', $validator->errors()->toArray());
});

test('email must be unique', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $validator = Validator::make(['email' => 'existing@example.com'], profileValidationRulesProfileRules());
    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('email', $validator->errors()->toArray());
});

test('email unique ignores current user', function () {
    $user = User::factory()->create(['email' => 'myemail@example.com']);

    // Verify user exists
    $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'myemail@example.com']);

    $validator = Validator::make(
        ['name' => 'Test User', 'email' => 'myemail@example.com'],
        profileValidationRulesProfileRules($user->id)
    );
    $this->assertTrue($validator->passes(), 'Validation errors: '.json_encode($validator->errors()->toArray()));
});

test('passes with valid data', function () {
    $validator = Validator::make([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ], profileValidationRulesProfileRules());
    $this->assertTrue($validator->passes());
});

function profileValidationRulesProfileRules(?int $userId = null): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ],
    ];
}
