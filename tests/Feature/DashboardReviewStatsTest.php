<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create();
    $this->caregiver = Caregiver::factory()->create();
});

function completedUnrated(Client $client, array $overrides = []): Booking
{
    return Booking::factory()->forClient($client)->create(array_merge([
        'status' => BookingStatus::Completed->value,
    ], $overrides));
}

function caregiverRating(Booking $booking, User $rater, Caregiver $caregiver, float $rating): void
{
    BookingRating::create([
        'booking_id' => $booking->id,
        'rater_id' => $rater->id,
        'ratable_type' => Caregiver::class,
        'ratable_id' => $caregiver->id,
        'rating' => $rating,
    ]);
}

describe('dashboard review stats (#151)', function () {
    test('pending reviews excludes migrated bubble bookings', function () {
        completedUnrated($this->client, ['bubble_id' => null]);           // counts
        completedUnrated($this->client, ['bubble_id' => 'bubble_123']);   // excluded

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('admin.reviewAnalytics.pendingReviewsCount', 1)
            );
    });

    test('rating distribution buckets decimals and sums to total', function () {
        caregiverRating(completedUnrated($this->client), $this->client->user, $this->caregiver, 5.0);
        caregiverRating(completedUnrated($this->client), $this->client->user, $this->caregiver, 4.5); // → 5★
        caregiverRating(completedUnrated($this->client), $this->client->user, $this->caregiver, 4.0);
        caregiverRating(completedUnrated($this->client), $this->client->user, $this->caregiver, 3.5); // → 4★

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // [5★, 4★, 3★, 2★, 1★] → 5.0 & 4.5 = two 5★, 4.0 & 3.5 = two 4★
                ->where('admin.reviewAnalytics.ratingDistribution', [2, 2, 0, 0, 0])
                ->where('admin.reviewAnalytics.totalReviews', 4)
            );
    });
});
