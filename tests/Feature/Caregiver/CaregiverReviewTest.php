<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Caregiver;
use App\Models\Client;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
});

/**
 * @return array{0: Booking, 1: Caregiver, 2: Client}
 */
function caregiverReviewBooking(string $status = BookingStatus::Completed->value): array
{
    $client = Client::factory()->create();
    $caregiver = Caregiver::factory()->create();

    $booking = Booking::factory()->forClient($client)->create([
        'status' => $status,
        'caregiver_id' => $caregiver->id,
    ]);

    return [$booking, $caregiver, $client];
}

function caregiverRatePayload(array $overrides = []): array
{
    return array_merge([
        'rating' => 5,
        'comment' => 'Lovely family, great kids.',
        'type' => BookingRating::TYPE_CAREGIVER_TO_CLIENT,
    ], $overrides);
}

describe('Caregiver leaving a review of the family', function () {
    test('assigned caregiver can review a completed job', function () {
        [$booking, $caregiver, $client] = caregiverReviewBooking();

        actingAs($caregiver->user)
            ->post(route('jobs.rate', $booking), caregiverRatePayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('booking_ratings', [
            'booking_id' => $booking->id,
            'rater_id' => $caregiver->user->id,
            'ratable_type' => Client::class,
            'ratable_id' => $client->id,
            'rating' => 5,
            'comment' => 'Lovely family, great kids.',
        ]);
    });

    test('re-submitting updates the existing review in place (editable)', function () {
        [$booking, $caregiver, $client] = caregiverReviewBooking();

        actingAs($caregiver->user)
            ->post(route('jobs.rate', $booking), caregiverRatePayload(['rating' => 5]))
            ->assertRedirect();

        actingAs($caregiver->user)
            ->post(route('jobs.rate', $booking), caregiverRatePayload(['rating' => 3, 'comment' => 'Updated.']))
            ->assertRedirect();

        expect(BookingRating::where('booking_id', $booking->id)->where('ratable_type', Client::class)->count())->toBe(1);

        $this->assertDatabaseHas('booking_ratings', [
            'booking_id' => $booking->id,
            'ratable_type' => Client::class,
            'rating' => 3,
            'comment' => 'Updated.',
        ]);
    });

    test('a caregiver cannot review a job they are not assigned to', function () {
        [$booking] = caregiverReviewBooking();
        $otherCaregiver = Caregiver::factory()->create();

        actingAs($otherCaregiver->user)
            ->post(route('jobs.rate', $booking), caregiverRatePayload())
            ->assertStatus(403);

        expect(BookingRating::count())->toBe(0);
    });

    test('a caregiver cannot review a job that is not completed', function () {
        [$booking, $caregiver] = caregiverReviewBooking(BookingStatus::Confirmed->value);

        actingAs($caregiver->user)
            ->post(route('jobs.rate', $booking), caregiverRatePayload())
            ->assertStatus(400);

        expect(BookingRating::count())->toBe(0);
    });

    test('rating must be between 1 and 5', function () {
        [$booking, $caregiver] = caregiverReviewBooking();

        actingAs($caregiver->user)
            ->post(route('jobs.rate', $booking), caregiverRatePayload(['rating' => 6]))
            ->assertSessionHasErrors('rating');

        actingAs($caregiver->user)
            ->post(route('jobs.rate', $booking), caregiverRatePayload(['rating' => 0]))
            ->assertSessionHasErrors('rating');

        expect(BookingRating::count())->toBe(0);
    });

    test('rating is required', function () {
        [$booking, $caregiver] = caregiverReviewBooking();

        actingAs($caregiver->user)
            ->post(route('jobs.rate', $booking), caregiverRatePayload(['rating' => null]))
            ->assertSessionHasErrors('rating');

        expect(BookingRating::count())->toBe(0);
    });
});
