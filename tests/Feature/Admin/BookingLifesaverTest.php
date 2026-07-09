<?php

use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SpecialtyTypeSeeder;

beforeEach(function () {
    $this->seed([
        SettingsSeeder::class,
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create();
    $this->booking = Booking::factory()->forClient($this->client)->create([
        'status' => 'received',
        'caregiver_id' => null,
        'start_datetime' => now()->addDays(10),
        'end_datetime' => now()->addDays(10)->addHours(4),
    ]);
});

describe('Admin Lifesaver toggle', function () {
    test('admin can manually flag a booking as a Lifesaver', function () {
        $this->actingAs($this->admin)
            ->post(route('bookings.lifesaver', $this->booking), ['lifesaver_override' => true])
            ->assertRedirect()
            ->assertSessionHas('success');

        expect($this->booking->fresh()->lifesaver_override)->toBeTrue();
    });

    test('admin can force it off, and reset to automatic', function () {
        $this->actingAs($this->admin)
            ->post(route('bookings.lifesaver', $this->booking), ['lifesaver_override' => false]);
        expect($this->booking->fresh()->lifesaver_override)->toBeFalse();

        $this->actingAs($this->admin)
            ->post(route('bookings.lifesaver', $this->booking), ['lifesaver_override' => null]);
        expect($this->booking->fresh()->lifesaver_override)->toBeNull();
    });

    test('a caregiver cannot toggle the Lifesaver flag', function () {
        $caregiver = Caregiver::factory()->create();

        $this->actingAs($caregiver->user)
            ->post(route('bookings.lifesaver', $this->booking), ['lifesaver_override' => true])
            ->assertForbidden();

        expect($this->booking->fresh()->lifesaver_override)->toBeNull();
    });

    test('the booking detail page exposes the computed Lifesaver state', function () {
        $this->booking->update(['lifesaver_override' => true]);

        $this->actingAs($this->admin)
            ->get(route('bookings.show', $this->booking))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('booking.is_lifesaver', true)
                ->where('booking.lifesaver_override', true)
            );
    });
});
