<?php

use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\CertificationType;
use App\Models\Client;
use App\Models\Location;
use App\Models\SpecialtyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('caregiver can view completed job detail', function () {
    SpecialtyType::factory()->count(5)->create(['is_active' => true]);
    Location::factory()->count(5)->create(['is_active' => true]);
    AttributeDefinition::factory()->count(5)->create(['is_active' => true, 'entity_type' => 'caregiver']);
    CertificationType::factory()->count(5)->create(['is_active' => true]);

    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);
    $booking = Booking::factory()->forClient($client)->completed()->create();

    $caregiverUser = $booking->caregiver->user;

    $this->actingAs($caregiverUser);

    visit("/jobs/{$booking->ulid}")
        ->assertSee('Job Details')
        ->assertNoJavaScriptErrors();
});

test('caregiver job detail shows client information', function () {
    SpecialtyType::factory()->count(5)->create(['is_active' => true]);
    Location::factory()->count(5)->create(['is_active' => true]);
    AttributeDefinition::factory()->count(5)->create(['is_active' => true, 'entity_type' => 'caregiver']);
    CertificationType::factory()->count(5)->create(['is_active' => true]);

    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);
    $booking = Booking::factory()->forClient($client)->completed()->create();

    $caregiverUser = $booking->caregiver->user;

    $this->actingAs($caregiverUser);

    visit("/jobs/{$booking->ulid}")
        ->assertSee('Client Information')
        ->assertNoJavaScriptErrors();
});

test('caregiver job detail shows start and end times', function () {
    SpecialtyType::factory()->count(5)->create(['is_active' => true]);
    Location::factory()->count(5)->create(['is_active' => true]);
    AttributeDefinition::factory()->count(5)->create(['is_active' => true, 'entity_type' => 'caregiver']);
    CertificationType::factory()->count(5)->create(['is_active' => true]);

    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);
    $booking = Booking::factory()->forClient($client)->completed()->create();

    $caregiverUser = $booking->caregiver->user;

    $this->actingAs($caregiverUser);

    visit("/jobs/{$booking->ulid}")
        ->assertNoJavaScriptErrors();
});
