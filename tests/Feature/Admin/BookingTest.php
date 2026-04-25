<?php

use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientChild;
use App\Models\ClientPet;
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

describe('Booking - Admin', function () {
    test('guests cannot view bookings index', function () {
        $response = $this->get(route('bookings.index'));
        $response->assertRedirect(route('login'));
    });

    test('guests cannot create a booking', function () {
        $response = $this->post(route('bookings.store'), [
            'client_id' => $this->client->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
            'start_datetime' => now()->addDays(1)->toISOString(),
            'end_datetime' => now()->addDays(1)->addHours(4)->toISOString(),
            'hotel_id' => $this->hotel->id,
            'total_amount' => 100,
            'status' => 'received',
            'payment_status' => 'pending',
        ]);

        $response->assertRedirect(route('login'));
    });

    test('guests cannot update a booking', function () {
        $booking = Booking::factory()->create();

        $response = $this->patch(route('bookings.update', $booking), [
            'status' => 'confirmed',
        ]);

        $response->assertRedirect(route('login'));
    });

    test('guests cannot delete a booking', function () {
        $booking = Booking::factory()->create();

        $response = $this->delete(route('bookings.destroy', $booking));
        $response->assertRedirect(route('login'));
    });

    test('guests cannot search hotels', function () {
        $response = $this->get(route('hotels.search', ['q' => 'test']));
        $response->assertRedirect(route('login'));
    });

    // Non-admin user tests - caregivers CAN access bookings (like availabilities)
    test('caregivers can view bookings index', function () {
        $caregiverUser = Caregiver::factory()->create();
        $this->actingAs($caregiverUser->user);

        $response = $this->get(route('bookings.index'));
        $response->assertSuccessful();
    });

    test('caregivers cannot create a booking', function () {
        $caregiverUser = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($caregiverUser);

        $response = $this->post(route('bookings.store'), [
            'client_id' => $this->client->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
            'start_datetime' => now()->addDays(1)->toISOString(),
            'end_datetime' => now()->addDays(1)->addHours(4)->toISOString(),
            'hotel_id' => $this->hotel->id,
            'total_amount' => 100,
            'status' => 'received',
            'payment_status' => 'pending',
        ]);

        $response->assertForbidden();
    });

    test('caregivers cannot update a booking', function () {
        $caregiverUser = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($caregiverUser);

        $booking = Booking::factory()->create();

        $response = $this->patch(route('bookings.update', $booking), [
            'client_id' => $booking->client_id,
            'service_type' => $booking->service_type,
            'location_type' => $booking->location_type,
            'start_datetime' => $booking->start_datetime->toISOString(),
            'end_datetime' => $booking->end_datetime->toISOString(),
            'total_amount' => $booking->total_amount,
            'status' => 'confirmed',
            'payment_status' => $booking->payment_status,
        ]);

        $response->assertForbidden();
    });

    test('caregivers cannot delete a booking', function () {
        $caregiverUser = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($caregiverUser);

        $booking = Booking::factory()->create();

        $response = $this->delete(route('bookings.destroy', $booking));
        $response->assertForbidden();
    });

    test('caregivers can search hotels', function () {
        $caregiverUser = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($caregiverUser);

        $response = $this->get(route('hotels.search', ['q' => 'test']));
        $response->assertSuccessful();
    });

    // Admin user tests
    test('admin can view bookings index', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('bookings.index'));
        $response->assertSuccessful();
    });

    test('admin can create a booking with hotel location', function () {
        $this->actingAs($this->user);

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
            'address_line2' => '',
            'address_city' => 'Los Angeles',
            'address_state' => 'CA',
            'address_zip' => '90001',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'client_id' => $this->client->id,
            'hotel_id' => $this->hotel->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
        ]);
    });

    test('admin can create a booking with vacation rental and save rental platform', function () {
        $this->actingAs($this->user);

        $response = $this->post(route('bookings.store'), [
            'client_id' => $this->client->id,
            'service_type' => 'babysitter',
            'location_type' => 'vacation_rental',
            'start_datetime' => now()->addDays(1)->setHour(14)->toISOString(),
            'end_datetime' => now()->addDays(1)->setHour(18)->toISOString(),
            'total_amount' => 150,
            'status' => 'received',
            'payment_status' => 'pending',
            'rental_platform' => 'airbnb',
            'address_line1' => '123 Beach House Way',
            'address_line2' => '',
            'address_city' => 'Malibu',
            'address_state' => 'CA',
            'address_zip' => '90265',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'client_id' => $this->client->id,
            'location_type' => 'vacation_rental',
            'rental_platform' => 'airbnb',
            'address_line1' => '123 Beach House Way',
            'address_city' => 'Malibu',
            'address_state' => 'CA',
            'address_zip' => '90265',
        ]);
    });

    test('admin can create a booking with client address', function () {
        $this->actingAs($this->user);

        $clientAddress = ClientAddress::factory()->create();

        $response = $this->post(route('bookings.store'), [
            'client_id' => $this->client->id,
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => now()->addDays(1)->setHour(14)->toISOString(),
            'end_datetime' => now()->addDays(1)->setHour(18)->toISOString(),
            'address_id' => $clientAddress->id,
            'total_amount' => 100,
            'status' => 'received',
            'payment_status' => 'pending',
            'address_line1' => '123 Home Way',
            'address_line2' => '',
            'address_city' => 'Home City',
            'address_state' => 'CA',
            'address_zip' => '90001',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'client_id' => $this->client->id,
            'address_id' => $clientAddress->id,
        ]);
    });

    test('admin can update a booking', function () {
        $this->actingAs($this->user);

        $booking = Booking::factory()->create([
            'status' => 'received',
            'total_amount' => 100,
        ]);

        $response = $this->patch(route('bookings.update', $booking), [
            'client_id' => $booking->client_id,
            'service_type' => $booking->service_type,
            'location_type' => $booking->location_type,
            'start_datetime' => now()->addDays(10)->toISOString(),
            'end_datetime' => now()->addDays(11)->toISOString(),
            'hotel_id' => $booking->hotel_id,
            'address_id' => $booking->address_id,
            'total_amount' => '200.00',
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $response->assertRedirect();

        $booking->refresh();
        expect((float) $booking->total_amount)->toBe(200.00);
        expect($booking->status)->toBe('confirmed');
    });

    test('admin can update booking vacation rental with platform and address', function () {
        $this->actingAs($this->user);

        $booking = Booking::factory()->create([
            'location_type' => 'vacation_rental',
        ]);

        $response = $this->patch(route('bookings.update', $booking), [
            'client_id' => $booking->client_id,
            'service_type' => $booking->service_type,
            'location_type' => 'vacation_rental',
            'start_datetime' => now()->addDays(10)->toISOString(),
            'end_datetime' => now()->addDays(11)->toISOString(),
            'total_amount' => '200',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'rental_platform' => 'vrbo',
            'address_line1' => '456 Mountain Cabin',
            'address_line2' => '',
            'address_city' => 'Lake Tahoe',
            'address_state' => 'CA',
            'address_zip' => '96150',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'rental_platform' => 'vrbo',
            'address_line1' => '456 Mountain Cabin',
            'address_city' => 'Lake Tahoe',
        ]);
    });

    test('admin can delete a booking', function () {
        $this->actingAs($this->user);

        $booking = Booking::factory()->create();

        $response = $this->delete(route('bookings.destroy', $booking));

        $response->assertRedirect();

        $this->assertSoftDeleted('bookings', [
            'id' => $booking->id,
        ]);
    });

    test('admin can search hotels', function () {
        $this->actingAs($this->user);

        $hotel = Hotel::factory()->create(['name' => 'Grand Hotel']);

        $response = $this->get(route('hotels.search', ['q' => 'Grand']));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/json');

        $data = $response->json();
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toContain('Grand Hotel');
    });

    test('bookings index loads with filters', function () {
        $this->actingAs($this->user);

        Booking::factory()->count(3)->create();

        $response = $this->get(route('bookings.index', ['month' => 4, 'year' => 2026]));

        $response->assertSuccessful();
    });

    test('booking index includes required data for form', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('bookings.index'));

        $response->assertSuccessful();

        $response->assertSee('bookings', false);
        $response->assertSee('clients');
        $response->assertSee('hotels');
        $response->assertSee('caregivers');
        $response->assertSee('service_types');
        $response->assertSee('location_types');
        $response->assertSee('booking_statuses');
        $response->assertSee('payment_statuses');
    });

    test('booking creation captures client snapshot data', function () {
        $this->actingAs($this->user);

        // Create client with children and pets
        $client = Client::factory()->create();
        $children = ClientChild::factory()->count(2)->create(['client_id' => $client->id]);
        $pets = ClientPet::factory()->count(1)->create(['client_id' => $client->id]);

        $response = $this->post(route('bookings.store'), [
            'client_id' => $client->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
            'start_datetime' => now()->addDays(1)->setHour(14)->toISOString(),
            'end_datetime' => now()->addDays(1)->setHour(18)->toISOString(),
            'hotel_id' => $this->hotel->id,
            'total_amount' => 100,
            'status' => 'received',
            'payment_status' => 'pending',
            'address_line1' => '123 Hotel Way',
            'address_line2' => '',
            'address_city' => 'Los Angeles',
            'address_state' => 'CA',
            'address_zip' => '90001',
        ]);

        $response->assertRedirect();

        $booking = Booking::where('client_id', $client->id)->first();

        // Verify snapshot data was captured
        expect($booking->client_first_name)->toBe($client->first_name);
        expect($booking->client_last_name)->toBe($client->last_name);
        expect($booking->client_phone)->toBe($client->phone);
        expect($booking->client_email)->toBe($client->user->email);
        expect($booking->children)->toHaveCount(2);
        expect($booking->pets)->toHaveCount(1);
        expect($booking->children[0]['name'])->toBe($children->first()->name);
        expect($booking->pets[0]['name'])->toBe($pets->first()->name);
    });

    test('booking snapshot remains unchanged when client profile is updated', function () {
        $this->actingAs($this->user);

        // Create client with initial data
        $client = Client::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '555-1234',
        ]);

        // Create booking
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'received',
        ]);

        // Capture original snapshot data
        $originalFirstName = $booking->client_first_name;
        $originalLastName = $booking->client_last_name;
        $originalPhone = $booking->client_phone;

        // Update client profile
        $client->update([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '555-9999',
        ]);

        // Verify booking snapshot remains unchanged
        $booking->refresh();
        expect($booking->client_first_name)->toBe($originalFirstName);
        expect($booking->client_last_name)->toBe($originalLastName);
        expect($booking->client_phone)->toBe($originalPhone);
    });

    test('recommended caregivers endpoint returns scored caregivers', function () {
        $this->actingAs($this->user);

        $client = Client::factory()->create();
        Caregiver::factory()->count(3)->create();

        $response = $this->get(route('bookings.recommendedCaregivers', [
            'client_id' => $client->id,
        ]));

        $response->assertSuccessful();
        $response->assertJsonCount(3);
        $response->assertJsonStructure([
            '*' => ['id', 'name', 'score', 'matchBadge' => ['label', 'color', 'icon']],
        ]);
    });

    test('recommended caregivers endpoint requires client_id', function () {
        $this->actingAs($this->user);

        $response = $this->get('/bookings/recommended-caregivers');

        $response->assertStatus(302); // Redirect to validation error or similar
    });

    test('notify endpoint creates notification records', function () {
        // $this->markTestSkipped('CSRF protection prevents this test from running in isolation.');

        $this->actingAs($this->user);

        $booking = Booking::factory()->create(['status' => 'received']);
        $caregiver1 = Caregiver::factory()->create();
        $caregiver2 = Caregiver::factory()->create();

        $response = $this->post(route('bookings.notify', $booking->id), [
            'caregiver_ids' => [$caregiver1->id, $caregiver2->id],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('booking_caregiver_notifications', [
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver1->id,
            'claimed' => false,
        ]);

        $this->assertDatabaseHas('booking_caregiver_notifications', [
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver2->id,
            'claimed' => false,
        ]);
    });

    test('notify endpoint prevents duplicate notifications', function () {
        // $this->markTestSkipped('CSRF protection prevents this test from running in isolation.');

        $this->actingAs($this->user);

        $booking = Booking::factory()->create(['status' => 'received']);
        $caregiver = Caregiver::factory()->create();

        // First notification
        $this->post(route('bookings.notify', $booking->id), [
            'caregiver_ids' => [$caregiver->id],
        ]);

        // Second notification to same caregiver
        $this->post(route('bookings.notify', $booking->id), [
            'caregiver_ids' => [$caregiver->id],
        ]);

        // Should only have one record (updateOrCreate)
        $this->assertDatabaseCount('booking_caregiver_notifications', 1);
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

});
