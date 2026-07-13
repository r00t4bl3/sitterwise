<?php

use App\Models\Booking;
use App\Models\Caregiver;
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
});

/**
 * A booking whose caregiver_id is set but which has no assignment row for that
 * caregiver — the gap the raw-update accept path leaves behind. updateQuietly
 * bypasses the saved-hook so no row is auto-created.
 */
function bookingMissingOwnAssignment(string $status, array $overrides = []): Booking
{
    $caregiver = Caregiver::factory()->create();
    $booking = Booking::factory()->create(array_merge([
        'caregiver_id' => null,
        'status' => $status,
    ], $overrides));
    $booking->updateQuietly(['caregiver_id' => $caregiver->id]);

    return $booking;
}

describe('caregivers:backfill-missing-assignments', function () {
    test('dry-run reports the gap without creating any row', function () {
        $booking = bookingMissingOwnAssignment('confirmed');

        $this->artisan('caregivers:backfill-missing-assignments')
            ->expectsOutputToContain('Missing assignment rows: 1')
            ->expectsOutputToContain('Dry-run mode');

        expect($booking->assignments()->count())->toBe(0);
    });

    test('--apply creates a pending (null) row for an ongoing confirmed booking', function () {
        $booking = bookingMissingOwnAssignment('confirmed');

        $this->artisan('caregivers:backfill-missing-assignments --apply')
            ->expectsOutputToContain('Missing assignment rows: 1')
            ->doesntExpectOutputToContain('Dry-run mode');

        $assignment = $booking->assignments()->where('caregiver_id', $booking->caregiver_id)->first();
        expect($assignment)->not->toBeNull()
            ->and($assignment->resolution)->toBeNull()
            ->and($assignment->assigned_at)->not->toBeNull();
    });

    test('a checked-out (paid) booking is skipped so reliability/milestone metrics stay untouched', function () {
        // Fabricating a 'completed' assignment row here would retroactively
        // change reliability scores and milestone counts, so historical completed
        // work is intentionally left alone.
        $booking = bookingMissingOwnAssignment('paid', ['checkout_at' => now()->subHour()]);

        $this->artisan('caregivers:backfill-missing-assignments --apply')
            ->expectsOutputToContain('No bookings need a backfilled assignment row.');

        expect($booking->assignments()->count())->toBe(0);
    });

    test('a cancelled booking is skipped (no fabricated history)', function () {
        $booking = bookingMissingOwnAssignment('cancelled', ['cancelled_at' => now()]);

        $this->artisan('caregivers:backfill-missing-assignments --apply')
            ->expectsOutputToContain('No bookings need a backfilled assignment row.');

        expect($booking->assignments()->count())->toBe(0);
    });

    test('a booking that already has its own row is left untouched (no duplicate)', function () {
        $caregiver = Caregiver::factory()->create();
        $booking = Booking::factory()->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'confirmed',
        ]);
        // The saved-hook already created the row on assignment.
        expect($booking->assignments()->count())->toBe(1);

        $this->artisan('caregivers:backfill-missing-assignments --apply')
            ->expectsOutputToContain('No bookings need a backfilled assignment row.');

        expect($booking->assignments()->count())->toBe(1);
    });

    test('a prior caregiver reassigned row is preserved and the current caregiver gets a fresh pending row', function () {
        $priorCaregiver = Caregiver::factory()->create();
        $currentCaregiver = Caregiver::factory()->create();
        $booking = Booking::factory()->create([
            'caregiver_id' => null,
            'status' => 'confirmed',
        ]);
        $booking->assignments()->create([
            'caregiver_id' => $priorCaregiver->id,
            'assigned_at' => now()->subDay(),
            'resolution' => 'reassigned',
            'resolution_at' => now()->subDay(),
        ]);
        $booking->updateQuietly(['caregiver_id' => $currentCaregiver->id]);

        $this->artisan('caregivers:backfill-missing-assignments --apply')
            ->expectsOutputToContain('Missing assignment rows: 1');

        expect($booking->assignments()->where('caregiver_id', $priorCaregiver->id)->first()->resolution)
            ->toBe('reassigned')
            ->and($booking->assignments()->where('caregiver_id', $currentCaregiver->id)->first()->resolution)
            ->toBeNull();
    });

    test('it is idempotent — a second run finds nothing', function () {
        bookingMissingOwnAssignment('confirmed');

        $this->artisan('caregivers:backfill-missing-assignments --apply');

        $this->artisan('caregivers:backfill-missing-assignments --apply')
            ->expectsOutputToContain('No bookings need a backfilled assignment row.');
    });
});
