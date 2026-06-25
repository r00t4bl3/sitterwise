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

test('saved observer recalculates caregiver rating on create', function () {
    BookingRating::create([
        'booking_id' => $this->booking->id,
        'rater_id' => $this->clientUser->id,
        'ratable_type' => Caregiver::class,
        'ratable_id' => $this->caregiver->id,
        'rating' => 4.5,
    ]);

    expect($this->caregiver->fresh()->rating)->toEqual('4.50');
});

test('saved observer recalculates caregiver rating on update', function () {
    $rating = BookingRating::create([
        'booking_id' => $this->booking->id,
        'rater_id' => $this->clientUser->id,
        'ratable_type' => Caregiver::class,
        'ratable_id' => $this->caregiver->id,
        'rating' => 3,
    ]);

    $rating->update(['rating' => 5]);

    expect($this->caregiver->fresh()->rating)->toEqual('5.00');
});

test('deleted observer recalculates caregiver rating on soft delete', function () {
    BookingRating::create([
        'booking_id' => $this->booking->id,
        'rater_id' => $this->clientUser->id,
        'ratable_type' => Caregiver::class,
        'ratable_id' => $this->caregiver->id,
        'rating' => 4,
    ]);

    $this->caregiver->fresh()->rating;

    $rating = BookingRating::first();
    $rating->delete();

    expect($this->caregiver->fresh()->rating)->toEqual('0.00');
});

test('saved observer recalculates caregiver rating on restore', function () {
    BookingRating::create([
        'booking_id' => $this->booking->id,
        'rater_id' => $this->clientUser->id,
        'ratable_type' => Caregiver::class,
        'ratable_id' => $this->caregiver->id,
        'rating' => 4,
    ]);

    $rating = BookingRating::first();
    $rating->delete();

    expect($this->caregiver->fresh()->rating)->toEqual('0.00');

    $rating->restore();

    expect($this->caregiver->fresh()->rating)->toEqual('4.00');
});

test('saved observer recalculates client rating on create for caregiver-to-client ratings', function () {
    BookingRating::create([
        'booking_id' => $this->booking->id,
        'rater_id' => $this->caregiverUser->id,
        'ratable_type' => Client::class,
        'ratable_id' => $this->client->id,
        'rating' => 5,
    ]);

    expect($this->client->fresh()->rating)->toEqual('5.00');
});
