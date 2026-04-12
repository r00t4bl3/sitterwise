<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientChild;
use App\Models\ClientPet;
use App\Models\Hotel;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AttributeDefinitionSeeder::class);
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create();
    $this->hotel = Hotel::factory()->create();
});

describe('BookingController', function () {
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
        $response = $this->get(route('admin.bookings.searchHotels', ['q' => 'test']));
        $response->assertRedirect(route('login'));
    });

    // Non-admin user tests - caregivers CAN access bookings (like availabilities)
    test('caregivers can view bookings index', function () {
        $caregiverUser = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($caregiverUser);

        $response = $this->get(route('bookings.index'));
        $response->assertSuccessful();
    });

    test('caregivers can create a booking', function () {
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

        $response->assertRedirect();
    });

    test('caregivers can update a booking', function () {
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

        $response->assertRedirect();
    });

    test('caregivers can delete a booking', function () {
        $caregiverUser = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($caregiverUser);

        $booking = Booking::factory()->create();

        $response = $this->delete(route('bookings.destroy', $booking));
        $response->assertRedirect();
    });

    test('caregivers can search hotels', function () {
        $caregiverUser = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($caregiverUser);

        $response = $this->get(route('admin.bookings.searchHotels', ['q' => 'test']));
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

        $response = $this->get(route('admin.bookings.searchHotels', ['q' => 'Grand']));

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
        $this->withoutMiddleware();
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
        $this->withoutMiddleware();
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
});
