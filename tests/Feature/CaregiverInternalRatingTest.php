<?php

use App\Console\Commands\RecalculateReliability;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\CaregiverAssignment;
use App\Models\CaregiverInterview;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);

    $caregiverUser = User::factory()->create(['role' => 'caregiver', 'email' => 'c@example.com']);
    $this->caregiver = Caregiver::create([
        'user_id' => $caregiverUser->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '555-0100',
        'date_of_birth' => '1990-01-01',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
    ]);

    $this->booking = Booking::factory()->forClient($client)->create([
        'caregiver_id' => null,
        'status' => 'completed',
    ]);

});

function makeAssignment(Caregiver $caregiver, Booking $booking, ?string $resolution): CaregiverAssignment
{
    return CaregiverAssignment::updateOrCreate(
        [
            'caregiver_id' => $caregiver->id,
            'booking_id' => $booking->id,
        ],
        [
            'resolution' => $resolution,
            'resolution_at' => $resolution ? now() : null,
            'assigned_at' => now(),
        ],
    );
}

it('updates communication score on both legacy and new tables', function () {
    $this->actingAs($this->admin)
        ->put(route('caregivers.updateAdminRating', $this->caregiver), [
            'admin_rating' => 4.25,
        ]);

    expect((float) $this->caregiver->fresh()->admin_rating)->toBe(4.25);

    $rating = $this->caregiver->internalRating()->first();
    expect((float) $rating->communication_score)->toBe(4.25);
});

it('saves communication notes', function () {
    $this->actingAs($this->admin)
        ->put(route('caregivers.updateAdminRating', $this->caregiver), [
            'admin_rating' => 4.0,
            'communication_notes' => 'Great communicator',
        ]);

    $rating = $this->caregiver->internalRating()->first();
    expect($rating->communication_notes)->toBe('Great communicator');
});

it('calculates reliability from completed assignments', function () {
    makeAssignment($this->caregiver, $this->booking, 'completed');

    Artisan::call('app:recalculate-reliability', ['--caregiver' => $this->caregiver->id]);

    $rating = $this->caregiver->internalRating()->first();
    expect((float) $rating->reliability_score)->toBe(5.0);
});

it('applies reliability formula correctly', function () {
    $client = $this->booking->client;
    for ($i = 0; $i < 5; $i++) {
        $b = Booking::factory()->forClient($client)->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => 'completed',
        ]);
        makeAssignment($this->caregiver, $b, 'completed');
    }
    for ($i = 0; $i < 3; $i++) {
        $b = Booking::factory()->forClient($client)->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => 'completed',
        ]);
        makeAssignment($this->caregiver, $b, 'backed_out');
    }

    Artisan::call('app:recalculate-reliability', ['--caregiver' => $this->caregiver->id]);

    $rating = $this->caregiver->internalRating()->first();
    expect((float) $rating->reliability_score)->toBe(3.5);
});

it('excludes cancelled_by_sitterwise from reliability', function () {
    makeAssignment($this->caregiver, $this->booking, 'cancelled_by_sitterwise');

    Artisan::call('app:recalculate-reliability', ['--caregiver' => $this->caregiver->id]);

    $rating = $this->caregiver->internalRating()->first();
    expect($rating->reliability_score)->toBeNull();
});

it('leaves reliability null when no resolved assignments exist', function () {
    makeAssignment($this->caregiver, $this->booking, null);

    Artisan::call('app:recalculate-reliability', ['--caregiver' => $this->caregiver->id]);

    $rating = $this->caregiver->internalRating()->first();
    expect($rating->reliability_score)->toBeNull();
});

it('uses reliability override instead of auto score', function () {
    makeAssignment($this->caregiver, $this->booking, 'completed');

    $this->caregiver->internalRating()->create([
        'reliability_score' => 2.5,
        'reliability_override' => 4.0,
        'reliability_cached_at' => now(),
    ]);

    $rating = $this->caregiver->internalRating()->first();
    expect($rating->effectiveReliability())->toBe(4.0);
});

it('clearing reliability override reverts to auto score', function () {
    makeAssignment($this->caregiver, $this->booking, 'completed');

    $this->actingAs($this->admin)
        ->put(route('caregivers.updateReliabilityOverride', $this->caregiver), [
            'reliability_override' => 3.0,
        ]);

    $this->actingAs($this->admin)
        ->put(route('caregivers.updateReliabilityOverride', $this->caregiver), [
            'reliability_override' => null,
        ]);

    $rating = $this->caregiver->internalRating()->first();
    expect($rating->reliability_override)->toBeNull();
    expect((float) $rating->reliability_score)->toBe(5.0);
});

it('calculates composite with all three components', function () {
    CaregiverInterview::create([
        'caregiver_id' => $this->caregiver->id,
        'evaluator_id' => $this->admin->id,
        'scores' => ['soft_skills' => [], 'professionalism' => []],
        'composite' => 30,
        'status' => 'completed',
    ]);

    $this->caregiver->internalRating()->create([
        'communication_score' => 4.0,
        'reliability_score' => 5.0,
        'reliability_cached_at' => now(),
    ]);

    $command = new RecalculateReliability;
    $rating = $this->caregiver->internalRating()->first();
    $composite = $command->calculateComposite($this->caregiver, $rating);

    expect($composite)->toBe(90.67);
});

it('returns null composite when no components exist', function () {
    $command = new RecalculateReliability;
    $rating = $this->caregiver->internalRating()->firstOrNew([]);
    $composite = $command->calculateComposite($this->caregiver, $rating);

    expect($composite)->toBeNull();
});

it('recalculates reliability after backOut', function () {
    $assignment = makeAssignment($this->caregiver, $this->booking, null);

    $this->actingAs($this->caregiver->user)
        ->post(route('assignments.back-out', $assignment), [
            'reason' => 'Scheduling conflict',
        ]);

    Artisan::call('app:recalculate-reliability', ['--caregiver' => $this->caregiver->id]);

    $rating = $this->caregiver->internalRating()->first();
    expect((float) $rating->reliability_score)->toBe(4.5);
});

it('does not recalculate for caregivers without resolved assignments', function () {
    makeAssignment($this->caregiver, $this->booking, null);

    Artisan::call('app:recalculate-reliability');

    $rating = $this->caregiver->internalRating()->first();
    expect($rating)->toBeNull();
});
