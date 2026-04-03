<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Tests\TestCase;

class ProfileValidationRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_name()
    {
        $validator = Validator::make(['name' => ''], $this->profileRules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_name_max_length()
    {
        $validator = Validator::make(['name' => str_repeat('a', 256)], $this->profileRules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_requires_email()
    {
        $validator = Validator::make(['email' => ''], $this->profileRules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_requires_valid_email_format()
    {
        $validator = Validator::make(['email' => 'not-an-email'], $this->profileRules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_email_max_length()
    {
        $validator = Validator::make(['email' => str_repeat('a', 256).'@example.com'], $this->profileRules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_email_must_be_unique()
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $validator = Validator::make(['email' => 'existing@example.com'], $this->profileRules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_email_unique_ignores_current_user()
    {
        $user = User::factory()->create(['email' => 'myemail@example.com']);

        // Verify user exists
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'myemail@example.com']);

        $validator = Validator::make(
            ['name' => 'Test User', 'email' => 'myemail@example.com'],
            $this->profileRules($user->id)
        );
        $this->assertTrue($validator->passes(), 'Validation errors: '.json_encode($validator->errors()->toArray()));
    }

    public function test_passes_with_valid_data()
    {
        $validator = Validator::make([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ], $this->profileRules());
        $this->assertTrue($validator->passes());
    }

    private function profileRules(?int $userId = null): array
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
}
