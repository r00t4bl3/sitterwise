<?php

use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->seed([
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create();
});

describe('Client Booking History', function () {
    test('guests are redirected to login when accessing booking history', function () {
        $response = $this->get(route('clients.bookingHistory', $this->client));

        $response->assertRedirect(route('login'));
    });

    test('regular users cannot access booking history', function () {
        $user = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($user);

        $response = $this->get(route('clients.bookingHistory', $this->client));

        $response->assertForbidden();
    });

    test('admin users can view booking history', function () {
        Booking::factory()->count(3)->create([
            'client_id' => $this->client->id,
        ]);

        $this->actingAs($this->admin);

        $response = $this->get(route('clients.bookingHistory', $this->client));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/clients/booking-history')
            ->has('bookings.data', 3)
            ->has('filters')
        );
    });

    test('booking history shows only bookings for the given client', function () {
        $otherClient = Client::factory()->create();
        Booking::factory()->count(2)->create(['client_id' => $this->client->id]);
        Booking::factory()->count(3)->create(['client_id' => $otherClient->id]);

        $this->actingAs($this->admin);

        $response = $this->get(route('clients.bookingHistory', $this->client));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 2)
        );
    });

    test('booking history can be filtered by status', function () {
        Booking::factory()->create(['client_id' => $this->client->id, 'status' => 'confirmed']);
        Booking::factory()->create(['client_id' => $this->client->id, 'status' => 'completed']);
        Booking::factory()->create(['client_id' => $this->client->id, 'status' => 'cancelled']);

        $this->actingAs($this->admin);

        $response = $this->get(route('clients.bookingHistory', [$this->client, 'status' => 'completed']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 1)
        );
    });

    test('booking history returns empty when status filter matches nothing', function () {
        Booking::factory()->create(['client_id' => $this->client->id, 'status' => 'confirmed']);

        $this->actingAs($this->admin);

        $response = $this->get(route('clients.bookingHistory', [$this->client, 'status' => 'paid']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 0)
        );
    });

    test('booking history can be filtered by search', function () {
        $caregiver = Caregiver::factory()->create();
        $caregiver->user->update(['name' => 'SearchableCaregiver']);

        Booking::factory()->create([
            'client_id' => $this->client->id,
            'caregiver_id' => $caregiver->id,
        ]);
        Booking::factory()->create([
            'client_id' => $this->client->id,
            'caregiver_id' => null,
        ]);

        $this->actingAs($this->admin);

        $response = $this->get(route('clients.bookingHistory', [$this->client, 'search' => 'Searchable']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 1)
        );
    });

    test('booking history paginates results', function () {
        Booking::factory()->count(25)->create(['client_id' => $this->client->id]);

        $this->actingAs($this->admin);

        $response = $this->get(route('clients.bookingHistory', $this->client));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 20)
            ->where('bookings.total', 25)
            ->where('bookings.last_page', 2)
        );
    });

    test('booking history persists filter in pagination links', function () {
        Booking::factory()->count(25)->create(['client_id' => $this->client->id, 'status' => 'confirmed']);
        Booking::factory()->count(5)->create(['client_id' => $this->client->id, 'status' => 'completed']);

        $this->actingAs($this->admin);

        $response = $this->get(route('clients.bookingHistory', [$this->client, 'status' => 'confirmed']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 20)
            ->where('bookings.total', 25)
        );
    });

    test('booking history passes correct client info to inertia', function () {
        $this->actingAs($this->admin);

        $response = $this->get(route('clients.bookingHistory', $this->client));

        $response->assertInertia(fn ($page) => $page
            ->where('client.id', $this->client->id)
            ->where('client.first_name', $this->client->first_name)
            ->where('client.last_name', $this->client->last_name)
        );
    });
});
