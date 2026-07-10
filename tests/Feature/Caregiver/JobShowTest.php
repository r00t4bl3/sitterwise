<?php

use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->seed([
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->caregiver = Caregiver::factory()->create();
    $this->client = Client::factory()->create();
});

describe('Job show — invited caregiver redirect to accept page', function () {
    test('invited unassigned caregiver is redirected from jobs.show to the accept page', function () {
        $booking = Booking::factory()->forClient($this->client)->create([
            'status' => 'received',
            'caregiver_id' => null,
        ]);

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $this->caregiver->id,
            'notified_at' => now(),
        ]);

        $this->actingAs($this->caregiver->user);

        $this->get(route('jobs.show', $booking))
            ->assertRedirect(route('bookings.show', $booking->ulid));
    });

    test('invited unassigned caregiver following the SMS short link is redirected to the accept page', function () {
        $booking = Booking::factory()->forClient($this->client)->create([
            'status' => 'received',
            'caregiver_id' => null,
        ]);

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $this->caregiver->id,
            'notified_at' => now(),
        ]);

        $this->actingAs($this->caregiver->user);

        $this->get(route('jobs.short', $booking))
            ->assertRedirect(route('bookings.show', $booking->ulid));
    });

    test('assigned caregiver stays on the read-only job page', function () {
        $booking = Booking::factory()->forClient($this->client)->create([
            'status' => 'confirmed',
            'caregiver_id' => $this->caregiver->id,
        ]);

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $this->caregiver->id,
            'notified_at' => now(),
        ]);

        $this->actingAs($this->caregiver->user);

        $this->get(route('jobs.show', $booking))
            ->assertSuccessful();
    });

    test('caregiver who was neither invited nor assigned gets 403', function () {
        $booking = Booking::factory()->forClient($this->client)->create([
            'status' => 'received',
            'caregiver_id' => null,
        ]);

        $this->actingAs($this->caregiver->user);

        $this->get(route('jobs.show', $booking))
            ->assertForbidden();
    });
});

describe('Job show — admin access', function () {
    test('admin hitting jobs.show is redirected to the admin booking view', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = Booking::factory()->forClient($this->client)->create();

        $this->actingAs($admin)
            ->get(route('jobs.show', $booking))
            ->assertRedirect(route('bookings.show', $booking->ulid));
    });

    test('admin following the SMS short link is redirected to the admin booking view', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = Booking::factory()->forClient($this->client)->create();

        $this->actingAs($admin)
            ->get(route('jobs.short', $booking))
            ->assertRedirect(route('bookings.show', $booking->ulid));
    });

    test('super admin is redirected too', function () {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $booking = Booking::factory()->forClient($this->client)->create();

        $this->actingAs($superAdmin)
            ->get(route('jobs.show', $booking))
            ->assertRedirect(route('bookings.show', $booking->ulid));
    });

    test('admin hitting jobs index is redirected to the admin booking list', function () {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('jobs.index'))
            ->assertRedirect(route('bookings.index'));
    });
});

describe('Job show — Corporate (Care.com) Job #', function () {
    test('an assigned caregiver sees the Corporate Job # for a Care.com booking', function () {
        $booking = Booking::factory()->withBookingGroup(fn ($g) => $g->state([
            'client_id' => $this->client->id,
            'corporate_id' => 'CARE-12345',
        ]))->create([
            'status' => 'confirmed',
            'caregiver_id' => $this->caregiver->id,
        ]);

        $this->actingAs($this->caregiver->user);

        $this->get(route('jobs.show', $booking))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('caregiver/jobs/show')
                ->where('booking.corporate_id', 'CARE-12345')
            );
    });

    test('a non-corporate booking exposes a null Corporate Job # (nothing shown)', function () {
        $booking = Booking::factory()->forClient($this->client)->create([
            'status' => 'confirmed',
            'caregiver_id' => $this->caregiver->id,
        ]);

        $this->actingAs($this->caregiver->user);

        $this->get(route('jobs.show', $booking))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->where('booking.corporate_id', null)
            );
    });

    test('the My Jobs list carries the Corporate Job # for a Care.com booking', function () {
        Booking::factory()->withBookingGroup(fn ($g) => $g->state([
            'client_id' => $this->client->id,
            'corporate_id' => 'CARE-99999',
        ]))->create([
            'status' => 'completed',
            'caregiver_id' => $this->caregiver->id,
        ]);

        $this->actingAs($this->caregiver->user);

        $this->get(route('jobs.index'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('caregiver/jobs/index')
                ->where('jobs.data.0.corporate_id', 'CARE-99999')
            );
    });
});

describe('Job show — claimed by another caregiver (PII-free)', function () {
    test('invited caregiver sees the filled page, not the client PII, when another caregiver claimed the job', function () {
        $otherCaregiver = Caregiver::factory()->create();
        $booking = Booking::factory()->forClient($this->client)->create([
            'status' => 'confirmed',
            'caregiver_id' => $otherCaregiver->id,
        ]);

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $this->caregiver->id,
            'notified_at' => now(),
        ]);

        $this->actingAs($this->caregiver->user);

        $response = $this->get(route('jobs.show', $booking))->assertSuccessful();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('caregiver/jobs/filled')
            ->missing('booking')
        );

        $response->assertDontSee($this->client->user->email)
            ->assertDontSee($this->client->first_name);
    });
});

describe('Job index — early checkout availability', function () {
    test('an in-progress job (started, not yet ended) is listed with the fields needed to check out early', function () {
        // Underway: started an hour ago, still scheduled to end in an hour. The
        // Checkout button now gates on the job having started (not on the planned
        // end passing), so this job must ship start_datetime + a null resolution.
        Booking::factory()->forClient($this->client)->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => 'confirmed',
            'start_datetime' => now()->subHour(),
            'end_datetime' => now()->addHour(),
        ]);

        $this->actingAs($this->caregiver->user);

        $this->get(route('jobs.index'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('caregiver/jobs/index')
                ->has('jobs.data', 1)
                ->where('jobs.data.0.status', 'confirmed')
                ->where('jobs.data.0.assignment_resolution', null)
                ->has('jobs.data.0.start_datetime')
            );
    });
});
