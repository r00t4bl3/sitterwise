<?php

use App\Enums\AssignmentResolution;
use App\Enums\BookingStatus;
use App\Enums\CaregiverStatus;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\CaregiverAssignment;
use App\Models\User;
use App\Notifications\AdminCaregiverBackedOutNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

function buildCaregiver(array $overrides = []): Caregiver
{
    $user = User::factory()->create(['role' => 'caregiver']);

    $data = array_merge([
        'user_id' => $user->id,
        'first_name' => 'Test',
        'last_name' => 'Caregiver',
        'slug' => 'test-caregiver-'.uniqid(),
        'phone' => '619-555-0100',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'date_of_birth' => '2000-01-01',
    ], $overrides);

    return Caregiver::create($data);
}

function createAssignment(Caregiver $caregiver): CaregiverAssignment
{
    $booking = Booking::factory()->create([
        'caregiver_id' => $caregiver->id,
        'status' => BookingStatus::Confirmed,
    ]);

    return $booking->assignments()->firstOrCreate([
        'caregiver_id' => $caregiver->id,
    ]);
}

describe('caregiver back-out', function () {
    it('allows caregiver to back out of an unresolved assignment', function () {
        Mail::fake();

        $caregiver = buildCaregiver(['status' => CaregiverStatus::Active]);
        $assignment = createAssignment($caregiver);

        actingAs($caregiver->user)
            ->post(route('assignments.back-out', $assignment), [
                'reason' => 'Feeling unwell',
            ])
            ->assertSessionHas('success');

        $assignment->refresh();
        expect($assignment->resolution)->toBe(AssignmentResolution::BackedOut->value);
        expect($assignment->resolution_note)->toBe('Feeling unwell');
        expect($assignment->resolution_at)->not->toBeNull();
    });

    it('sends notification to admins on back-out', function () {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = buildCaregiver(['status' => CaregiverStatus::Active]);
        $assignment = createAssignment($caregiver);

        actingAs($caregiver->user)
            ->post(route('assignments.back-out', $assignment), [
                'reason' => 'Family emergency',
            ]);

        Notification::assertSentTo(
            $admin,
            AdminCaregiverBackedOutNotification::class,
            function ($notification) use ($caregiver) {
                return $notification->caregiverName === $caregiver->first_name.' '.$caregiver->last_name
                    && $notification->reason === 'Family emergency';
            }
        );
    });

    it('requires a reason for back-out', function () {
        $caregiver = buildCaregiver(['status' => CaregiverStatus::Active]);
        $assignment = createAssignment($caregiver);

        actingAs($caregiver->user)
            ->post(route('assignments.back-out', $assignment), [])
            ->assertSessionHasErrors('reason');
    });

    it('does not allow back-out on already resolved assignment', function () {
        $caregiver = buildCaregiver(['status' => CaregiverStatus::Active]);
        $assignment = createAssignment($caregiver);
        $assignment->resolve(AssignmentResolution::Completed);

        actingAs($caregiver->user)
            ->post(route('assignments.back-out', $assignment), [
                'reason' => 'Too late',
            ])
            ->assertSessionHas('error');
    });

    it('does not allow another caregiver to back out someone else assignment', function () {
        $caregiver = buildCaregiver(['status' => CaregiverStatus::Active]);
        $other = buildCaregiver(['status' => CaregiverStatus::Active]);
        $assignment = createAssignment($caregiver);

        actingAs($other->user)
            ->post(route('assignments.back-out', $assignment), [
                'reason' => 'Not mine',
            ])
            ->assertStatus(403);
    });
});

describe('admin assignment actions', function () {
    it('admin can excuse a back-out', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = buildCaregiver();
        $assignment = createAssignment($caregiver);

        actingAs($admin)
            ->post(route('assignments.excuse', $assignment), [
                'note' => 'Genuine family emergency',
            ])
            ->assertSessionHas('success');

        $assignment->refresh();
        expect($assignment->resolution)->toBe(AssignmentResolution::BackedOutExcused->value);
        expect($assignment->excused_by)->toBe($admin->id);
    });

    it('requires a note for excuse', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = buildCaregiver();
        $assignment = createAssignment($caregiver);

        actingAs($admin)
            ->post(route('assignments.excuse', $assignment), [])
            ->assertSessionHasErrors('note');
    });

    it('admin can log a no-show', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = buildCaregiver();
        $assignment = createAssignment($caregiver);

        actingAs($admin)
            ->post(route('assignments.no-show', $assignment), [
                'note' => 'Never showed up',
            ])
            ->assertSessionHas('success');

        $assignment->refresh();
        expect($assignment->resolution)->toBe(AssignmentResolution::NoShow->value);
    });

    it('admin can log a late arrival', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = buildCaregiver();
        $assignment = createAssignment($caregiver);

        actingAs($admin)
            ->post(route('assignments.late-arrival', $assignment), [
                'note' => 'Arrived 30 min late',
            ])
            ->assertSessionHas('success');

        $assignment->refresh();
        expect($assignment->late_arrival_flag)->toBeTrue();
        expect($assignment->late_arrival_note)->toBe('Arrived 30 min late');
    });
});

describe('late arrival command', function () {
    it('flags caregivers with 3+ late arrivals in 60 days', function () {
        $caregiver = buildCaregiver();
        $assignment = createAssignment($caregiver);
        $assignment->update(['late_arrival_flag' => true]);

        $assignment2 = createAssignment($caregiver);
        $assignment2->update(['late_arrival_flag' => true]);

        $assignment3 = createAssignment($caregiver);
        $assignment3->update(['late_arrival_flag' => true]);

        artisan('app:check-late-arrivals')->assertSuccessful();
    });

    it('does not flag caregivers with fewer than 3 late arrivals', function () {
        $caregiver = buildCaregiver();
        $assignment = createAssignment($caregiver);
        $assignment->update(['late_arrival_flag' => true]);

        artisan('app:check-late-arrivals')->assertSuccessful();
    });
});
