<?php

use App\Models\CaregiverStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreCaregiverRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_data()
    {
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

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_requires_first_name()
    {
        $validator = Validator::make(['first_name' => ''], $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('first_name', $validator->errors()->toArray());
    }

    public function test_requires_last_name()
    {
        $validator = Validator::make(['last_name' => ''], $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('last_name', $validator->errors()->toArray());
    }

    public function test_requires_valid_email()
    {
        $validator = Validator::make(['email' => 'not-an-email'], $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_requires_unique_email()
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $validator = Validator::make(['email' => 'existing@example.com'], $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_requires_status_id()
    {
        $validator = Validator::make(['status_id' => null], $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('status_id', $validator->errors()->toArray());
    }

    public function test_requires_status_id_to_exist()
    {
        $validator = Validator::make(['status_id' => 999], $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('status_id', $validator->errors()->toArray());
    }

    public function test_requires_password()
    {
        $validator = Validator::make(['password' => ''], $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_requires_password_minimum_length()
    {
        $validator = Validator::make(['password' => 'short', 'password_confirmation' => 'short'], $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_requires_password_confirmation()
    {
        $validator = Validator::make(['password' => 'password123', 'password_confirmation' => 'different'], $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_phone_is_nullable()
    {
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

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_address_is_nullable()
    {
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

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_date_of_birth_is_nullable()
    {
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

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_biography_is_nullable()
    {
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

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_notes_is_nullable()
    {
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

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    private function rules(): array
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
}
