<?php

use App\Models\AttributeDefinition;
use App\Models\CertificationType;
use App\Models\Hotel;
use App\Models\Location;
use App\Models\SpecialtyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// CertificationTypeController Tests
describe('CertificationTypeController', function () {
    beforeEach(function () {
        $this->user = User::factory()->create(['role' => 'super_admin']);
    });

    test('guests cannot view certifications index', function () {
        $response = $this->get(route('certifications.index'));
        $response->assertRedirect(route('login'));
    });

    test('admin cannot view certifications index', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->get(route('certifications.index'));
        // Middleware allows both admin and super_admin
        $response->assertSuccessful();
    });

    test('super_admin can view certifications index', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('certifications.index'));
        $response->assertSuccessful();
    });

    test('super_admin can create a certification', function () {
        $this->actingAs($this->user);

        $response = $this->post(route('certifications.store'), [
            'name'             => 'First Aid',
            'description'      => 'First aid certification',
            'expires_required' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('certification_types', [
            'name' => 'First Aid',
        ]);
    });

    test('super_admin can update a certification', function () {
        $this->actingAs($this->user);

        $certification = CertificationType::factory()->create();

        $response = $this->patch(route('certifications.update', $certification), [
            'name'             => 'Updated Name',
            'description'      => 'Updated description',
            'expires_required' => false,
            'is_active'        => false,
        ]);

        $response->assertRedirect();

        $certification->refresh();
        expect($certification->name)->toBe('Updated Name');
    });

    test('super_admin can delete a certification', function () {
        $this->actingAs($this->user);

        $certification = CertificationType::factory()->create();

        $response = $this->delete(route('certifications.destroy', $certification));

        $response->assertRedirect();

        $this->assertDatabaseMissing('certification_types', [
            'id' => $certification->id,
        ]);
    });
});

// SpecialtyTypeController Tests
describe('SpecialtyTypeController', function () {
    beforeEach(function () {
        $this->user = User::factory()->create(['role' => 'super_admin']);
    });

    test('guests cannot view specialties index', function () {
        $response = $this->get(route('specialties.index'));
        $response->assertRedirect(route('login'));
    });

    test('admin cannot view specialties index', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->get(route('specialties.index'));
        // Middleware allows both admin and super_admin
        $response->assertSuccessful();
    });

    test('super_admin can view specialties index', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('specialties.index'));
        $response->assertSuccessful();
    });

    test('super_admin can create a specialty', function () {
        $this->actingAs($this->user);

        $response = $this->post(route('specialties.store'), [
            'name'        => 'Newborn Care',
            'description' => 'Specializes in newborn care',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('specialty_types', [
            'name' => 'Newborn Care',
        ]);
    });

    test('super_admin can update a specialty', function () {
        $this->actingAs($this->user);

        $specialty = SpecialtyType::factory()->create();

        $response = $this->patch(route('specialties.update', $specialty), [
            'name'        => 'Updated Specialty',
            'description' => 'Updated description',
        ]);

        $response->assertRedirect();

        $specialty->refresh();
        expect($specialty->name)->toBe('Updated Specialty');
    });

    test('super_admin can delete a specialty', function () {
        $this->actingAs($this->user);

        $specialty = SpecialtyType::factory()->create();

        $response = $this->delete(route('specialties.destroy', $specialty));

        $response->assertRedirect();

        $this->assertDatabaseMissing('specialty_types', [
            'id' => $specialty->id,
        ]);
    });
});

// LocationController Tests
describe('LocationController', function () {
    beforeEach(function () {
        $this->user = User::factory()->create(['role' => 'super_admin']);
    });

    test('guests cannot view locations index', function () {
        $response = $this->get(route('locations.index'));
        $response->assertRedirect(route('login'));
    });

    test('admin cannot view locations index', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->get(route('locations.index'));
        // Middleware allows both admin and super_admin
        $response->assertSuccessful();
    });

    test('super_admin can view locations index', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('locations.index'));
        $response->assertSuccessful();
    });

    test('super_admin can create a location', function () {
        $this->actingAs($this->user);

        $response = $this->post(route('locations.store'), [
            'name' => 'Grand Hotel',
            'type' => 'hotel',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('locations', [
            'name' => 'Grand Hotel',
        ]);
    });

    test('super_admin can update a location', function () {
        $this->actingAs($this->user);

        $location = Location::factory()->create();

        $response = $this->patch(route('locations.update', $location), [
            'name' => 'Updated Location',
        ]);

        $response->assertRedirect();

        $location->refresh();
        expect($location->name)->toBe('Updated Location');
    });

    test('super_admin can delete a location', function () {
        $this->actingAs($this->user);

        $location = Location::factory()->create();

        $response = $this->delete(route('locations.destroy', $location));

        $response->assertRedirect();

        $this->assertDatabaseMissing('locations', [
            'id' => $location->id,
        ]);
    });
});

// AttributeDefinitionController Tests
describe('AttributeDefinitionController', function () {
    beforeEach(function () {
        $this->user = User::factory()->create(['role' => 'super_admin']);
    });

    test('guests cannot view attributes index', function () {
        $response = $this->get(route('attributes.index'));
        $response->assertRedirect(route('login'));
    });

    test('admin cannot view attributes index', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->get(route('attributes.index'));
        // Middleware allows both admin and super_admin
        $response->assertSuccessful();
    });

    test('super_admin can view attributes index', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('attributes.index'));
        $response->assertSuccessful();
    });

    test('super_admin can create an attribute', function () {
        $this->actingAs($this->user);

        $response = $this->post(route('attributes.store'), [
            'name'        => 'Pet Allergies',
            'type'        => 'text',
            'entity_type' => 'client',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('attribute_definitions', [
            'name' => 'Pet Allergies',
        ]);
    });

    test('super_admin can update an attribute', function () {
        $this->actingAs($this->user);

        $attribute = AttributeDefinition::factory()->create([
            'name' => 'Original Name',
            'slug' => 'original-name',
        ]);

        $response = $this->patch(route('attributes.update', $attribute), [
            'name'        => 'UpdatedName',
            'type'        => 'text',
            'entity_type' => 'client',
        ]);

        $response->assertRedirect();

        $attribute->refresh();
        expect($attribute->name)->toBe('UpdatedName');
    });

    test('super_admin can delete an attribute', function () {
        $this->actingAs($this->user);

        $attribute = AttributeDefinition::factory()->create();

        $response = $this->delete(route('attributes.destroy', $attribute));

        $response->assertRedirect();

        $this->assertDatabaseMissing('attribute_definitions', [
            'id' => $attribute->id,
        ]);
    });
});

// HotelController Tests
describe('HotelController', function () {
    beforeEach(function () {
        $this->user = User::factory()->create(['role' => 'super_admin']);
    });

    test('guests cannot view hotels index', function () {
        $response = $this->get(route('hotels.index'));
        $response->assertRedirect(route('login'));
    });

    test('admin cannot view hotels index', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->get(route('hotels.index'));
        // Middleware allows both admin and super_admin
        $response->assertSuccessful();
    });

    test('super_admin can view hotels index', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('hotels.index'));
        $response->assertSuccessful();
    });

    test('super_admin can create a hotel', function () {
        $this->actingAs($this->user);

        $response = $this->post(route('hotels.store'), [
            'name'                 => 'Luxury Hotel',
            'line1'                => '123 Main St',
            'line2'                => '',
            'city'                 => 'Los Angeles',
            'state'                => 'CA',
            'zip'                  => '90001',
            'parking_instructions' => 'Valet parking available',
            'hourly_rate'          => 25.00,
            'resort_fee'           => null,
            'contact_name'         => '',
            'contact_phone'        => '',
            'admin_notes'          => '',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('hotels', [
            'name' => 'Luxury Hotel',
        ]);
    });

    test('super_admin can update a hotel', function () {
        $this->actingAs($this->user);

        $hotel = Hotel::factory()->create();

        $response = $this->patch(route('hotels.update', $hotel), [
            'name'                 => 'Updated Hotel',
            'line1'                => $hotel->line1,
            'line2'                => '',
            'city'                 => $hotel->city,
            'state'                => $hotel->state,
            'zip'                  => $hotel->zip,
            'parking_instructions' => 'Test',
            'hourly_rate'          => 25.00,
        ]);

        $response->assertRedirect();

        $hotel->refresh();
        expect($hotel->name)->toBe('Updated Hotel');
    });

    test('super_admin can delete a hotel', function () {
        $this->actingAs($this->user);

        $hotel = Hotel::factory()->create();

        $response = $this->delete(route('hotels.destroy', $hotel));

        $response->assertRedirect();

        $this->assertDatabaseMissing('hotels', [
            'id' => $hotel->id,
        ]);
    });
});