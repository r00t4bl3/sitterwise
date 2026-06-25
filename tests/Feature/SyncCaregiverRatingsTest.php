<?php

use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Caregiver;
use App\Models\CertificationType;
use App\Models\Client;
use App\Models\Location;
use App\Models\SpecialtyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    SpecialtyType::factory()->count(5)->create(['is_active' => true]);
    Location::factory()->count(5)->create(['is_active' => true]);
    AttributeDefinition::factory()->count(5)->create(['is_active' => true, 'entity_type' => 'caregiver']);
    CertificationType::factory()->count(5)->create(['is_active' => true]);

    $this->clientUser = User::factory()->create(['role' => 'client']);
    $this->client = Client::factory()->create(['user_id' => $this->clientUser->id]);

    $this->caregiverUser = User::factory()->create(['role' => 'caregiver']);
    $this->caregiver = Caregiver::factory()->create([
        'user_id' => $this->caregiverUser->id,
        'rating' => null,
    ]);

    $this->booking = Booking::factory()->forClient($this->client)->create([
        'caregiver_id' => $this->caregiver->id,
    ]);
});

test('command syncs ratings from booking_ratings', function () {
    BookingRating::create([
        'booking_id' => $this->booking->id,
        'rater_id' => $this->clientUser->id,
        'ratable_type' => Caregiver::class,
        'ratable_id' => $this->caregiver->id,
        'rating' => 4,
    ]);

    // Simulate stale cache by resetting rating to null (as if created pre-observer)
    $this->caregiver->update(['rating' => null]);

    $this->artisan('app:sync-caregiver-ratings')
        ->assertSuccessful();

    expect($this->caregiver->fresh()->rating)->toEqual('4.00');
});

test('command syncs multiple ratings', function () {
    $booking2 = Booking::factory()->forClient($this->client)->create([
        'caregiver_id' => $this->caregiver->id,
    ]);

    BookingRating::create([
        'booking_id' => $this->booking->id,
        'rater_id' => $this->clientUser->id,
        'ratable_type' => Caregiver::class,
        'ratable_id' => $this->caregiver->id,
        'rating' => 5,
    ]);

    BookingRating::create([
        'booking_id' => $booking2->id,
        'rater_id' => $this->clientUser->id,
        'ratable_type' => Caregiver::class,
        'ratable_id' => $this->caregiver->id,
        'rating' => 3,
    ]);

    $this->caregiver->update(['rating' => null]);

    $this->artisan('app:sync-caregiver-ratings')
        ->assertSuccessful();

    expect($this->caregiver->fresh()->rating)->toEqual('4.00');
});

test('command sets rating to 0 for caregivers with no ratings', function () {
    $this->artisan('app:sync-caregiver-ratings')
        ->assertSuccessful();

    expect($this->caregiver->fresh()->rating)->toEqual('0.00');
});

test('command is idempotent', function () {
    BookingRating::create([
        'booking_id' => $this->booking->id,
        'rater_id' => $this->clientUser->id,
        'ratable_type' => Caregiver::class,
        'ratable_id' => $this->caregiver->id,
        'rating' => 3.5,
    ]);

    $this->caregiver->update(['rating' => null]);

    $this->artisan('app:sync-caregiver-ratings');
    $firstResult = $this->caregiver->fresh()->rating;

    $this->artisan('app:sync-caregiver-ratings');
    $secondResult = $this->caregiver->fresh()->rating;

    expect($firstResult)->toEqual($secondResult);
});

test('command ignores soft-deleted ratings', function () {
    BookingRating::create([
        'booking_id' => $this->booking->id,
        'rater_id' => $this->clientUser->id,
        'ratable_type' => Caregiver::class,
        'ratable_id' => $this->caregiver->id,
        'rating' => 5,
    ]);

    BookingRating::first()->delete();

    $this->caregiver->update(['rating' => null]);

    $this->artisan('app:sync-caregiver-ratings')
        ->assertSuccessful();

    expect($this->caregiver->fresh()->rating)->toEqual('0.00');
});
