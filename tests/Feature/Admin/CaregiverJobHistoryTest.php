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
        $client->update(['last_name' => 'SearchableClient']);

        Booking::factory()->forClient($client)->create([
            'caregiver_id' => $this->caregiver->id,
        ]);
        Booking::factory()->forClient(Client::factory()->create())->create([
            'caregiver_id' => $this->caregiver->id,
        ]);

        $this->actingAs($this->admin);

        $response = $this->get(route('caregivers.jobHistory', [$this->caregiver, 'search' => 'Searchable']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 1)
        );
    });

    test('job history can be filtered by location type', function () {
        $booking = Booking::factory()->forClient(Client::factory()->create())->create([
            'caregiver_id' => $this->caregiver->id,
        ]);
        Booking::factory()->forClient(Client::factory()->create())->create([
            'caregiver_id' => $this->caregiver->id,
        ]);

        $this->actingAs($this->admin);

        $response = $this->get(route('caregivers.jobHistory', [$this->caregiver, 'search' => 'private']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 2)
        );
    });

    test('job history paginates results', function () {
        Booking::factory()->count(25)->create(['caregiver_id' => $this->caregiver->id]);

        $this->actingAs($this->admin);

        $response = $this->get(route('caregivers.jobHistory', $this->caregiver));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 10)
            ->where('bookings.total', 25)
            ->where('bookings.last_page', 3)
        );
    });

    test('job history persists filter in pagination links', function () {
        Booking::factory()->count(25)->create(['caregiver_id' => $this->caregiver->id, 'status' => 'confirmed']);
        Booking::factory()->count(5)->create(['caregiver_id' => $this->caregiver->id, 'status' => 'completed']);

        $this->actingAs($this->admin);

        $response = $this->get(route('caregivers.jobHistory', [$this->caregiver, 'status' => 'confirmed']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 10)
            ->where('bookings.total', 25)
        );
    });

    test('backed-out jobs remain visible in job history with their resolution', function () {
        $booking = Booking::factory()->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => 'confirmed',
        ]);
        $assignment = $booking->assignments()->firstOrFail();

        // Caregiver backs out: this nulls bookings.caregiver_id but keeps the
        // assignment row (resolution = backed_out). The job must still appear.
        $this->actingAs($this->caregiver->user)
            ->post(route('assignments.back-out', $assignment), ['reason' => 'Family emergency'])
            ->assertRedirect();

        expect($booking->fresh()->caregiver_id)->toBeNull();

        $this->actingAs($this->admin);
        $response = $this->get(route('caregivers.jobHistory', $this->caregiver));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings.data', 1)
            ->where('bookings.data.0.assignment_resolution', 'backed_out')
            ->where('bookings.data.0.assignment_id', $assignment->id)
        );
    });

    test('an ongoing job with no own assignment row shows as Pending, not a prior caregiver resolution', function () {
        // The job was handed to this caregiver via a path that left no assignment
        // row; the only row belongs to the reassigned prior caregiver. History must
        // show the ongoing job as Pending (null), not borrow "Reassigned".
        $priorCaregiver = Caregiver::factory()->create();
        $booking = Booking::factory()->create([
            'caregiver_id' => null,
            'status' => 'confirmed',
        ]);
        $booking->assignments()->create([
            'caregiver_id' => $priorCaregiver->id,
            'assigned_at' => now()->subDay(),
            'resolution' => 'reassigned',
            'resolution_at' => now()->subDay(),
        ]);
        $booking->updateQuietly(['caregiver_id' => $this->caregiver->id]);

        $this->actingAs($this->admin);
        $this->get(route('caregivers.jobHistory', $this->caregiver))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->has('bookings.data', 1)
                ->where('bookings.data.0.assignment_resolution', null)
                ->where('bookings.data.0.assignment_resolution_label', null)
            );
    });

    test('a job another caregiver backed out of does not leak into this caregiver history', function () {
        $other = Caregiver::factory()->create();
        $booking = Booking::factory()->create([
            'caregiver_id' => $other->id,
            'status' => 'confirmed',
        ]);
        $assignment = $booking->assignments()->firstOrFail();

        $this->actingAs($other->user)
            ->post(route('assignments.back-out', $assignment), ['reason' => 'Scheduling conflict']);

        // The broadened (assignment-based) query must still be caregiver-scoped:
        // this caregiver has no jobs, so the other caregiver's back-out must not show.
        $this->actingAs($this->admin);
        $this->get(route('caregivers.jobHistory', $this->caregiver))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->has('bookings.data', 0));
    });

    test('a backed-out job can be excused now that it is visible', function () {
        $booking = Booking::factory()->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => 'confirmed',
        ]);
        $assignment = $booking->assignments()->firstOrFail();

        $this->actingAs($this->caregiver->user)
            ->post(route('assignments.back-out', $assignment), ['reason' => 'Sick']);

        $this->actingAs($this->admin)
            ->post(route('assignments.excuse', $assignment), ['note' => 'Approved leave'])
            ->assertRedirect();

        expect($assignment->fresh()->resolution)->toBe('backed_out_excused');
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
