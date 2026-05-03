<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use App\Models\CertificationType;
use App\Models\Client;
use App\Models\Location;
use App\Models\SpecialtyType;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BookingReviewTest extends TestCase
{
    use RefreshDatabase;

    protected $clientUser;

    protected $client;

    protected $caregiverUser;

    protected $caregiver;

    protected $completedBooking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);

        CaregiverStatus::factory()->create(['is_active' => true]);
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
    }

    public function test_review_form_accessible_for_logged_in_client()
    {
        $response = $this->actingAs($this->clientUser)
            ->get(route('reviews.create', $this->completedBooking));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('booking')
            ->where('booking.caregiver_name', $this->caregiver->first_name.' '.$this->caregiver->last_name)
        );
    }

    public function test_review_form_only_works_for_completed_bookings()
    {
        $pendingBooking = Booking::factory()->create([
            'client_id' => $this->client->id,
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Pending->value,
        ]);

        $response = $this->actingAs($this->clientUser)->get(route('reviews.create', $pendingBooking));

        $response->assertStatus(403);
    }

    public function test_review_form_only_works_for_own_bookings()
    {
        $otherClientUser = User::factory()->create(['role' => 'client']);
        $otherClient = Client::factory()->create(['user_id' => $otherClientUser->id]);

        $otherBooking = Booking::factory()->create([
            'client_id' => $otherClient->id,
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Completed->value,
        ]);

        $response = $this->actingAs($this->clientUser)->get(route('reviews.create', $otherBooking));

        $response->assertStatus(403);
    }

    public function test_client_can_submit_review_with_rating_and_comment()
    {
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
    }

    public function test_review_can_be_updated()
    {
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
    }

    public function test_rating_is_required()
    {
        $response = $this->actingAs($this->clientUser)->post(route('reviews.store', $this->completedBooking), [
            'rating' => '',
            'comment' => 'Some comment',
        ]);

        $response->assertSessionHasErrors('rating');
    }

    public function test_rating_must_be_between_1_and_5()
    {
        $response = $this->actingAs($this->clientUser)->post(route('reviews.store', $this->completedBooking), [
            'rating' => 6,
            'comment' => 'Invalid rating',
        ]);

        $response->assertSessionHasErrors('rating');
    }

    public function test_comment_is_optional()
    {
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
    }

    public function test_tip_field_is_optional()
    {
        $response = $this->actingAs($this->clientUser)->post(route('reviews.store', $this->completedBooking), [
            'rating' => 5,
            'comment' => 'Nice',
            'tip' => '',
        ]);

        $response->assertSessionHas('success');
        $response->assertStatus(302);
    }

    public function test_existing_review_data_is_prepopulated()
    {
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
    }

    // ========== GUEST/NON-LOGGED-IN CLIENT TESTS ==========

    public function test_guest_can_access_review_via_signed_url()
    {
        $signedUrl = URL::signedRoute('review.create', [
            'booking' => $this->completedBooking->ulid,
        ]);

        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('booking')
            ->where('booking.caregiver_name', $this->caregiver->first_name.' '.$this->caregiver->last_name)
        );
    }

    public function test_guest_can_submit_review_via_signed_url()
    {
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
    }

    public function test_guest_review_uses_booking_client_user_id_as_rater()
    {
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
    }

    public function test_invalid_signed_url_rejected()
    {
        $invalidUrl = route('review.create', $this->completedBooking->ulid).'?signature=invalid';

        $response = $this->get($invalidUrl);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_review_non_completed_booking()
    {
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
    }
}
