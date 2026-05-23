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
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    SpecialtyType::factory()->count(5)->create(['is_active' => true]);
    Location::factory()->count(5)->create(['is_active' => true]);
    AttributeDefinition::factory()->count(5)->create(['is_active' => true, 'entity_type' => 'caregiver']);
    CertificationType::factory()->count(5)->create(['is_active' => true]);

    $this->clientUser = User::factory()->create(['role' => 'client']);
    $this->client = Client::factory()->create(['user_id' => $this->clientUser->id]);

    $this->caregiverUser = User::factory()->create(['role' => 'caregiver']);
    $this->caregiver = Caregiver::factory()->create([
        'user_id' => $this->caregiverUser->id,
        'rating' => 0,
    ]);

    $this->completedBooking = Booking::factory()->create([
        'client_id' => $this->client->id,
        'caregiver_id' => $this->caregiver->id,
        'status' => BookingStatus::Completed->value,
    ]);
});

test('review form accessible for logged in client', function () {
    $response = $this->actingAs($this->clientUser)
        ->get(route('reviews.create', $this->completedBooking));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->has('booking')
        ->where('booking.caregiver_name', $this->caregiver->first_name.' '.$this->caregiver->last_name)
    );
});

test('review form only works for completed bookings', function () {
    $pendingBooking = Booking::factory()->create([
        'client_id' => $this->client->id,
        'caregiver_id' => $this->caregiver->id,
        'status' => BookingStatus::Pending->value,
    ]);

    $response = $this->actingAs($this->clientUser)->get(route('reviews.create', $pendingBooking));

    $response->assertStatus(403);
});

test('review form only works for own bookings', function () {
    $otherClientUser = User::factory()->create(['role' => 'client']);
    $otherClient = Client::factory()->create(['user_id' => $otherClientUser->id]);

    $otherBooking = Booking::factory()->create([
        'client_id' => $otherClient->id,
        'caregiver_id' => $this->caregiver->id,
        'status' => BookingStatus::Completed->value,
    ]);

    $response = $this->actingAs($this->clientUser)->get(route('reviews.create', $otherBooking));

    $response->assertStatus(403);
});

test('client can submit review with rating and comment', function () {
    $response = $this->actingAs($this->clientUser)->post(route('reviews.store', $this->completedBooking), [
        'rating' => 5,
        'comment' => 'Great caregiver! Very professional.',
        'tip' => '',
    ]);

    $response->assertSessionHas('success');
    $response->assertStatus(302);

    $this->assertDatabaseHas('booking_ratings', [
        'booking_id' => $this->completedBooking->id,
        'rater_id' => $this->clientUser->id,
        'ratable_id' => $this->caregiver->id,
        'ratable_type' => Caregiver::class,
        'rating' => 5,
        'comment' => 'Great caregiver! Very professional.',
    ]);
});

test('review can be updated', function () {
    BookingRating::create([
        'booking_id' => $this->completedBooking->id,
        'rater_id' => $this->clientUser->id,
        'ratable_id' => $this->caregiver->id,
        'ratable_type' => Caregiver::class,
        'rating' => 4,
        'comment' => 'Good service',
    ]);

    $response = $this->actingAs($this->clientUser)->post(route('reviews.store', $this->completedBooking), [
        'rating' => 5,
        'comment' => 'Updated: Actually excellent service!',
        'tip' => '',
    ]);

    $response->assertSessionHas('success');
    $response->assertStatus(302);

    $this->assertEquals(1, BookingRating::count());
    $this->assertEquals(5, BookingRating::first()->rating);
    $this->assertEquals('Updated: Actually excellent service!', BookingRating::first()->comment);
});

test('rating is required', function () {
    $response = $this->actingAs($this->clientUser)->post(route('reviews.store', $this->completedBooking), [
        'rating' => '',
        'comment' => 'Some comment',
    ]);

    $response->assertSessionHasErrors('rating');
});

test('rating must be between 1 and 5', function () {
    $response = $this->actingAs($this->clientUser)->post(route('reviews.store', $this->completedBooking), [
        'rating' => 6,
        'comment' => 'Invalid rating',
    ]);

    $response->assertSessionHasErrors('rating');
});

test('comment is optional', function () {
    $response = $this->actingAs($this->clientUser)->post(route('reviews.store', $this->completedBooking), [
        'rating' => 5,
        'comment' => '',
    ]);

    $response->assertSessionHas('success');
    $response->assertStatus(302);
    $this->assertDatabaseHas('booking_ratings', [
        'booking_id' => $this->completedBooking->id,
        'rating' => 5,
    ]);
});

test('tip field is optional', function () {
    $response = $this->actingAs($this->clientUser)->post(route('reviews.store', $this->completedBooking), [
        'rating' => 5,
        'comment' => 'Nice',
        'tip' => '',
    ]);

    $response->assertSessionHas('success');
    $response->assertStatus(302);
});

test('existing review data is prepopulated', function () {
    BookingRating::create([
        'booking_id' => $this->completedBooking->id,
        'rater_id' => $this->clientUser->id,
        'ratable_id' => $this->caregiver->id,
        'ratable_type' => Caregiver::class,
        'rating' => 4,
        'comment' => 'Original comment',
    ]);

    $this->completedBooking->update(['tip' => 10.00]);

    $response = $this->actingAs($this->clientUser)->get(route('reviews.create', $this->completedBooking));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->where('booking.existing_rating', '4.00')
        ->where('booking.existing_comment', 'Original comment')
        ->where('booking.existing_tip', '10.00')
    );
});

// ========== GUEST/NON-LOGGED-IN CLIENT TESTS ==========

test('guest can access review via signed url', function () {
    $signedUrl = URL::signedRoute('review.create', [
        'booking' => $this->completedBooking->ulid,
    ]);

    $response = $this->get($signedUrl);

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->has('booking')
        ->where('booking.caregiver_name', $this->caregiver->first_name.' '.$this->caregiver->last_name)
    );
});

test('guest can submit review via signed url', function () {
    $signedUrl = URL::signedRoute('review.store', [
        'booking' => $this->completedBooking->ulid,
    ]);

    $response = $this->post($signedUrl, [
        'rating' => 5,
        'comment' => 'Guest review test',
        'tip' => '',
    ]);

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->has('caregiver_name')
    );

    $this->assertDatabaseHas('booking_ratings', [
        'booking_id' => $this->completedBooking->id,
        'rater_id' => $this->client->user_id,
        'rating' => 5,
        'comment' => 'Guest review test',
    ]);
});

test('guest review uses booking client user id as rater', function () {
    $signedUrl = URL::signedRoute('review.store', [
        'booking' => $this->completedBooking->ulid,
    ]);

    $this->post($signedUrl, [
        'rating' => 4,
        'comment' => 'Test rating from guest',
        'tip' => '',
    ]);

    $rating = BookingRating::where('booking_id', $this->completedBooking->id)->first();
    $this->assertEquals($this->client->user_id, $rating->rater_id);
});

test('invalid signed url rejected', function () {
    $invalidUrl = route('review.create', $this->completedBooking->ulid).'?signature=invalid';

    $response = $this->get($invalidUrl);

    $response->assertStatus(403);
});

test('guest cannot review non completed booking', function () {
    $pendingBooking = Booking::factory()->create([
        'client_id' => $this->client->id,
        'caregiver_id' => $this->caregiver->id,
        'status' => BookingStatus::Pending->value,
    ]);

    $signedUrl = URL::signedRoute('review.create', [
        'booking' => $pendingBooking->ulid,
    ]);

    $response = $this->get($signedUrl);

    $response->assertStatus(403);
});
