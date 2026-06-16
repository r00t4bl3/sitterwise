<?php

use App\Enums\AssignmentResolution;
use App\Enums\CaregiverStatus;
use App\Events\BookingGroupSplit;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientChild;
use App\Models\ClientPet;
use App\Models\Hotel;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->seed(AttributeDefinitionSeeder::class);
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
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

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
            'child_ids' => [$child->id],
            'address_line1' => '123 Hotel Way',
            'address_line2' => '',
            'address_city' => 'Los Angeles',
            'address_state' => 'CA',
            'address_zip' => '90001',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('booking_groups', [
            'client_id' => $this->client->id,
            'hotel_id' => $this->hotel->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
        ]);
    });

    test('admin can create a booking with unlisted hotel name', function () {
        $this->actingAs($this->user);
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        $response = $this->post(route('bookings.store'), [
            'client_id' => $this->client->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
            'start_datetime' => now()->addDays(1)->setHour(14)->toISOString(),
            'end_datetime' => now()->addDays(1)->setHour(18)->toISOString(),
            'hotel_id' => null,
            'hotel_name' => 'My Unlisted Hotel',
            'total_amount' => 100,
            'status' => 'received',
            'payment_status' => 'pending',
            'child_ids' => [$child->id],
            'address_line1' => '123 Unlisted Hotel Rd',
            'address_line2' => '',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('booking_groups', [
            'client_id' => $this->client->id,
            'hotel_id' => null,
            'hotel_name' => 'My Unlisted Hotel',
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
        ]);
    });

    test('admin can create a booking with vacation rental and save rental platform', function () {
        $this->actingAs($this->user);
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        $response = $this->post(route('bookings.store'), [
            'client_id' => $this->client->id,
            'service_type' => 'babysitter',
            'location_type' => 'vacation_rental',
            'start_datetime' => now()->addDays(1)->setHour(14)->toISOString(),
            'end_datetime' => now()->addDays(1)->setHour(18)->toISOString(),
            'total_amount' => 150,
            'status' => 'received',
            'payment_status' => 'pending',
            'child_ids' => [$child->id],
            'rental_platform' => 'airbnb',
            'address_line1' => '123 Beach House Way',
            'address_line2' => '',
            'address_city' => 'Malibu',
            'address_state' => 'CA',
            'address_zip' => '90265',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('booking_groups', [
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
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

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
            'child_ids' => [$child->id],
            'address_line1' => '123 Home Way',
            'address_line2' => '',
            'address_city' => 'Home City',
            'address_state' => 'CA',
            'address_zip' => '90001',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('booking_groups', [
            'client_id' => $this->client->id,
            'address_id' => $clientAddress->id,
        ]);
    });

    test('admin can update a booking', function () {
        $this->actingAs($this->user);
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        $booking = Booking::factory()->create([
            'status' => 'received',
        ]);

        $response = $this->patch(route('bookings.update', $booking), [
            'client_id' => $booking->client_id,
            'service_type' => $booking->service_type,
            'location_type' => $booking->location_type,
            'start_datetime' => now()->addDays(10)->toISOString(),
            'end_datetime' => now()->addDays(11)->toISOString(),
            'hotel_id' => $booking->hotel_id,
            'address_id' => $booking->address_id,
            'child_ids' => [$child->id],
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $response->assertRedirect();
        $booking->refresh();
        expect($booking->status)->toBe('confirmed');
    });

    test('unassigning caregiver from confirmed booking reverts status to received', function () {
        $this->actingAs($this->user);

        $caregiver = Caregiver::factory()->create();
        $booking = Booking::factory()
            ->forClient($this->client)
            ->withBookingGroup(fn ($group) => $group->state(['service_type' => 'babysitter']))
            ->create([
                'caregiver_id' => $caregiver->id,
                'status' => 'confirmed',
                'start_datetime' => now()->addDays(10),
                'end_datetime' => now()->addDays(10)->addHours(4),
            ]);

        // The saved hook creates an unresolved assignment
        $assignment = $booking->assignments()->unresolved()->first();
        expect($assignment)->not->toBeNull();

        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        $response = $this->patch(route('bookings.update', $booking), [
            'client_id' => $booking->client_id,
            'service_type' => $booking->service_type,
            'location_type' => $booking->location_type,
            'start_datetime' => $booking->start_datetime->toISOString(),
            'end_datetime' => $booking->end_datetime->toISOString(),
            'caregiver_id' => '',
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'child_ids' => [$child->id],
        ]);

        $response->assertSessionHasNoErrors();
        $booking->refresh();

        expect($booking->caregiver_id)->toBeNull();
        expect($booking->status)->toBe('received');
        $assignment->refresh();
        expect($assignment->resolution)->toBe(AssignmentResolution::Reassigned->value);
    });

    test('unassigning caregiver from completed booking does not revert status', function () {
        $this->actingAs($this->user);

        $caregiver = Caregiver::factory()->create();
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);
        $booking = Booking::factory()
            ->forClient($this->client)
            ->withBookingGroup(fn ($group) => $group->state(['service_type' => 'babysitter']))
            ->create([
                'caregiver_id' => $caregiver->id,
                'status' => 'completed',
                'payment_status' => 'paid',
                'start_datetime' => now()->subDays(2),
                'end_datetime' => now()->subDays(2)->addHours(4),
            ]);

        $response = $this->patch(route('bookings.update', $booking), [
            'client_id' => $booking->client_id,
            'service_type' => $booking->service_type,
            'location_type' => $booking->location_type,
            'start_datetime' => $booking->start_datetime->toISOString(),
            'end_datetime' => $booking->end_datetime->toISOString(),
            'caregiver_id' => '',
            'status' => 'completed',
            'payment_status' => 'paid',
            'child_ids' => [$child->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $booking->refresh();
        expect($booking->caregiver_id)->toBeNull();
        expect($booking->status)->toBe('completed');
    });

    test('assigning caregiver to received booking auto-confirms', function () {
        $this->actingAs($this->user);

        $caregiver = Caregiver::factory()->create();
        $booking = Booking::factory()
            ->forClient($this->client)
            ->withBookingGroup(fn ($group) => $group->state(['service_type' => 'babysitter']))
            ->create([
                'caregiver_id' => null,
                'status' => 'received',
                'start_datetime' => now()->addDays(10),
                'end_datetime' => now()->addDays(10)->addHours(4),
            ]);

        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        $response = $this->patch(route('bookings.update', $booking), [
            'client_id' => $booking->client_id,
            'service_type' => $booking->service_type,
            'location_type' => $booking->location_type,
            'start_datetime' => $booking->start_datetime->toISOString(),
            'end_datetime' => $booking->end_datetime->toISOString(),
            'caregiver_id' => $caregiver->id,
            'status' => 'received',
            'payment_status' => 'pending',
            'child_ids' => [$child->id],
        ]);

        $response->assertSessionHasNoErrors();
        $booking->refresh();

        expect($booking->caregiver_id)->toBe($caregiver->id);
        expect($booking->status)->toBe('confirmed');

        $assignment = $booking->assignments()->where('caregiver_id', $caregiver->id)->unresolved()->first();
        expect($assignment)->not->toBeNull();
    });

    test('admin can update booking vacation rental with platform and address', function () {
        $this->actingAs($this->user);

        $booking = Booking::factory()->withBookingGroup(fn ($g) => $g->state([
            'location_type' => 'vacation_rental',
        ]))->create();
        $child = ClientChild::factory()->create(['client_id' => $booking->client_id]);

        $response = $this->patch(route('bookings.update', $booking), [
            'client_id' => $booking->client_id,
            'service_type' => $booking->service_type,
            'location_type' => 'vacation_rental',
            'start_datetime' => now()->addDays(10)->toISOString(),
            'end_datetime' => now()->addDays(11)->toISOString(),
            'total_amount' => '200',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'child_ids' => [$child->id],
            'rental_platform' => 'vrbo',
            'address_line1' => '456 Mountain Cabin',
            'address_line2' => '',
            'address_city' => 'Lake Tahoe',
            'address_state' => 'CA',
            'address_zip' => '96150',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('booking_groups', [
            'id' => $booking->booking_group_id,
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

        $this->assertModelMissing($booking);
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
            'child_ids' => $children->pluck('id')->toArray(),
            'address_line1' => '123 Hotel Way',
            'address_line2' => '',
            'address_city' => 'Los Angeles',
            'address_state' => 'CA',
            'address_zip' => '90001',
        ]);

        $response->assertRedirect();

        $booking = Booking::whereHas('bookingGroup', fn ($q) => $q->where('client_id', $client->id))->first();

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
        $booking = Booking::factory()->forClient($client)->create([
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

    test('recommended caregivers endpoint returns tiered caregivers', function () {
        $this->actingAs($this->user);

        $client = Client::factory()->create();

        // Create caregivers with availability so they pass the default filter
        foreach (range(1, 3) as $i) {
            $caregiver = Caregiver::factory()->create(['status' => CaregiverStatus::Active->value]);
            Availability::factory()->create([
                'caregiver_id' => $caregiver->id,
                'date' => now()->addDays(5)->format('Y-m-d'),
            ]);
        }

        $response = $this->get(route('bookings.recommendedCaregivers', [
            'client_id' => $client->id,
        ]));

        $response->assertSuccessful();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'age', 'score', 'matchIcons', 'hasBeenNotified'],
            ],
            'all_ids',
            'meta' => ['total', 'per_page', 'current_page', 'last_page'],
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

    test('notify transitions booking from received to pending', function () {
        $this->actingAs($this->user);

        $booking = Booking::factory()->create(['status' => 'received']);
        $caregiver = Caregiver::factory()->create();

        $this->post(route('bookings.notify', $booking->id), [
            'caregiver_ids' => [$caregiver->id],
        ]);

        $booking->refresh();
        expect($booking->status)->toBe('pending');
    });

    test('notify from pending keeps status as pending', function () {
        $this->actingAs($this->user);

        $booking = Booking::factory()->create(['status' => 'pending']);
        $caregiver = Caregiver::factory()->create();

        $this->post(route('bookings.notify', $booking->id), [
            'caregiver_ids' => [$caregiver->id],
        ]);

        $booking->refresh();
        expect($booking->status)->toBe('pending');
    });

    test('notify from confirmed booking returns error', function () {
        $this->actingAs($this->user);

        $booking = Booking::factory()->create(['status' => 'confirmed']);
        $caregiver = Caregiver::factory()->create();

        $response = $this->post(route('bookings.notify', $booking->id), [
            'caregiver_ids' => [$caregiver->id],
        ]);

        $response->assertSessionHas('error');
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
        $booking = Booking::whereHas('bookingGroup', fn ($q) => $q->where('client_id', $this->client->id))->first();
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
        $booking = Booking::whereHas('bookingGroup', fn ($q) => $q->where('client_id', $this->client->id))->latest()->first();
        expect($booking->children)->toHaveCount(1);
        expect($booking->children[0]['name'])->toBe('Transient Child');

    });

    // Add tests for update request
    test('admin can update a booking to add new children and save to profile', function () {
        $this->actingAs($this->user);

        // Create a booking without children
        $booking = Booking::factory()->withBookingGroup(fn ($g) => $g->state([
            'client_id' => $this->client->id,
            'hotel_id' => $this->hotel->id,
            'service_type' => 'petsitter',
            'location_type' => 'hotel',
        ]))->create([
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
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'address_line1' => '789 Updated Way',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'new_children' => $newChildrenData,
            'save_children_pets_to_profile' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('booking_groups', [
            'id' => $booking->booking_group_id,
            'service_type' => 'babysitter',
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);

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
        $booking = Booking::factory()->withBookingGroup(fn ($g) => $g->state([
            'client_id' => $this->client->id,
            'hotel_id' => $this->hotel->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
        ]))->create([
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
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        // Create a booking with existing children but not saving to profile
        $booking = Booking::factory()->withBookingGroup(fn ($g) => $g->state([
            'client_id' => $this->client->id,
            'hotel_id' => $this->hotel->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
            'children' => [
                [
                    'name' => 'Original Child',
                    'gender' => 'male',
                    'birth_month' => 5,
                    'birth_year' => 2020,
                ],
            ],
        ]))->create([
            'start_datetime' => now()->addDays(1)->setHour(14),
            'end_datetime' => now()->addDays(1)->setHour(18),
            'status' => 'received',
            'payment_status' => 'pending',
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
            'child_ids' => [$child->id],
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
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

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
            'child_ids' => [$child->id],
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
        $booking = Booking::whereHas('bookingGroup', fn ($q) => $q->where('client_id', $this->client->id))->first();
        expect($booking->pets)->toHaveCount(2);
        expect($booking->pets[0]['name'])->toBe('Pet One');
        expect($booking->pets[1]['name'])->toBe('Pet Two');
    });

    test('admin can create a booking with new pets but NOT save to profile if requested', function () {
        $this->actingAs($this->user);
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

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
            'child_ids' => [$child->id],
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
        $booking = Booking::whereHas('bookingGroup', fn ($q) => $q->where('client_id', $this->client->id))->latest()->first();
        expect($booking->pets)->toHaveCount(1);
        expect($booking->pets[0]['name'])->toBe('Transient Pet');
    });

    test('admin can update a booking to add new pets and save to profile', function () {
        $this->actingAs($this->user);
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        // Create a booking without pets
        $booking = Booking::factory()->withBookingGroup(fn ($g) => $g->state([
            'client_id' => $this->client->id,
            'service_type' => 'petsitter',
            'location_type' => 'private_home',
        ]))->create([
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
            'child_ids' => [$child->id],
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
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        // Create a booking without pets
        $booking = Booking::factory()->withBookingGroup(fn ($g) => $g->state([
            'client_id' => $this->client->id,
            'service_type' => 'petsitter',
            'location_type' => 'private_home',
        ]))->create([
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
            'child_ids' => [$child->id],
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
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        // Create a booking with existing pets but not saving to profile
        $booking = Booking::factory()->withBookingGroup(fn ($g) => $g->state([
            'client_id' => $this->client->id,
            'hotel_id' => $this->hotel->id,
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'pets' => [
                [
                    'name' => 'Original Pet',
                    'type' => 'dog',
                    'breed' => 'Beagle',
                    'notes' => 'Very friendly',
                ],
            ],
        ]))->create([
            'start_datetime' => now()->addDays(1)->setHour(10),
            'end_datetime' => now()->addDays(1)->setHour(14),
            'status' => 'received',
            'payment_status' => 'pending',
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
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $booking->start_datetime->toISOString(),
            'end_datetime' => $booking->end_datetime->toISOString(),
            'total_amount' => 150,
            'status' => 'received',
            'payment_status' => 'pending',
            'child_ids' => [$child->id],
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

    // ---- Date validation tests for create vs update ----

    test('admin cannot create a booking with a past start datetime', function () {
        $this->actingAs($this->user);
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        $response = $this->post(route('bookings.store'), [
            'client_id' => $this->client->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
            'start_datetime' => now()->subHours(2)->toISOString(),
            'end_datetime' => now()->addHours(2)->toISOString(),
            'hotel_id' => $this->hotel->id,
            'total_amount' => 100,
            'status' => 'received',
            'payment_status' => 'pending',
            'child_ids' => [$child->id],
            'address_line1' => '123 Hotel Way',
            'address_city' => 'Los Angeles',
            'address_state' => 'CA',
            'address_zip' => '90001',
        ]);

        $response->assertSessionHasErrors('start_datetime');
    });

    test('admin cannot create a booking with a past end datetime', function () {
        $this->actingAs($this->user);
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        $response = $this->post(route('bookings.store'), [
            'client_id' => $this->client->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
            'start_datetime' => now()->addHours(1)->toISOString(),
            'end_datetime' => now()->subHours(1)->toISOString(),
            'hotel_id' => $this->hotel->id,
            'total_amount' => 100,
            'status' => 'received',
            'payment_status' => 'pending',
            'child_ids' => [$child->id],
            'address_line1' => '123 Hotel Way',
            'address_city' => 'Los Angeles',
            'address_state' => 'CA',
            'address_zip' => '90001',
        ]);

        $response->assertSessionHasErrors('end_datetime');
    });

    test('admin can update a booking with a past start datetime', function () {
        $this->actingAs($this->user);
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        $booking = Booking::factory()->withBookingGroup(fn ($g) => $g->state([
            'client_id' => $this->client->id,
            'hotel_id' => $this->hotel->id,
        ]))->create([
            'start_datetime' => now()->subDays(1)->setHour(14),
            'end_datetime' => now()->subDays(1)->setHour(18),
            'status' => 'received',
            'payment_status' => 'pending',
        ]);

        $response = $this->patch(route('bookings.update', $booking), [
            'client_id' => $booking->client_id,
            'service_type' => $booking->service_type,
            'location_type' => $booking->location_type,
            'start_datetime' => $booking->start_datetime->toISOString(),
            'end_datetime' => $booking->end_datetime->toISOString(),
            'hotel_id' => $booking->hotel_id,
            'child_ids' => [$child->id],
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $response->assertRedirect();
    });

    test('admin cannot update a booking where end datetime is before start datetime', function () {
        $this->actingAs($this->user);
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        $booking = Booking::factory()->withBookingGroup(fn ($g) => $g->state([
            'client_id' => $this->client->id,
            'hotel_id' => $this->hotel->id,
        ]))->create([
            'start_datetime' => now()->subDays(1)->setHour(14),
            'end_datetime' => now()->subDays(1)->setHour(18),
            'status' => 'received',
            'payment_status' => 'pending',
        ]);

        $response = $this->patch(route('bookings.update', $booking), [
            'client_id' => $booking->client_id,
            'service_type' => $booking->service_type,
            'location_type' => $booking->location_type,
            'start_datetime' => $booking->end_datetime->toISOString(),
            'end_datetime' => $booking->start_datetime->toISOString(),
            'hotel_id' => $booking->hotel_id,
            'child_ids' => [$child->id],
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $response->assertSessionHasErrors('end_datetime');
    });

    test('admin cannot update a booking with less than 4 hours duration', function () {
        $this->actingAs($this->user);
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        $booking = Booking::factory()->withBookingGroup(fn ($g) => $g->state([
            'client_id' => $this->client->id,
            'hotel_id' => $this->hotel->id,
        ]))->create([
            'start_datetime' => now()->subDays(1)->setHour(14),
            'end_datetime' => now()->subDays(1)->setHour(18),
            'status' => 'received',
            'payment_status' => 'pending',
        ]);

        $response = $this->patch(route('bookings.update', $booking), [
            'client_id' => $booking->client_id,
            'service_type' => $booking->service_type,
            'location_type' => $booking->location_type,
            'start_datetime' => now()->subHours(3)->toISOString(),
            'end_datetime' => now()->subHour()->toISOString(),
            'hotel_id' => $booking->hotel_id,
            'child_ids' => [$child->id],
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $response->assertSessionHasErrors('end_datetime');
    });

    it('can create a group childcare booking with children notes', function () {
        $this->actingAs($this->user);

        $start = now()->addDays(1)->setHour(9);
        $end = now()->addDays(1)->setHour(17);

        $response = $this->post(route('bookings.store'), [
            'client_id' => $this->client->id,
            'service_type' => 'group_childcare_invoiced',
            'location_type' => 'private_home',
            'start_datetime' => $start->toISOString(),
            'end_datetime' => $end->toISOString(),
            'status' => 'received',
            'payment_status' => 'pending',
            'address_line1' => '123 Main St',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'children_notes' => '10 children, ages 3-7',
            'new_children' => [],
            'new_pets' => [],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $booking = Booking::whereHas('bookingGroup', fn ($q) => $q->where('client_id', $this->client->id))->first();

        expect($booking)->not->toBeNull();
        expect($booking->children)->toBe([]);
        $group = $booking->bookingGroup;
        expect($group?->children_notes)->toBe('10 children, ages 3-7');
        expect($booking->children_notes)->toBe('10 children, ages 3-7');
        expect($booking->service_type)->toBe('group_childcare_invoiced');
    });

    it('can create a corporate invoiced booking with child_ids (children_notes ignored)', function () {
        $this->actingAs($this->user);
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        $start = now()->addDays(1)->setHour(10);
        $end = now()->addDays(1)->setHour(14);

        $response = $this->post(route('bookings.store'), [
            'client_id' => $this->client->id,
            'service_type' => 'corporate_invoiced',
            'location_type' => 'private_home',
            'start_datetime' => $start->toISOString(),
            'end_datetime' => $end->toISOString(),
            'status' => 'received',
            'payment_status' => 'pending',
            'address_line1' => '456 Oak Ave',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92102',
            'child_ids' => [$child->id],
            'children_notes' => 'should be ignored',
        ]);

        $response->assertSessionHasNoErrors();

        $booking = Booking::whereHas('bookingGroup', fn ($q) => $q
            ->where('client_id', $this->client->id)
            ->where('service_type', 'corporate_invoiced'))
            ->first();

        expect($booking)->not->toBeNull();
        expect($booking->children)->not->toBeNull();
        expect($booking->children)->toHaveCount(1);
        expect($booking->children_notes)->toBeNull();
    });

    it('stores children normally for non-group bookings', function () {
        $this->actingAs($this->user);

        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);
        $start = now()->addDays(1)->setHour(14);
        $end = now()->addDays(1)->setHour(18);

        $response = $this->post(route('bookings.store'), [
            'client_id' => $this->client->id,
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $start->toISOString(),
            'end_datetime' => $end->toISOString(),
            'status' => 'received',
            'payment_status' => 'pending',
            'address_line1' => '789 Pine St',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92103',
            'child_ids' => [$child->id],
            'children_notes' => 'should be ignored',
        ]);

        $response->assertSessionHasNoErrors();

        $booking = Booking::whereHas('bookingGroup', fn ($q) => $q
            ->where('client_id', $this->client->id)
            ->where('service_type', 'babysitter'))
            ->first();

        expect($booking)->not->toBeNull();
        expect($booking->children)->not->toBeNull();
        expect($booking->children)->toHaveCount(1);
        expect($booking->children_notes)->toBeNull();
    });

    it('can update a group booking children notes', function () {
        $this->actingAs($this->user);

        $group = BookingGroup::factory()->create([
            'client_id' => $this->client->id,
            'service_type' => 'group_childcare_invoiced',
        ]);
        $booking = Booking::factory()->create([
            'booking_group_id' => $group->id,
            'status' => 'received',
        ]);

        $start = now()->addDays(2)->setHour(8);
        $end = now()->addDays(2)->setHour(16);

        $response = $this->patch(route('bookings.update', $booking), [
            'client_id' => $this->client->id,
            'service_type' => 'group_childcare_invoiced',
            'location_type' => $booking->location_type,
            'start_datetime' => $start->toISOString(),
            'end_datetime' => $end->toISOString(),
            'hotel_id' => $booking->hotel_id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'children_notes' => '12 children, ages 5-12',
        ]);

        $response->assertSessionHasNoErrors();

        $booking->refresh();

        expect($booking->children_notes)->toBe('12 children, ages 5-12');
        expect($booking->children)->toBe([]);
    });

    describe('split group', function () {
        test('splits a group of 3 into 2+1', function () {
            $this->actingAs($this->user);

            $group = BookingGroup::factory()->create([
                'client_id' => $this->client->id,
                'service_type' => 'babysitter',
            ]);

            $bookings = Booking::factory()->count(3)->create([
                'booking_group_id' => $group->id,
                'status' => 'received',
            ]);

            $idsToExtract = [$bookings[0]->id, $bookings[1]->id];

            $response = $this->post(route('bookings.groups.split', $group), [
                'booking_ids' => $idsToExtract,
            ]);

            $response->assertRedirect();
            $group->refresh();

            expect($group->bookings()->count())->toBe(1);
            expect(BookingGroup::count())->toBe(2);
        });

        test('copies shared fields to new group', function () {
            $this->actingAs($this->user);

            $group = BookingGroup::factory()->create([
                'client_id' => $this->client->id,
                'service_type' => 'petsitter',
                'location_type' => 'hotel',
            ]);

            $bookings = Booking::factory()->count(2)->create([
                'booking_group_id' => $group->id,
                'status' => 'received',
            ]);

            $response = $this->post(route('bookings.groups.split', $group), [
                'booking_ids' => [$bookings[1]->id],
            ]);

            $response->assertRedirect();
            $newGroup = BookingGroup::where('id', '!=', $group->id)->first();

            expect($newGroup->client_id)->toBe($this->client->id);
            expect($newGroup->service_type)->toBe('petsitter');
            expect($newGroup->location_type)->toBe('hotel');
        });

        test('resets caregiver fields on extracted bookings', function () {
            $this->actingAs($this->user);

            $caregiver = Caregiver::factory()->create();

            $group = BookingGroup::factory()->create([
                'client_id' => $this->client->id,
            ]);

            $bookings = Booking::factory()->count(2)->create([
                'booking_group_id' => $group->id,
                'status' => 'reserved',
                'caregiver_id' => $caregiver->id,
                'reserved_by' => $caregiver->id,
                'reservation_expires_at' => now()->addMinutes(5),
            ]);

            $response = $this->post(route('bookings.groups.split', $group), [
                'booking_ids' => [$bookings[0]->id],
            ]);

            $response->assertRedirect();
            $bookings[0]->refresh();

            expect($bookings[0]->status)->toBe('received');
            expect($bookings[0]->caregiver_id)->toBeNull();
            expect($bookings[0]->reserved_by)->toBeNull();
            expect($bookings[0]->reservation_expires_at)->toBeNull();
            expect($bookings[0]->confirmed_by)->toBeNull();
            expect($bookings[0]->confirmed_at)->toBeNull();
        });

        test('fails when trying to move all bookings', function () {
            $this->actingAs($this->user);

            $group = BookingGroup::factory()->create([
                'client_id' => $this->client->id,
            ]);

            $bookings = Booking::factory()->count(2)->create([
                'booking_group_id' => $group->id,
                'status' => 'received',
            ]);

            $response = $this->post(route('bookings.groups.split', $group), [
                'booking_ids' => [$bookings[0]->id, $bookings[1]->id],
            ]);

            $response->assertSessionHas('error');
            expect(BookingGroup::count())->toBe(1);
        });

        test('fails when booking IDs do not belong to group', function () {
            $this->actingAs($this->user);

            $group = BookingGroup::factory()->create([
                'client_id' => $this->client->id,
            ]);

            $otherGroup = BookingGroup::factory()->create([
                'client_id' => $this->client->id,
            ]);

            $otherBooking = Booking::factory()->create([
                'booking_group_id' => $otherGroup->id,
                'status' => 'received',
            ]);

            $response = $this->post(route('bookings.groups.split', $group), [
                'booking_ids' => [$otherBooking->id],
            ]);

            $response->assertSessionHas('error');
            expect(BookingGroup::count())->toBe(2);
        });

        test('BookingGroupSplit event fires on split', function () {
            $this->actingAs($this->user);

            Event::fake();

            $group = BookingGroup::factory()->create([
                'client_id' => $this->client->id,
            ]);

            $bookings = Booking::factory()->count(2)->create([
                'booking_group_id' => $group->id,
                'status' => 'received',
            ]);

            $this->post(route('bookings.groups.split', $group), [
                'booking_ids' => [$bookings[1]->id],
            ]);

            Event::assertDispatched(BookingGroupSplit::class);
        });
    });

    describe('export', function () {
        test('guests cannot export bookings', function () {
            $response = $this->get(route('bookings.export'));

            $response->assertRedirect(route('login'));
        });

        test('caregivers cannot export bookings', function () {
            $caregiverUser = User::factory()->create(['role' => 'caregiver']);
            $this->actingAs($caregiverUser);

            $response = $this->get(route('bookings.export'));

            $response->assertForbidden();
        });

        test('admin can export bookings as xlsx', function () {
            $this->actingAs($this->user);

            $response = $this->get(route('bookings.export'));

            $response->assertSuccessful();
            $response->assertHeader('Content-Disposition', 'attachment; filename=bookings-'.now()->format('F').'-'.now()->year.'.xlsx');
        });

        test('export respects month and year parameters', function () {
            $this->actingAs($this->user);

            Booking::factory()->withBookingGroup(fn ($g) => $g->state([
                'client_id' => $this->client->id,
            ]))->create([
                'start_datetime' => now()->year(2025)->month(3)->day(15)->setHour(10),
                'end_datetime' => now()->year(2025)->month(3)->day(15)->setHour(14),
            ]);

            $response = $this->get(route('bookings.export', ['month' => 3, 'year' => 2025]));

            $response->assertSuccessful();
            $response->assertHeader('Content-Disposition', 'attachment; filename=bookings-March-2025.xlsx');
        });

        test('baseline: timezone behavior documents current PT-as-UTC storage for admin booking', function () {
            // The admin booking sheet sends naive datetime strings like "2026-05-29T09:00"
            // from formatDateTimeLocal(). These represent 9:00 AM PT but have
            // no timezone indicator. The backend currently treats them as UTC.
            //
            // This documents the CURRENT (buggy) behavior for the admin flow.
            // Both admin and client booking creation hit AdminBookingService@store.

            $this->actingAs($this->user);
            $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

            $startStr = now()->addDays(1)->format('Y-m-d\TH:i');
            $endStr = now()->addDays(1)->addHours(4)->format('Y-m-d\TH:i');

            // Extract the time portion to assert on
            $startTime = substr($startStr, 11, 5);
            $endTime = substr($endStr, 11, 5);

            $response = $this->post(route('bookings.store'), [
                'client_id' => $this->client->id,
                'service_type' => 'babysitter',
                'location_type' => 'private_home',
                'start_datetime' => $startStr,
                'end_datetime' => $endStr,
                'status' => 'received',
                'payment_status' => 'pending',
                'child_ids' => [$child->id],
                'address_line1' => '100 Timezone St',
                'address_line2' => '',
                'address_city' => 'San Diego',
                'address_state' => 'CA',
                'address_zip' => '92101',
            ]);

            $response->assertRedirect();

            $booking = Booking::latest('id')->first();

            // ── 1. Raw DB value ──
            // The frontend sends a naive datetime (no timezone).
            // Mutator converts PT→UTC, so the stored value is UTC not PT.
            $rawStart = $booking->getRawOriginal('start_datetime');
            $rawEnd = $booking->getRawOriginal('end_datetime');

            // Input time "09:00" PT = 16:00 UTC, input "13:00" PT = 20:00 UTC
            $utcStartTime = Carbon::parse($startStr, 'America/Los_Angeles')->setTimezone('UTC')->format('H:i');
            $utcEndTime = Carbon::parse($endStr, 'America/Los_Angeles')->setTimezone('UTC')->format('H:i');
            expect(Carbon::parse($rawStart)->format('H:i'))->toBe($utcStartTime);
            expect(Carbon::parse($rawEnd)->format('H:i'))->toBe($utcEndTime);

            // ── 2. Carbon serialization (what the frontend receives) ──
            $isoStart = $booking->start_datetime->toISOString();
            $isoEnd = $booking->end_datetime->toISOString();

            expect($isoStart)->toContain('T'.$utcStartTime.':00');
            expect($isoEnd)->toContain('T'.$utcEndTime.':00');

            // ── 3. PT conversion (now shows correct PT time) ──
            // If we convert the stored UTC value to America/Los_Angeles,
            // the time matches the original PT input.
            $ptStart = $booking->start_datetime->copy()->setTimezone('America/Los_Angeles');

            expect($ptStart->format('H:i'))->toBe($startTime);

            // ── 4. Inertia response (what the frontend receives) ──
            // Hit the show endpoint and verify the serialized datetime values.
            $showResponse = $this->get(route('bookings.show', $booking));
            $showResponse->assertInertia(fn ($page) => $page
                ->where('booking.start_datetime', $booking->start_datetime->toISOString())
                ->where('booking.end_datetime', $booking->end_datetime->toISOString())
            );

            // ── Summary ──
            // User meant:    9:00 AM PT  →  stores 16:00 UTC  →  displays as 9:00 AM PT ✅
            // Mutator converts "09:00" parsed as PT → 16:00 UTC → stored correctly
            // → toISOString() = "...T16:00:00..."
            // → formatDisplayTimeInPT() = "9:00 AM" ✅
        });

        test('Z-suffixed datetime from formatUtcStringFromPt is not double-converted', function () {
            // formatUtcStringFromPt() now outputs "2026-07-15T20:00Z" (UTC with Z suffix).
            // This test verifies convertToUtc detects the Z and skips PT→UTC re-conversion.

            $this->actingAs($this->user);
            $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

            // 1 PM PT = 20:00 UTC → formatUtcStringFromPt outputs "T20:00Z"
            $response = $this->post(route('bookings.store'), [
                'client_id' => $this->client->id,
                'service_type' => 'babysitter',
                'location_type' => 'private_home',
                'start_datetime' => '2026-07-15T16:00Z',   // 9 AM PT
                'end_datetime' => '2026-07-15T20:00Z',     // 1 PM PT (NOT double-converted)
                'status' => 'received',
                'payment_status' => 'pending',
                'child_ids' => [$child->id],
                'address_line1' => '100 Timezone St',
                'address_line2' => '',
                'address_city' => 'San Diego',
                'address_state' => 'CA',
                'address_zip' => '92101',
            ]);

            $response->assertRedirect();

            $booking = Booking::latest('id')->first();

            // If double-converted: end would be stored as 2026-07-16 03:00:00 (20:00 PT = 03:00 UTC next day)
            // Correct: stored as 2026-07-15 20:00:00 (the Z-suffixed input is already UTC)
            $rawEnd = $booking->getRawOriginal('end_datetime');
            expect(Carbon::parse($rawEnd)->format('Y-m-d H:i'))->toBe('2026-07-15 20:00');

            // Verify PT display still shows 1:00 PM
            $ptEnd = $booking->end_datetime->copy()->setTimezone('America/Los_Angeles');
            expect($ptEnd->format('H:i'))->toBe('13:00');
        });

    });
});
