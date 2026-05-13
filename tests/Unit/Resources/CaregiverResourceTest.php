<?php

use App\Http\Resources\CaregiverResource;
use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function caregiverResourceCreateCaregiver(array $overrides = []): Caregiver
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

test('resolve includes required fields', function () {
    $caregiver = caregiverResourceCreateCaregiver();

    $resource = new CaregiverResource($caregiver);
    $array = $resource->resolve();

    $this->assertArrayHasKey('id', $array);
    $this->assertArrayHasKey('first_name', $array);
    $this->assertArrayHasKey('last_name', $array);
    $this->assertArrayHasKey('email', $array);
});

test('resolve includes status and specialties', function () {
    $caregiver = caregiverResourceCreateCaregiver();

    $resource = new CaregiverResource($caregiver);
    $array = $resource->resolve();

    $this->assertArrayHasKey('status', $array);
    $this->assertArrayHasKey('specialty_types', $array);
});

test('date of birth is formatted', function () {
    $caregiver = caregiverResourceCreateCaregiver(['date_of_birth' => '1990-01-15']);

    $resource = new CaregiverResource($caregiver);
    $array = $resource->resolve();

    $this->assertEquals('January 15, 1990', $array['date_of_birth']);
});

test('locations key exists', function () {
    $caregiver = caregiverResourceCreateCaregiver();

    $resource = new CaregiverResource($caregiver);
    $array = $resource->resolve();

    $this->assertArrayHasKey('locations', $array);
});
