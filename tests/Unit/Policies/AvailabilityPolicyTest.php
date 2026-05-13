<?php

use App\Models\Availability;
use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use App\Models\User;
use App\Policies\AvailabilityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('view any allows admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $policy = new AvailabilityPolicy;

    $this->assertTrue($policy->viewAny($admin));
});

test('view any allows caregiver', function () {
    $caregiver = User::factory()->create(['role' => 'caregiver']);
    $policy = new AvailabilityPolicy;

    $this->assertTrue($policy->viewAny($caregiver));
});

test('view any allows client', function () {
    $client = User::factory()->create(['role' => 'client']);
    $policy = new AvailabilityPolicy;

    $this->assertTrue($policy->viewAny($client));
});

test('view any denies other roles', function () {
    $user = User::factory()->make(['role' => 'other']);
    $policy = new AvailabilityPolicy;

    $this->assertFalse($policy->viewAny($user));
});

test('view allows admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $availability = new Availability(['caregiver_id' => 1, 'date' => '2026-01-01', 'time_slots' => ['morning']]);
    $policy = new AvailabilityPolicy;

    $this->assertTrue($policy->view($admin, $availability));
});

test('view allows caregiver who owns availability', function () {
    $status = CaregiverStatus::factory()->create();
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::factory()->make([
        'user_id' => $user->id,
        'status_id' => $status->id,
    ]);
    $availability = Availability::factory()->make(['caregiver_id' => $caregiver->id]);
    $policy = new AvailabilityPolicy;

    $this->assertTrue($policy->view($user, $availability));
});

test('view denies caregiver who does not own availability', function () {
    $status = CaregiverStatus::factory()->create();
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::factory()->make([
        'user_id' => $user->id,
        'status_id' => $status->id,
    ]);
    $otherAvailability = Availability::factory()->make(['caregiver_id' => 999]);
    $policy = new AvailabilityPolicy;

    $this->assertFalse($policy->view($user, $otherAvailability));
});

test('view denies client', function () {
    $client = User::factory()->create(['role' => 'client']);
    $availability = new Availability(['caregiver_id' => 1, 'date' => '2026-01-01', 'time_slots' => ['morning']]);
    $policy = new AvailabilityPolicy;

    $this->assertFalse($policy->view($client, $availability));
});

test('create allows admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $policy = new AvailabilityPolicy;

    $this->assertTrue($policy->create($admin));
});

test('create allows caregiver', function () {
    $caregiver = User::factory()->create(['role' => 'caregiver']);
    $policy = new AvailabilityPolicy;

    $this->assertTrue($policy->create($caregiver));
});

test('create denies client', function () {
    $client = User::factory()->create(['role' => 'client']);
    $policy = new AvailabilityPolicy;

    $this->assertFalse($policy->create($client));
});

test('update allows admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $availability = new Availability(['caregiver_id' => 1, 'date' => '2026-01-01', 'time_slots' => ['morning']]);
    $policy = new AvailabilityPolicy;

    $this->assertTrue($policy->update($admin, $availability));
});

test('update allows caregiver who owns availability', function () {
    $status = CaregiverStatus::factory()->create();
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::factory()->make([
        'user_id' => $user->id,
        'status_id' => $status->id,
    ]);
    $availability = Availability::factory()->make(['caregiver_id' => $caregiver->id]);
    $policy = new AvailabilityPolicy;

    $this->assertTrue($policy->update($user, $availability));
});

test('update denies caregiver who does not own availability', function () {
    $status = CaregiverStatus::factory()->create();
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::factory()->make([
        'user_id' => $user->id,
        'status_id' => $status->id,
    ]);
    $otherAvailability = Availability::factory()->make(['caregiver_id' => 999]);
    $policy = new AvailabilityPolicy;

    $this->assertFalse($policy->update($user, $otherAvailability));
});

test('delete allows admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $availability = new Availability(['caregiver_id' => 1, 'date' => '2026-01-01', 'time_slots' => ['morning']]);
    $policy = new AvailabilityPolicy;

    $this->assertTrue($policy->delete($admin, $availability));
});

test('delete allows caregiver who owns availability', function () {
    $status = CaregiverStatus::factory()->create();
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::factory()->make([
        'user_id' => $user->id,
        'status_id' => $status->id,
    ]);
    $availability = Availability::factory()->make(['caregiver_id' => $caregiver->id]);
    $policy = new AvailabilityPolicy;

    $this->assertTrue($policy->delete($user, $availability));
});

test('delete denies caregiver who does not own availability', function () {
    $status = CaregiverStatus::factory()->create();
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::factory()->make([
        'user_id' => $user->id,
        'status_id' => $status->id,
    ]);
    $otherAvailability = Availability::factory()->make(['caregiver_id' => 999]);
    $policy = new AvailabilityPolicy;

    $this->assertFalse($policy->delete($user, $otherAvailability));
});
