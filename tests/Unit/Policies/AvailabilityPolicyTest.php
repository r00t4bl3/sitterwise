<?php

use App\Models\Availability;
use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use App\Models\User;
use App\Policies\AvailabilityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_any_allows_admin()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $policy = new AvailabilityPolicy;

        $this->assertTrue($policy->viewAny($admin));
    }

    public function test_view_any_allows_caregiver()
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $policy = new AvailabilityPolicy;

        $this->assertTrue($policy->viewAny($caregiver));
    }

    public function test_view_any_allows_client()
    {
        $client = User::factory()->create(['role' => 'client']);
        $policy = new AvailabilityPolicy;

        $this->assertTrue($policy->viewAny($client));
    }

    public function test_view_any_denies_other_roles()
    {
        $user = User::factory()->make(['role' => 'other']);
        $policy = new AvailabilityPolicy;

        $this->assertFalse($policy->viewAny($user));
    }

    public function test_view_allows_admin()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $availability = new Availability(['caregiver_id' => 1, 'date' => '2026-01-01', 'time_slots' => ['morning']]);
        $policy = new AvailabilityPolicy;

        $this->assertTrue($policy->view($admin, $availability));
    }

    public function test_view_allows_caregiver_who_owns_availability()
    {
        $status = CaregiverStatus::factory()->create();
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::factory()->make([
            'user_id' => $user->id,
            'status_id' => $status->id,
        ]);
        $availability = Availability::factory()->make(['caregiver_id' => $caregiver->id]);
        $policy = new AvailabilityPolicy;

        $this->assertTrue($policy->view($user, $availability));
    }

    public function test_view_denies_caregiver_who_does_not_own_availability()
    {
        $status = CaregiverStatus::factory()->create();
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::factory()->make([
            'user_id' => $user->id,
            'status_id' => $status->id,
        ]);
        $otherAvailability = Availability::factory()->make(['caregiver_id' => 999]);
        $policy = new AvailabilityPolicy;

        $this->assertFalse($policy->view($user, $otherAvailability));
    }

    public function test_view_denies_client()
    {
        $client = User::factory()->create(['role' => 'client']);
        $availability = new Availability(['caregiver_id' => 1, 'date' => '2026-01-01', 'time_slots' => ['morning']]);
        $policy = new AvailabilityPolicy;

        $this->assertFalse($policy->view($client, $availability));
    }

    public function test_create_allows_admin()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $policy = new AvailabilityPolicy;

        $this->assertTrue($policy->create($admin));
    }

    public function test_create_allows_caregiver()
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $policy = new AvailabilityPolicy;

        $this->assertTrue($policy->create($caregiver));
    }

    public function test_create_denies_client()
    {
        $client = User::factory()->create(['role' => 'client']);
        $policy = new AvailabilityPolicy;

        $this->assertFalse($policy->create($client));
    }

    public function test_update_allows_admin()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $availability = new Availability(['caregiver_id' => 1, 'date' => '2026-01-01', 'time_slots' => ['morning']]);
        $policy = new AvailabilityPolicy;

        $this->assertTrue($policy->update($admin, $availability));
    }

    public function test_update_allows_caregiver_who_owns_availability()
    {
        $status = CaregiverStatus::factory()->create();
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::factory()->make([
            'user_id' => $user->id,
            'status_id' => $status->id,
        ]);
        $availability = Availability::factory()->make(['caregiver_id' => $caregiver->id]);
        $policy = new AvailabilityPolicy;

        $this->assertTrue($policy->update($user, $availability));
    }

    public function test_update_denies_caregiver_who_does_not_own_availability()
    {
        $status = CaregiverStatus::factory()->create();
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::factory()->make([
            'user_id' => $user->id,
            'status_id' => $status->id,
        ]);
        $otherAvailability = Availability::factory()->make(['caregiver_id' => 999]);
        $policy = new AvailabilityPolicy;

        $this->assertFalse($policy->update($user, $otherAvailability));
    }

    public function test_delete_allows_admin()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $availability = new Availability(['caregiver_id' => 1, 'date' => '2026-01-01', 'time_slots' => ['morning']]);
        $policy = new AvailabilityPolicy;

        $this->assertTrue($policy->delete($admin, $availability));
    }

    public function test_delete_allows_caregiver_who_owns_availability()
    {
        $status = CaregiverStatus::factory()->create();
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::factory()->make([
            'user_id' => $user->id,
            'status_id' => $status->id,
        ]);
        $availability = Availability::factory()->make(['caregiver_id' => $caregiver->id]);
        $policy = new AvailabilityPolicy;

        $this->assertTrue($policy->delete($user, $availability));
    }

    public function test_delete_denies_caregiver_who_does_not_own_availability()
    {
        $status = CaregiverStatus::factory()->create();
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::factory()->make([
            'user_id' => $user->id,
            'status_id' => $status->id,
        ]);
        $otherAvailability = Availability::factory()->make(['caregiver_id' => 999]);
        $policy = new AvailabilityPolicy;

        $this->assertFalse($policy->delete($user, $otherAvailability));
    }
}
