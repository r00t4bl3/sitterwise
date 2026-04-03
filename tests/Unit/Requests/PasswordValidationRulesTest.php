<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class PasswordValidationRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_current_password()
    {
        $validator = Validator::make(['current_password' => ''], $this->passwordRules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('current_password', $validator->errors()->toArray());
    }

    public function test_current_password_must_match()
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->actingAs($user);

        $validator = Validator::make(
            ['current_password' => 'wrong-password'],
            $this->passwordRules()
        );
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('current_password', $validator->errors()->toArray());
    }

    public function test_requires_new_password()
    {
        $validator = Validator::make(['password' => ''], $this->passwordRules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_password_meets_minimum_requirements()
    {
        $validator = Validator::make(['password' => 'short'], $this->passwordRules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_password_requires_confirmation()
    {
        $validator = Validator::make([
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ], $this->passwordRules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_passes_with_valid_password()
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);
        $this->actingAs($user);

        $validator = Validator::make([
            'current_password' => 'OldPassword123!',
            'password' => 'NewValidPassword123!',
            'password_confirmation' => 'NewValidPassword123!',
        ], $this->passwordRules());
        $this->assertTrue($validator->passes());
    }

    private function passwordRules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ];
    }
}
