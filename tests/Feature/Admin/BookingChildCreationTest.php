<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\Hotel;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CaregiverStatusSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AttributeDefinitionSeeder::class);
    $this->seed(CaregiverStatusSeeder::class);
    $this->seed(CertificationTypeSeeder::class);
    $this->seed(LocationSeeder::class);
    $this->seed(SpecialtyTypeSeeder::class);
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create();
    $this->hotel = Hotel::factory()->create();
});

test('admin can create a booking with new children and save to profile', function () {
    $this->actingAs($this->user);

    $newChildrenData = [
        [
            'name' => 'Child One',
            'gender' => 'male',
            'birth_month' => 5,
            'birth_year' => 2020,
        ],
        [
            'name' => 'Child Two',
            'gender' => 'female',
            'birth_month' => 10,
            'birth_year' => 2022,
        ],
    ];

    $response = $this->post(route('bookings.store'), [
        'client_id' => $this->client->id,
        'service_type' => 'babysitter',
        'location_type' => 'hotel',
        'start_datetime' => now()->addDays(1)->setHour(14)->toISOString(),
        'end_datetime' => now()->addDays(1)->setHour(18)->toISOString(),
        'hotel_id' => $this->hotel->id,
        'total_amount' => 100,
        'status' => 'received',
        'payment_status' => 'pending',
        'address_line1' => '123 Hotel Way',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'new_children' => $newChildrenData,
        'save_children_pets_to_profile' => true,
    ]);

    $response->assertRedirect();

    // Verify children are saved to client profile
    $this->assertDatabaseHas('client_children', [
        'client_id' => $this->client->id,
        'name' => 'Child One',
    ]);
    $this->assertDatabaseHas('client_children', [
        'client_id' => $this->client->id,
        'name' => 'Child Two',
    ]);

    // Verify booking snapshot includes the new children
    $booking = Booking::where('client_id', $this->client->id)->first();
    expect($booking->children)->toHaveCount(2);
    expect($booking->children[0]['name'])->toBe('Child One');
    expect($booking->children[1]['name'])->toBe('Child Two');
});

test('admin can create a booking with new children but NOT save to profile if requested', function () {
    $this->actingAs($this->user);

    $newChildrenData = [
        [
            'name' => 'Transient Child',
            'gender' => 'female',
            'birth_month' => 1,
            'birth_year' => 2021,
        ],
    ];

    $response = $this->post(route('bookings.store'), [
        'client_id' => $this->client->id,
        'service_type' => 'babysitter',
        'location_type' => 'hotel',
        'start_datetime' => now()->addDays(2)->setHour(10)->toISOString(),
        'end_datetime' => now()->addDays(2)->setHour(14)->toISOString(),
        'hotel_id' => $this->hotel->id,
        'total_amount' => 100,
        'status' => 'received',
        'payment_status' => 'pending',
        'address_line1' => '456 Hotel Ave',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'new_children' => $newChildrenData,
        'save_children_pets_to_profile' => false,
    ]);

    $response->assertRedirect();

    // Verify child is NOT saved to client profile
    $this->assertDatabaseMissing('client_children', [
        'client_id' => $this->client->id,
        'name' => 'Transient Child',
    ]);

    // Snapshot SHOULD include new_children data even if we didn't save to profile, because we set it on the booking before saving.
    $booking = Booking::where('client_id', $this->client->id)->latest()->first();
    expect($booking->children)->toHaveCount(1);
    expect($booking->children[0]['name'])->toBe('Transient Child');

});

// Add tests for update request
test('admin can update a booking to add new children and save to profile', function () {
    $this->actingAs($this->user);

    // Create a booking without children
    $booking = Booking::factory()->create([
        'client_id' => $this->client->id,
        'hotel_id' => $this->hotel->id,
        'service_type' => 'babysitter',
        'location_type' => 'hotel',
        'start_datetime' => now()->addDays(1)->setHour(14),
        'end_datetime' => now()->addDays(1)->setHour(18),
        'status' => 'received',
        'payment_status' => 'pending',
    ]);

    $newChildrenData = [
        [
            'name' => 'Updated Child One',
            'gender' => 'male',
            'birth_month' => 3,
            'birth_year' => 2019,
        ],
        [
            'name' => 'Updated Child Two',
            'gender' => 'female',
            'birth_month' => 7,
            'birth_year' => 2021,
        ],
    ];

    $response = $this->patch(route('bookings.update', $booking), [
        'client_id' => $this->client->id,
        'service_type' => 'babysitter',
        'location_type' => 'hotel',
        'start_datetime' => $booking->start_datetime->toISOString(),
        'end_datetime' => $booking->end_datetime->toISOString(),
        'hotel_id' => $this->hotel->id,
        'total_amount' => 150,
        'status' => 'received',
        'payment_status' => 'pending',
        'address_line1' => '789 Updated Way',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'new_children' => $newChildrenData,
        'save_children_pets_to_profile' => true,
    ]);

    $response->assertRedirect();

    // Verify children are saved to client profile
    $this->assertDatabaseHas('client_children', [
        'client_id' => $this->client->id,
        'name' => 'Updated Child One',
    ]);
    $this->assertDatabaseHas('client_children', [
        'client_id' => $this->client->id,
        'name' => 'Updated Child Two',
    ]);

    // Verify booking snapshot includes the new children
    $updatedBooking = Booking::find($booking->id);
    expect($updatedBooking->children)->toHaveCount(2);
    expect($updatedBooking->children[0]['name'])->toBe('Updated Child One');
    expect($updatedBooking->children[1]['name'])->toBe('Updated Child Two');
});

test('admin can update a booking to add new children but NOT save to profile if requested', function () {
    $this->actingAs($this->user);

    // Create a booking without children
    $booking = Booking::factory()->create([
        'client_id' => $this->client->id,
        'hotel_id' => $this->hotel->id,
        'service_type' => 'babysitter',
        'location_type' => 'hotel',
        'start_datetime' => now()->addDays(1)->setHour(14),
        'end_datetime' => now()->addDays(1)->setHour(18),
        'status' => 'received',
        'payment_status' => 'pending',
    ]);

    $newChildrenData = [
        [
            'name' => 'Updated Transient Child',
            'gender' => 'male',
            'birth_month' => 11,
            'birth_year' => 2020,
        ],
    ];

    $response = $this->patch(route('bookings.update', $booking), [
        'client_id' => $this->client->id,
        'service_type' => 'babysitter',
        'location_type' => 'hotel',
        'start_datetime' => $booking->start_datetime->toISOString(),
        'end_datetime' => $booking->end_datetime->toISOString(),
        'hotel_id' => $this->hotel->id,
        'total_amount' => 150,
        'status' => 'received',
        'payment_status' => 'pending',
        'address_line1' => '789 Updated Way',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'new_children' => $newChildrenData,
        'save_children_pets_to_profile' => false,
    ]);

    $response->assertRedirect();

    // Verify child is NOT saved to client profile
    $this->assertDatabaseMissing('client_children', [
        'client_id' => $this->client->id,
        'name' => 'Updated Transient Child',
    ]);

    // Snapshot SHOULD include new_children data even if we didn't save to profile
    $updatedBooking = Booking::find($booking->id);
    expect($updatedBooking->children)->toHaveCount(1);
    expect($updatedBooking->children[0]['name'])->toBe('Updated Transient Child');
});

test('admin can update a booking without adding new children and retain existing children when save_children_pets_to_profile is false', function () {
    $this->actingAs($this->user);

    // Create a booking with existing children but not saving to profile
    $booking = Booking::factory()->create([
        'client_id' => $this->client->id,
        'hotel_id' => $this->hotel->id,
        'service_type' => 'babysitter',
        'location_type' => 'hotel',
        'start_datetime' => now()->addDays(1)->setHour(14),
        'end_datetime' => now()->addDays(1)->setHour(18),
        'status' => 'received',
        'payment_status' => 'pending',
        'children' => [
            [
                'name' => 'Original Child',
                'gender' => 'male',
                'birth_month' => 5,
                'birth_year' => 2020,
            ],
        ],
    ]);

    // Verify booking has children
    expect($booking->children)->toHaveCount(1);
    expect($booking->children[0]['name'])->toBe('Original Child');

    // Verify child is NOT saved to client profile
    $this->assertDatabaseMissing('client_children', [
        'client_id' => $this->client->id,
        'name' => 'Original Child',
    ]);

    // Update the booking without changing children
    $response = $this->patch(route('bookings.update', $booking), [
        'client_id' => $this->client->id,
        'service_type' => 'babysitter',
        'location_type' => 'hotel',
        'start_datetime' => $booking->start_datetime->toISOString(),
        'end_datetime' => $booking->end_datetime->toISOString(),
        'hotel_id' => $this->hotel->id,
        'total_amount' => 150,
        'status' => 'received',
        'payment_status' => 'pending',
        'address_line1' => '789 Updated Way',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'save_children_pets_to_profile' => false,
    ]);

    $response->assertRedirect();

    // Verify children are still present after update
    $updatedBooking = Booking::find($booking->id);
    expect($updatedBooking->children)->toHaveCount(1);
    expect($updatedBooking->children[0]['name'])->toBe('Original Child');
});

test('admin can create a booking with new pets and save to profile', function () {
    $this->actingAs($this->user);

    $newPetsData = [
        [
            'name' => 'Pet One',
            'type' => 'dog',
            'breed' => 'Golden Retriever',
            'notes' => 'Very friendly',
        ],
        [
            'name' => 'Pet Two',
            'type' => 'cat',
            'breed' => 'Persian',
            'notes' => 'Indoor only',
        ],
    ];

    $response = $this->post(route('bookings.store'), [
        'client_id' => $this->client->id,
        'service_type' => 'petsitter',
        'location_type' => 'private_home',
        'start_datetime' => now()->addDays(1)->setHour(10)->toISOString(),
        'end_datetime' => now()->addDays(1)->setHour(14)->toISOString(),
        'total_amount' => 100,
        'status' => 'received',
        'payment_status' => 'pending',
        'address_line1' => '123 Pet Owner Way',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'new_pets' => $newPetsData,
        'save_children_pets_to_profile' => true,
    ]);

    $response->assertRedirect();

    // Verify pets are saved to client profile
    $this->assertDatabaseHas('client_pets', [
        'client_id' => $this->client->id,
        'name' => 'Pet One',
    ]);
    $this->assertDatabaseHas('client_pets', [
        'client_id' => $this->client->id,
        'name' => 'Pet Two',
    ]);

    // Verify booking snapshot includes the new pets
    $booking = Booking::where('client_id', $this->client->id)->first();
    expect($booking->pets)->toHaveCount(2);
    expect($booking->pets[0]['name'])->toBe('Pet One');
    expect($booking->pets[1]['name'])->toBe('Pet Two');
});

test('admin can create a booking with new pets but NOT save to profile if requested', function () {
    $this->actingAs($this->user);

    $newPetsData = [
        [
            'name' => 'Transient Pet',
            'type' => 'dog',
            'breed' => 'Beagle',
            'notes' => 'Temporary visitor',
        ],
    ];

    $response = $this->post(route('bookings.store'), [
        'client_id' => $this->client->id,
        'service_type' => 'petsitter',
        'location_type' => 'private_home',
        'start_datetime' => now()->addDays(2)->setHour(10)->toISOString(),
        'end_datetime' => now()->addDays(2)->setHour(14)->toISOString(),
        'total_amount' => 100,
        'status' => 'received',
        'payment_status' => 'pending',
        'address_line1' => '456 Pet Ave',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'new_pets' => $newPetsData,
        'save_children_pets_to_profile' => false,
    ]);

    $response->assertRedirect();

    // Verify pet is NOT saved to client profile
    $this->assertDatabaseMissing('client_pets', [
        'client_id' => $this->client->id,
        'name' => 'Transient Pet',
    ]);

    // Snapshot SHOULD include new_pets data even if we didn't save to profile
    $booking = Booking::where('client_id', $this->client->id)->latest()->first();
    expect($booking->pets)->toHaveCount(1);
    expect($booking->pets[0]['name'])->toBe('Transient Pet');
});

test('admin can update a booking to add new pets and save to profile', function () {
    $this->actingAs($this->user);

    // Create a booking without pets
    $booking = Booking::factory()->create([
        'client_id' => $this->client->id,
        'service_type' => 'petsitter',
        'location_type' => 'private_home',
        'start_datetime' => now()->addDays(1)->setHour(10),
        'end_datetime' => now()->addDays(1)->setHour(14),
        'status' => 'received',
        'payment_status' => 'pending',
    ]);

    $newPetsData = [
        [
            'name' => 'Updated Pet One',
            'type' => 'dog',
            'breed' => 'Labrador',
            'notes' => 'Loves water',
        ],
        [
            'name' => 'Updated Pet Two',
            'type' => 'cat',
            'breed' => 'Siamese',
            'notes' => 'Very vocal',
        ],
    ];

    $response = $this->patch(route('bookings.update', $booking), [
        'client_id' => $this->client->id,
        'service_type' => 'petsitter',
        'location_type' => 'private_home',
        'start_datetime' => $booking->start_datetime->toISOString(),
        'end_datetime' => $booking->end_datetime->toISOString(),
        'total_amount' => 150,
        'status' => 'received',
        'payment_status' => 'pending',
        'address_line1' => '789 Updated Way',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'new_pets' => $newPetsData,
        'save_children_pets_to_profile' => true,
    ]);

    $response->assertRedirect();

    // Verify pets are saved to client profile
    $this->assertDatabaseHas('client_pets', [
        'client_id' => $this->client->id,
        'name' => 'Updated Pet One',
    ]);
    $this->assertDatabaseHas('client_pets', [
        'client_id' => $this->client->id,
        'name' => 'Updated Pet Two',
    ]);

    // Verify booking snapshot includes the new pets
    $updatedBooking = Booking::find($booking->id);
    expect($updatedBooking->pets)->toHaveCount(2);
    expect($updatedBooking->pets[0]['name'])->toBe('Updated Pet One');
    expect($updatedBooking->pets[1]['name'])->toBe('Updated Pet Two');
});

test('admin can update a booking to add new pets but NOT save to profile if requested', function () {
    $this->actingAs($this->user);

    // Create a booking without pets
    $booking = Booking::factory()->create([
        'client_id' => $this->client->id,
        'service_type' => 'petsitter',
        'location_type' => 'private_home',
        'start_datetime' => now()->addDays(1)->setHour(10),
        'end_datetime' => now()->addDays(1)->setHour(14),
        'status' => 'received',
        'payment_status' => 'pending',
    ]);

    $newPetsData = [
        [
            'name' => 'Updated Transient Pet',
            'type' => 'dog',
            'breed' => 'Poodle',
            'notes' => 'Temporary visitor',
        ],
    ];

    $response = $this->patch(route('bookings.update', $booking), [
        'client_id' => $this->client->id,
        'service_type' => 'petsitter',
        'location_type' => 'private_home',
        'start_datetime' => $booking->start_datetime->toISOString(),
        'end_datetime' => $booking->end_datetime->toISOString(),
        'total_amount' => 150,
        'status' => 'received',
        'payment_status' => 'pending',
        'address_line1' => '789 Updated Way',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'new_pets' => $newPetsData,
        'save_children_pets_to_profile' => false,
    ]);

    $response->assertRedirect();

    // Verify pet is NOT saved to client profile
    $this->assertDatabaseMissing('client_pets', [
        'client_id' => $this->client->id,
        'name' => 'Updated Transient Pet',
    ]);

    // Snapshot SHOULD include new_pets data even if we didn't save to profile
    $updatedBooking = Booking::find($booking->id);
    expect($updatedBooking->pets)->toHaveCount(1);
    expect($updatedBooking->pets[0]['name'])->toBe('Updated Transient Pet');
});

test('admin can update a booking without adding new pets and retain existing pets when save_children_pets_to_profile is false', function () {
    $this->actingAs($this->user);

    // Create a booking with existing pets but not saving to profile
    $booking = Booking::factory()->create([
        'client_id' => $this->client->id,
        'service_type' => 'petsitter',
        'location_type' => 'private_home',
        'start_datetime' => now()->addDays(1)->setHour(10),
        'end_datetime' => now()->addDays(1)->setHour(14),
        'status' => 'received',
        'payment_status' => 'pending',
        'pets' => [
            [
                'name' => 'Original Pet',
                'type' => 'dog',
                'breed' => 'Beagle',
                'notes' => 'Very friendly',
            ],
        ],
    ]);

    // Verify booking has pets
    expect($booking->pets)->toHaveCount(1);
    expect($booking->pets[0]['name'])->toBe('Original Pet');

    // Verify pet is NOT saved to client profile
    $this->assertDatabaseMissing('client_pets', [
        'client_id' => $this->client->id,
        'name' => 'Original Pet',
    ]);

    // Update the booking without changing pets
    $response = $this->patch(route('bookings.update', $booking), [
        'client_id' => $this->client->id,
        'service_type' => 'petsitter',
        'location_type' => 'private_home',
        'start_datetime' => $booking->start_datetime->toISOString(),
        'end_datetime' => $booking->end_datetime->toISOString(),
        'total_amount' => 150,
        'status' => 'received',
        'payment_status' => 'pending',
        'address_line1' => '789 Updated Way',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'save_children_pets_to_profile' => false,
    ]);

    $response->assertRedirect();

    // Verify pets are still present after update
    $updatedBooking = Booking::find($booking->id);
    expect($updatedBooking->pets)->toHaveCount(1);
    expect($updatedBooking->pets[0]['name'])->toBe('Original Pet');
});
