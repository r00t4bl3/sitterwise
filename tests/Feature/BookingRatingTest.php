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
use Tests\TestCase;

class BookingRatingTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected $clientUser;

    protected $client;

    protected $caregiverUser;

    protected $caregiver;

    protected $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);

        CaregiverStatus::factory()->create(['is_active' => true]);
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

        $this->booking = Booking::factory()->create([
            'client_id' => $this->client->id,
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Completed->value,
        ]);
    }

    public function test_allows_a_client_to_rate_a_caregiver()
    {
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
    }

    public function test_allows_a_caregiver_to_rate_a_client()
    {
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
    }

    public function test_prevents_unauthorized_users_from_rating_a_job()
    {
        $otherUser = User::factory()->create(['role' => 'client']);
        Client::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($otherUser)
            ->post(route('jobs.rate', $this->booking), [
                'rating' => 5,
                'type' => BookingRating::TYPE_CLIENT_TO_CAREGIVER,
            ]);

        $response->assertStatus(403);
    }

    public function test_enforces_unique_rating_per_rater_per_booking_per_direction()
    {
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
    }

    public function test_allows_admin_to_update_global_admin_rating()
    {
        $response = $this->actingAs($this->admin)
            ->put(route('caregivers.updateAdminRating', $this->caregiver), [
                'admin_rating' => 4.25,
            ]);

        $response->assertStatus(302);
        $this->assertEquals('4.25', $this->caregiver->fresh()->admin_rating);
    }

    public function test_correctly_recalculates_average_rating_and_ignores_soft_deleted_ratings()
    {
        $booking2 = Booking::factory()->create([
            'client_id' => $this->client->id,
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
    }
}
