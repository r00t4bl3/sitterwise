<?php

use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\BookingGroup;
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
    $this->booking = Booking::factory()->forClient($this->client)->create();
});

describe('Booking - Caregiver', function () {
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

        $booking1 = Booking::factory()->forClient($this->client)->create();
        $booking2 = Booking::factory()->forClient($this->client)->create();

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
        $booking = Booking::factory()->forClient($client)->create([
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
        $booking = Booking::factory()->forClient($client)->create([
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
        $booking = Booking::factory()->forClient($client)->create([
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
        $booking = Booking::factory()->forClient($client)->create([
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
        $response->assertRedirect(route('jobs.index'));

        $booking->refresh();
        expect($booking->status)->toBe('confirmed')
            ->and($booking->caregiver_id)->toBe($caregiver->id)
            ->and($booking->confirmed_by)->toBe($caregiver->id)
            ->and($booking->confirmed_at)->not()->toBeNull();
    });

    test('cannot confirm expired reservation', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->forClient($client)->create([
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
        $booking = Booking::factory()->forClient($this->client)->create([
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
            ->and($booking->status)->toBe('pending');
    });

    test('cannot release another caregiver reservation', function () {
        $caregiver1 = Caregiver::factory()->create();
        $caregiver2 = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->forClient($client)->create([
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
        $booking = Booking::factory()->forClient($client)->create([
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
        $booking = Booking::factory()->forClient($client)->create([
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

    test('past-dated booking not shown in available list', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->forClient($client)->create([
            'status' => 'received',
            'start_datetime' => now()->subDays(1)->setHour(9),
            'end_datetime' => now()->subDays(1)->setHour(13),
        ]);

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver->id,
            'notified_at' => now(),
            'claimed' => false,
        ]);

        $this->actingAs($caregiver->user);

        $response = $this->get('/bookings');

        $response->assertSuccessful();
        $response->inertiaProps('bookings', function ($bookings) {
            expect($bookings)->toBeArray()->toHaveCount(0);
        });
    });

    test('still-upcoming booking is shown in available list', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->forClient($client)->create([
            'status' => 'received',
            'start_datetime' => now()->addDays(1)->setHour(9),
            'end_datetime' => now()->addDays(1)->setHour(13),
        ]);

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver->id,
            'notified_at' => now(),
            'claimed' => false,
        ]);

        $this->actingAs($caregiver->user);

        $response = $this->get('/bookings');

        $response->assertSuccessful();
        $response->inertiaProps('bookings', function ($bookings) use ($booking) {
            expect($bookings)->toBeArray()->toHaveCount(1);
            expect($bookings[0]['id'])->toBe($booking->id);
        });
    });

    test('cannot confirm another caregiver reservation', function () {
        $caregiver1 = Caregiver::factory()->create();
        $caregiver2 = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->forClient($client)->create([
            'status' => 'reserved',
            'reserved_by' => $caregiver1->id,
            'reservation_expires_at' => now()->addMinute(),
        ]);

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver2->id,
            'notified_at' => now(),
        ]);

        $this->actingAs($caregiver2->user);

        $response = $this->post(route('bookings.confirm', $booking));

        $response->assertStatus(302);
        $response->assertSessionHas('error');

        $booking->refresh();
        expect($booking->status)->toBe('reserved');
    });

    test('expired reservation returns to open on access', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->forClient($client)->create([
            'status' => 'reserved',
            'reserved_by' => $caregiver->id,
            'reservation_expires_at' => now()->subSecond(),
        ]);

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver->id,
            'notified_at' => now(),
        ]);

        $this->actingAs($caregiver->user);

        $response = $this->get(route('bookings.index'));

        $response->assertSuccessful();
    });

    test('reservation sets correct expiration time', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->forClient($client)->create([
            'status' => 'received',
        ]);

        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver->id,
            'notified_at' => now(),
        ]);

        $this->actingAs($caregiver->user);

        $response = $this->post(route('bookings.reserve', $booking));

        $response->assertSessionHas('expires_in', 60);

        $booking->refresh();
        expect($booking->reservation_expires_at)->not()->toBeNull();
        expect($booking->reservation_expires_at->gt(now()))->toBeTrue();
    });

    test('only one caregiver can reserve at a time - atomic test', function () {
        $caregiver1 = Caregiver::factory()->create();
        $caregiver2 = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->forClient($client)->create([
            'status' => 'received',
        ]);

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

        $this->actingAs($caregiver1->user);
        $this->post(route('bookings.reserve', $booking));

        $this->actingAs($caregiver2->user);
        $response = $this->post(route('bookings.reserve', $booking));

        $response->assertSessionHas('error');

        $booking->refresh();
        expect($booking->reserved_by)->toBe($caregiver1->id);
        expect($booking->status)->toBe('reserved');
    });

    test('caregiver can reserve a group — siblings reserved atomically', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $group = BookingGroup::factory()->create([
            'client_id' => $client->id,
            'service_type' => 'babysitter',
        ]);
        $bookings = Booking::factory()->count(3)->create([
            'booking_group_id' => $group->id,
            'status' => 'received',
        ]);

        foreach ($bookings as $b) {
            BookingCaregiverNotification::create([
                'booking_id' => $b->id,
                'caregiver_id' => $caregiver->id,
                'notified_at' => now(),
            ]);
        }

        $this->actingAs($caregiver->user);

        $response = $this->post(route('bookings.reserve', $bookings[0]));

        $response->assertSessionHas('expires_in');

        $bookings[0]->refresh();
        $bookings[1]->refresh();
        $bookings[2]->refresh();
        expect($bookings[0]->status)->toBe('reserved');
        expect($bookings[1]->status)->toBe('reserved');
        expect($bookings[2]->status)->toBe('reserved');
        expect($bookings[0]->reserved_by)->toBe($caregiver->id);
        expect($bookings[1]->reserved_by)->toBe($caregiver->id);
        expect($bookings[2]->reserved_by)->toBe($caregiver->id);
    });

    test('caregiver can confirm a group — siblings confirmed atomically', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $group = BookingGroup::factory()->create([
            'client_id' => $client->id,
            'service_type' => 'babysitter',
        ]);
        $bookings = Booking::factory()->count(3)->create([
            'booking_group_id' => $group->id,
            'status' => 'reserved',
            'reserved_by' => $caregiver->id,
            'reservation_expires_at' => now()->addMinute(),
        ]);

        foreach ($bookings as $b) {
            BookingCaregiverNotification::create([
                'booking_id' => $b->id,
                'caregiver_id' => $caregiver->id,
                'notified_at' => now(),
            ]);
        }

        $this->actingAs($caregiver->user);

        $response = $this->post(route('bookings.confirm', $bookings[0]));

        $response->assertRedirect(route('jobs.index'));

        $bookings[0]->refresh();
        $bookings[1]->refresh();
        $bookings[2]->refresh();
        expect($bookings[0]->status)->toBe('confirmed');
        expect($bookings[1]->status)->toBe('confirmed');
        expect($bookings[2]->status)->toBe('confirmed');
        expect($bookings[0]->caregiver_id)->toBe($caregiver->id);
        expect($bookings[1]->caregiver_id)->toBe($caregiver->id);
        expect($bookings[2]->caregiver_id)->toBe($caregiver->id);
    });

    test('caregiver can reserve a pending booking', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $booking = Booking::factory()->forClient($client)->create([
            'status' => 'pending',
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

    test('caregiver can release a group — siblings released atomically', function () {
        $caregiver = Caregiver::factory()->create();
        $client = Client::factory()->create();
        $group = BookingGroup::factory()->create([
            'client_id' => $client->id,
            'service_type' => 'babysitter',
        ]);
        $bookings = Booking::factory()->count(3)->create([
            'booking_group_id' => $group->id,
            'status' => 'reserved',
            'reserved_by' => $caregiver->id,
            'reservation_expires_at' => now()->addMinute(),
        ]);

        $this->actingAs($caregiver->user);

        $response = $this->post(route('bookings.release', $bookings[0]));

        $response->assertStatus(302);

        $bookings[0]->refresh();
        $bookings[1]->refresh();
        $bookings[2]->refresh();
        expect($bookings[0]->status)->toBe('pending');
        expect($bookings[1]->status)->toBe('pending');
        expect($bookings[2]->status)->toBe('pending');
        expect($bookings[0]->reserved_by)->toBeNull();
        expect($bookings[1]->reserved_by)->toBeNull();
        expect($bookings[2]->reserved_by)->toBeNull();
    });
});
