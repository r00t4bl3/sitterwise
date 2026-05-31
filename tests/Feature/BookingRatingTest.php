<?php

use App\Enums\BookingStatus;
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
    $this->withoutMiddleware(PreventRequestForgery::class);

    SpecialtyType::factory()->count(5)->create(['is_active' => true]);
    Location::factory()->count(5)->create(['is_active' => true]);
    AttributeDefinition::factory()->count(5)->create(['is_active' => true, 'entity_type' => 'caregiver']);
    CertificationType::factory()->count(5)->create(['is_active' => true]);

    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->clientUser = User::factory()->create(['role' => 'client']);
    $this->client = Client::factory()->create(['user_id' => $this->clientUser->id]);

    $this->caregiverUser = User::factory()->create(['role' => 'caregiver']);
    $this->caregiver = Caregiver::factory()->create([
        'user_id' => $this->caregiverUser->id,
        'rating' => 0,
    ]);

    $this->booking = Booking::factory()->forClient($this->client)->create([
        'caregiver_id' => $this->caregiver->id,
        'status' => BookingStatus::Completed->value,
    ]);
});

test('allows a client to rate a caregiver', function () {
    $response = $this->actingAs($this->clientUser)
        ->post(route('jobs.rate', $this->booking), [
            'rating' => 5,
            'comment' => 'Great service!',
            'type' => BookingRating::TYPE_CLIENT_TO_CAREGIVER,
        ]);

    $response->assertStatus(302);
    $this->assertDatabaseHas('booking_ratings', [
        'booking_id' => $this->booking->id,
        'rater_id' => $this->clientUser->id,
        'ratable_id' => $this->caregiver->id,
        'ratable_type' => Caregiver::class,
        'rating' => 5,
    ]);

    $this->assertEquals('5.00', $this->caregiver->fresh()->rating);
});

test('allows a caregiver to rate a client', function () {
    $response = $this->actingAs($this->caregiverUser)
        ->post(route('jobs.rate', $this->booking), [
            'rating' => 4.5,
            'comment' => 'Very polite client.',
            'type' => BookingRating::TYPE_CAREGIVER_TO_CLIENT,
        ]);

    $response->assertStatus(302);
    $this->assertDatabaseHas('booking_ratings', [
        'booking_id' => $this->booking->id,
        'rater_id' => $this->caregiverUser->id,
        'ratable_id' => $this->client->id,
        'ratable_type' => Client::class,
        'rating' => 4.5,
    ]);

    $this->assertEquals('4.50', $this->client->fresh()->rating);
});

test('prevents unauthorized users from rating a job', function () {
    $otherUser = User::factory()->create(['role' => 'client']);
    Client::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($otherUser)
        ->post(route('jobs.rate', $this->booking), [
            'rating' => 5,
            'type' => BookingRating::TYPE_CLIENT_TO_CAREGIVER,
        ]);

    $response->assertStatus(403);
});

test('enforces unique rating per rater per booking per direction', function () {
    // First rating
    $this->actingAs($this->clientUser)
        ->post(route('jobs.rate', $this->booking), [
            'rating' => 5,
            'type' => BookingRating::TYPE_CLIENT_TO_CAREGIVER,
        ]);

    // Second rating (update)
    $response = $this->actingAs($this->clientUser)
        ->post(route('jobs.rate', $this->booking), [
            'rating' => 3,
            'type' => BookingRating::TYPE_CLIENT_TO_CAREGIVER,
        ]);

    $response->assertStatus(302);
    $this->assertEquals(1, BookingRating::count());
    $this->assertEquals('3.00', BookingRating::first()->rating);
    $this->assertEquals('3.00', $this->caregiver->fresh()->rating);
});

test('allows admin to update global admin rating', function () {
    $response = $this->actingAs($this->admin)
        ->put(route('caregivers.updateAdminRating', $this->caregiver), [
            'admin_rating' => 4.25,
        ]);

    $response->assertStatus(302);
    $this->assertEquals('4.25', $this->caregiver->fresh()->admin_rating);
});

test('correctly recalculates average rating and ignores soft deleted ratings', function () {
    $booking2 = Booking::factory()->forClient($this->client)->create([
        'caregiver_id' => $this->caregiver->id,
        'status' => BookingStatus::Completed->value,
    ]);

    // Rating 1: 5 stars
    $this->actingAs($this->clientUser)
        ->post(route('jobs.rate', $this->booking), [
            'rating' => 5,
            'type' => BookingRating::TYPE_CLIENT_TO_CAREGIVER,
        ]);

    // Rating 2: 3 stars
    $this->actingAs($this->clientUser)
        ->post(route('jobs.rate', $booking2), [
            'rating' => 3,
            'type' => BookingRating::TYPE_CLIENT_TO_CAREGIVER,
        ]);

    $this->assertEquals('4.00', $this->caregiver->fresh()->rating);

    // Soft delete one rating
    BookingRating::where('booking_id', $this->booking->id)->delete();

    $this->caregiver->recalculateRating();

    $this->assertEquals('3.00', $this->caregiver->fresh()->rating);
});
