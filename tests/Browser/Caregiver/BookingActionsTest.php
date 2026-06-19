<?php

use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\CertificationType;
use App\Models\Client;
use App\Models\Location;
use App\Models\SpecialtyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('caregiver can view available booking detail', function () {
    SpecialtyType::factory()->count(5)->create(['is_active' => true]);
    Location::factory()->count(5)->create(['is_active' => true]);
    AttributeDefinition::factory()->count(5)->create(['is_active' => true, 'entity_type' => 'caregiver']);
    CertificationType::factory()->count(5)->create(['is_active' => true]);

    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);
    $booking = Booking::factory()->forClient($client)->create();

    $caregiverUser = createCaregiver();

    BookingCaregiverNotification::create([
        'booking_id' => $booking->id,
        'caregiver_id' => $caregiverUser->caregiver->id,
        'notified_at' => now(),
        'claimed' => false,
    ]);

    $this->actingAs($caregiverUser);

    visit("/bookings/{$booking->ulid}")
        ->assertSee('Accept')
        ->assertNoJavaScriptErrors();
});

test('caregiver can view confirmed job detail', function () {
    SpecialtyType::factory()->count(5)->create(['is_active' => true]);
    Location::factory()->count(5)->create(['is_active' => true]);
    AttributeDefinition::factory()->count(5)->create(['is_active' => true, 'entity_type' => 'caregiver']);
    CertificationType::factory()->count(5)->create(['is_active' => true]);

    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);
    $booking = Booking::factory()->forClient($client)->confirmed()->create();

    $caregiverUser = $booking->caregiver->user;

    $this->actingAs($caregiverUser);

    visit("/jobs/{$booking->ulid}")
        ->assertNoJavaScriptErrors();
});
