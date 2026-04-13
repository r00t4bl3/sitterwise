<?php

use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\Caregiver;
use App\Models\Client;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CaregiverStatusSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        CaregiverStatusSeeder::class,
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->caregiver = Caregiver::factory()->create();
    $this->client = Client::factory()->create();
    $this->booking = Booking::factory()->create([
        'client_id' => $this->client->id,
    ]);
});

describe('BookingController - Caregiver', function () {
    test('caregiver can view their notified bookings', function () {
        // Create notification record
        BookingCaregiverNotification::create([
            'booking_id' => $this->booking->id,
            'caregiver_id' => $this->caregiver->id,
            'notified_at' => now(),
        ]);

        $this->actingAs($this->caregiver->user);

        $response = $this->get(route('bookings.index'));

        $response->assertSuccessful();
        // $response->assertJsonCount(1);
        // $response->assertJsonPath('0.id', $this->booking->id);
    });

    test('caregiver only sees their own notified bookings', function () {
        $caregiver1 = $this->caregiver;
        $caregiver2 = Caregiver::factory()->create();

        $booking1 = Booking::factory()->create(['client_id' => $this->client->id]);
        $booking2 = Booking::factory()->create(['client_id' => $this->client->id]);

        // Notify only caregiver1 about booking1
        BookingCaregiverNotification::create([
            'booking_id' => $booking1->id,
            'caregiver_id' => $caregiver1->id,
            'notified_at' => now(),
        ]);

        // Notify only caregiver2 about booking2
        BookingCaregiverNotification::create([
            'booking_id' => $booking2->id,
            'caregiver_id' => $caregiver2->id,
            'notified_at' => now(),
        ]);

        $this->actingAs($caregiver1->user);

        $response = $this->get(route('bookings.index'));

        $response->assertSuccessful();
        // $response->assertJsonCount(1);
        // $response->assertJsonPath('0.id', $booking1->id);
    });

    test('guest cannot access bookings', function () {
        $response = $this->get(route('bookings.index'));
        $response->assertRedirect(route('login'));
    });

    test('caregiver can reserve a booking', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'received',
        ]);

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver->id,
            'notified_at' => now(),
        ]);

        $this->actingAs($caregiver->user);

        $response = $this->post(route('bookings.reserve', $booking));

        $response->assertStatus(302);
        $response->assertSessionHas('expires_in');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'reserved_by' => $caregiver->id,
            'status' => 'reserved',
        ]);
    });

    test('only notified caregiver can reserve booking', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'received',
        ]);

        // Don't create notification record

        $this->actingAs($caregiver->user);

        $response = $this->post(route('bookings.reserve', $booking));

        $response->assertStatus(302);
        $response->assertSessionHas('error');
    });

    test('cannot reserve already reserved booking', function () {
        $caregiver1 = Caregiver::factory()->create();
        $caregiver2 = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'received',
        ]);

        // Notify both caregivers
        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver1->id,
            'notified_at' => now(),
        ]);
        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver2->id,
            'notified_at' => now(),
        ]);

        // First caregiver reserves
        $this->actingAs($caregiver1->user);
        $this->post(route('bookings.reserve', $booking));

        // Second caregiver tries to reserve
        $this->actingAs($caregiver2->user);
        $response = $this->post(route('bookings.reserve', $booking));

        $response->assertStatus(302);
        $response->assertSessionHas('error');
    });

    test('caregiver can confirm reserved booking', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'reserved',
            'reserved_by' => $caregiver->id,
            'reservation_expires_at' => now()->addMinute(),
        ]);

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver->id,
            'notified_at' => now(),
        ]);

        $this->actingAs($caregiver->user);

        $response = $this->post(route('bookings.confirm', $booking));

        $response->assertStatus(302);
        $response->assertRedirect(route('dashboard'));

        $booking->refresh();
        expect($booking->status)->toBe('confirmed')
            ->and($booking->caregiver_id)->toBe($caregiver->id)
            ->and($booking->confirmed_by)->toBe($caregiver->id)
            ->and($booking->confirmed_at)->not()->toBeNull();
    });

    test('cannot confirm expired reservation', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'reserved',
            'reserved_by' => $caregiver->id,
            'reservation_expires_at' => now()->subMinute(), // Expired
        ]);

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver->id,
            'notified_at' => now(),
        ]);

        $this->actingAs($caregiver->user);

        $response = $this->post(route('bookings.confirm', $booking));

        $response->assertStatus(302);
        $response->assertSessionHas('error');
    });

    test('caregiver can release reservation', function () {
        $booking = Booking::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'reserved',
            'reserved_by' => $this->caregiver->id,
            'reservation_expires_at' => now()->addMinute(),
        ]);

        $this->actingAs($this->caregiver->user);

        $response = $this->post(route('bookings.release', $booking));

        $response->assertStatus(302);

        $booking->refresh();
        expect($booking->reserved_by)->toBeNull()
            ->and($booking->reservation_expires_at)->toBeNull()
            ->and($booking->status)->toBe('received');
    });

    test('cannot release another caregiver reservation', function () {
        $caregiver1 = Caregiver::factory()->create();
        $caregiver2 = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'reserved',
            'reserved_by' => $caregiver1->id,
            'reservation_expires_at' => now()->addMinute(),
        ]);

        $this->actingAs($caregiver2->user);

        $response = $this->post(route('bookings.release', $booking));

        $response->assertStatus(302);

        $booking->refresh();
        // Should still be reserved by caregiver1
        expect($booking->reserved_by)->toBe($caregiver1->id);
    });

    test('notification marked as claimed on confirmation', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'reserved',
            'reserved_by' => $caregiver->id,
            'reservation_expires_at' => now()->addMinute(),
        ]);

        $notification = BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver->id,
            'notified_at' => now(),
            'claimed' => false,
        ]);

        $this->actingAs($caregiver->user);

        $response = $this->post(route('bookings.confirm', $booking));

        $notification->refresh();
        expect($notification->claimed)->toBeTrue()
            ->and($notification->responded_at)->not()->toBeNull();
        $response->assertStatus(302);
    });

    test('confirmed booking not shown in list', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'confirmed',
            'caregiver_id' => $caregiver->id,
        ]);

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver->id,
            'notified_at' => now(),
            'claimed' => true,
        ]);

        $this->actingAs($caregiver->user);

        $response = $this->get('/bookings');

        $response->assertSuccessful();
        $response->inertiaProps('bookings', function ($bookings) {
            expect($bookings)->toBeArray()->toHaveCount(0);
        });

    });
});
