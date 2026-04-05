<?php

use App\Models\Availability;
use App\Models\Caregiver;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CaregiverStatusSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CaregiverStatusSeeder::class);
    $this->seed(SpecialtyTypeSeeder::class);
    $this->seed(LocationSeeder::class);
    $this->seed(AttributeDefinitionSeeder::class);
    $this->seed(CertificationTypeSeeder::class);
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->caregiver = Caregiver::factory()->create();
});

describe('AvailabilityController', function () {
    test('guests cannot view availabilities index', function () {
        $response = $this->get(route('availabilities.index'));
        $response->assertRedirect(route('login'));
    });

    test('guests cannot view availability show page', function () {
        $availability = Availability::factory()->create();

        $response = $this->get(route('availabilities.show', $availability));
        $response->assertRedirect(route('login'));
    });

    test('guests cannot update an availability', function () {
        $availability = Availability::factory()->create();

        // The update route uses caregiver_id, not availability_id
        $response = $this->patch(route('availabilities.update', $availability->caregiver_id), [
            'date' => now()->addDays(1)->toDateString(),
            'time_slots' => ['morning'],
        ]);

        $response->assertRedirect(route('login'));
    });

    test('guests cannot delete an availability', function () {
        $availability = Availability::factory()->create();

        // The destroy route uses caregiver_id, not availability_id
        $response = $this->delete(route('availabilities.destroy', $availability->caregiver_id));
        $response->assertRedirect(route('login'));
    });

    test('caregivers CAN view availabilities index (they manage their own availability)', function () {
        $caregiverUser = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($caregiverUser);

        $response = $this->get(route('availabilities.index'));
        $response->assertSuccessful();
    });

    test('caregivers CAN view availability show page for their own caregiver', function () {
        $caregiverUser = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($caregiverUser);

        $availability = Availability::factory()->create();

        $response = $this->get(route('availabilities.show', $availability));
        $response->assertSuccessful();
    });

    test('admin can view availabilities index', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('availabilities.index'));
        $response->assertSuccessful();
    });

    test('admin can view availability show page', function () {
        $this->actingAs($this->user);

        $availability = Availability::factory()->create();

        $response = $this->get(route('availabilities.show', $availability));
        $response->assertSuccessful();
    });

    test('admin can update an availability', function () {
        $this->actingAs($this->user);

        $availability = Availability::factory()->create();

        // The update route uses caregiver_id, not availability_id
        $response = $this->patch(route('availabilities.update', $availability->caregiver_id), [
            'date' => $availability->date->toDateString(),
            'time_slots' => ['morning', 'afternoon'],
            'specific_time' => 'Updated availability',
        ]);

        $response->assertRedirect();

        $availability->refresh();
        expect($availability->time_slots)->toBe(['morning', 'afternoon']);
        expect($availability->specific_time)->toBe('Updated availability');
    });

    test('admin can delete an availability', function () {
        $this->actingAs($this->user);

        $availability = Availability::factory()->create();

        $response = $this->delete(route('availabilities.destroy', $availability));

        $response->assertRedirect();

        $this->assertSoftDeleted('availabilities', [
            'id' => $availability->id,
        ]);
    });
});
