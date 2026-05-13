<?php

use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CaregiverStatusSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->seed([
        CaregiverStatusSeeder::class,
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->caregiver = Caregiver::factory()->create();
});

describe('Caregiver Job History', function () {
    test('guests are redirected to login when accessing job history', function () {
        $response = $this->get(route('caregivers.jobHistory', $this->caregiver));

        $response->assertRedirect(route('login'));
    });

    test('regular users cannot access job history', function () {
        $user = User::factory()->create(['role' => 'client']);
        $this->actingAs($user);

        $response = $this->get(route('caregivers.jobHistory', $this->caregiver));

        $response->assertForbidden();
    });

    test('admin users can view job history', function () {
        Booking::factory()->count(3)->create([
            'caregiver_id' => $this->caregiver->id,
        ]);

        $this->actingAs($this->admin);

        $response = $this->get(route('caregivers.jobHistory', $this->caregiver));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/caregivers/job-history')
            ->has('bookings.data', 3)
            ->has('filters')
        );
    });

    test('job history shows only bookings for the given caregiver', function () {
        $otherCaregiver = Caregiver::factory()->create();
        Booking::factory()->count(2)->create(['caregiver_id' => $this->caregiver->id]);
        Booking::factory()->count(3)->create(['caregiver_id' => $otherCaregiver->id]);

        $this->actingAs($this->admin);

        $response = $this->get(route('caregivers.jobHistory', $this->caregiver));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 2)
        );
    });

    test('job history can be filtered by status', function () {
        Booking::factory()->create(['caregiver_id' => $this->caregiver->id, 'status' => 'confirmed']);
        Booking::factory()->create(['caregiver_id' => $this->caregiver->id, 'status' => 'completed']);
        Booking::factory()->create(['caregiver_id' => $this->caregiver->id, 'status' => 'cancelled']);

        $this->actingAs($this->admin);

        $response = $this->get(route('caregivers.jobHistory', [$this->caregiver, 'status' => 'completed']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 1)
        );
    });

    test('job history returns empty when status filter matches nothing', function () {
        Booking::factory()->create(['caregiver_id' => $this->caregiver->id, 'status' => 'confirmed']);

        $this->actingAs($this->admin);

        $response = $this->get(route('caregivers.jobHistory', [$this->caregiver, 'status' => 'paid']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 0)
        );
    });

    test('job history can be filtered by search', function () {
        $client = Client::factory()->create();
        $client->user->update(['name' => 'SearchableClient']);

        Booking::factory()->create([
            'caregiver_id' => $this->caregiver->id,
            'client_id' => $client->id,
        ]);
        Booking::factory()->create([
            'caregiver_id' => $this->caregiver->id,
            'client_id' => Client::factory(),
        ]);

        $this->actingAs($this->admin);

        $response = $this->get(route('caregivers.jobHistory', [$this->caregiver, 'search' => 'Searchable']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 1)
        );
    });

    test('job history paginates results', function () {
        Booking::factory()->count(25)->create(['caregiver_id' => $this->caregiver->id]);

        $this->actingAs($this->admin);

        $response = $this->get(route('caregivers.jobHistory', $this->caregiver));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 20)
            ->where('bookings.total', 25)
            ->where('bookings.last_page', 2)
        );
    });

    test('job history persists filter in pagination links', function () {
        Booking::factory()->count(25)->create(['caregiver_id' => $this->caregiver->id, 'status' => 'confirmed']);
        Booking::factory()->count(5)->create(['caregiver_id' => $this->caregiver->id, 'status' => 'completed']);

        $this->actingAs($this->admin);

        $response = $this->get(route('caregivers.jobHistory', [$this->caregiver, 'status' => 'confirmed']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 20)
            ->where('bookings.total', 25)
        );
    });

    test('job history passes correct caregiver info to inertia', function () {
        $this->actingAs($this->admin);

        $response = $this->get(route('caregivers.jobHistory', $this->caregiver));

        $response->assertInertia(fn ($page) => $page
            ->where('caregiver.id', $this->caregiver->id)
            ->where('caregiver.first_name', $this->caregiver->first_name)
            ->where('caregiver.last_name', $this->caregiver->last_name)
        );
    });
});
