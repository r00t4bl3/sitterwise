<?php

use App\Http\Resources\CaregiverResource;
use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaregiverResourceTest extends TestCase
{
    use RefreshDatabase;

    private function createCaregiver(array $overrides = []): Caregiver
    {
        $status = CaregiverStatus::factory()->create();
        $user = User::factory()->create();

        return Caregiver::create(array_merge([
            'user_id' => $user->id,
            'status_id' => $status->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ], $overrides));
    }

    public function test_resolve_includes_required_fields()
    {
        $caregiver = $this->createCaregiver();

        $resource = new CaregiverResource($caregiver);
        $array = $resource->resolve();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('first_name', $array);
        $this->assertArrayHasKey('last_name', $array);
        $this->assertArrayHasKey('email', $array);
    }

    public function test_resolve_includes_status_and_specialties()
    {
        $caregiver = $this->createCaregiver();

        $resource = new CaregiverResource($caregiver);
        $array = $resource->resolve();

        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('specialty_types', $array);
    }

    public function test_date_of_birth_is_formatted()
    {
        $caregiver = $this->createCaregiver(['date_of_birth' => '1990-01-15']);

        $resource = new CaregiverResource($caregiver);
        $array = $resource->resolve();

        $this->assertEquals('January 15, 1990', $array['date_of_birth']);
    }

    public function test_locations_key_exists()
    {
        $caregiver = $this->createCaregiver();

        $resource = new CaregiverResource($caregiver);
        $array = $resource->resolve();

        $this->assertArrayHasKey('locations', $array);
    }
}
