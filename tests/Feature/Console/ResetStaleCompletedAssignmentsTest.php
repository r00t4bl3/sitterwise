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
 * A confirmed, never-checked-out booking whose current caregiver's assignment
 * carries the stale 'completed' resolution — exactly the state the Bubble import
 * left behind, and the one this command targets.
 */
function stuckConfirmedBooking(): Booking
{
    $caregiver = Caregiver::factory()->create();

    $booking = Booking::factory()->create([
        'caregiver_id' => $caregiver->id,
        'status' => 'confirmed',
        'checkout_at' => null,
    ]);

    // The booking's saved hook auto-creates the assignment (resolution null);
    // the import stamped it 'completed' with resolution_at = end_datetime.
    $booking->assignments()->firstOrFail()->update([
        'resolution' => 'completed',
        'resolution_at' => $booking->end_datetime,
    ]);

    return $booking;
}

describe('bookings:reset-stale-completed-assignments', function () {
    test('dry-run reports the stuck booking without changing it', function () {
        $booking = stuckConfirmedBooking();

        $this->artisan('bookings:reset-stale-completed-assignments')
            ->expectsOutputToContain('Stale completed assignments: 1')
            ->expectsOutputToContain('Dry-run mode');

        $assignment = $booking->assignments()->firstOrFail();
        expect($assignment->resolution)->toBe('completed')
            ->and($assignment->resolution_at)->not->toBeNull();
    });

    test('--apply clears the stale resolution so the job is checkoutable again', function () {
        $booking = stuckConfirmedBooking();

        $this->artisan('bookings:reset-stale-completed-assignments --apply')
            ->expectsOutputToContain('Stale completed assignments: 1')
            ->doesntExpectOutputToContain('Dry-run mode');

        $assignment = $booking->assignments()->firstOrFail();
        expect($assignment->resolution)->toBeNull()
            ->and($assignment->resolution_at)->toBeNull();
    });

    test('it is idempotent — a second run finds nothing', function () {
        stuckConfirmedBooking();

        $this->artisan('bookings:reset-stale-completed-assignments --apply');

        $this->artisan('bookings:reset-stale-completed-assignments --apply')
            ->expectsOutputToContain('No stale completed assignments found.');
    });

    test('a genuinely completed & paid booking is left untouched', function () {
        $caregiver = Caregiver::factory()->create();
        $booking = Booking::factory()->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'paid',
            'checkout_at' => now(),
        ]);
        $booking->assignments()->firstOrFail()->update(['resolution' => 'completed']);

        $this->artisan('bookings:reset-stale-completed-assignments --apply')
            ->expectsOutputToContain('No stale completed assignments found.');

        expect($booking->assignments()->firstOrFail()->resolution)->toBe('completed');
    });

    test('a cancelled booking is left untouched', function () {
        $caregiver = Caregiver::factory()->create();
        $booking = Booking::factory()->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'confirmed',
            'cancelled_at' => now(),
        ]);
        $booking->assignments()->firstOrFail()->update(['resolution' => 'completed']);

        $this->artisan('bookings:reset-stale-completed-assignments --apply')
            ->expectsOutputToContain('No stale completed assignments found.');

        expect($booking->assignments()->firstOrFail()->resolution)->toBe('completed');
    });

    test('a confirmed booking already checked out is left untouched', function () {
        $caregiver = Caregiver::factory()->create();
        $booking = Booking::factory()->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'confirmed',
            'checkout_at' => now(),
        ]);
        $booking->assignments()->firstOrFail()->update(['resolution' => 'completed']);

        $this->artisan('bookings:reset-stale-completed-assignments --apply')
            ->expectsOutputToContain('No stale completed assignments found.');

        expect($booking->assignments()->firstOrFail()->resolution)->toBe('completed');
    });

    test('a former caregiver\'s backed-out assignment on the same booking is preserved', function () {
        $booking = stuckConfirmedBooking();

        // A different, former caregiver who backed out earlier — must not be reset.
        $formerCaregiver = Caregiver::factory()->create();
        $booking->assignments()->create([
            'caregiver_id' => $formerCaregiver->id,
            'assigned_at' => now()->subDay(),
            'resolution' => 'backed_out',
            'resolution_at' => now()->subDay(),
        ]);

        $this->artisan('bookings:reset-stale-completed-assignments --apply')
            ->expectsOutputToContain('Stale completed assignments: 1');

        expect($booking->assignments()->where('caregiver_id', $booking->caregiver_id)->firstOrFail()->resolution)
            ->toBeNull()
            ->and($booking->assignments()->where('caregiver_id', $formerCaregiver->id)->firstOrFail()->resolution)
            ->toBe('backed_out');
    });
});
