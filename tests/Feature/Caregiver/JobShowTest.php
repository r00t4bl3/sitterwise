<?php

use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\Caregiver;
use App\Models\Client;
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
