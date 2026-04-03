<?php

use App\Models\Availability;
use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_dashboard_with_stats()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        CaregiverStatus::factory()->create(['name' => 'Active']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('user', fn ($page) => $page
                ->where('name', $admin->name)
                ->where('role', 'admin')
            )
            ->has('stats')
        );
    }

    public function test_caregiver_sees_dashboard_with_caregiver_data()
    {
        $status = CaregiverStatus::factory()->create(['name' => 'Active']);
        $user = User::factory()->create(['role' => 'caregiver', 'name' => 'Jane Smith']);
        $caregiver = new Caregiver([
            'user_id' => $user->id,
            'status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'rating' => 4.5,
        ]);
        $caregiver->save();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('caregiver.first_name', 'Jane')
            ->where('caregiver.last_name', 'Smith')
            ->where('caregiver.status.name', 'Active')
        );
    }

    public function test_client_sees_dashboard_without_stats()
    {
        $client = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($client)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('user.role', 'client')
        );
    }

    public function test_unauthenticated_user_is_redirected_to_login()
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_caregiver_sees_future_availabilities()
    {
        $status = CaregiverStatus::factory()->create(['name' => 'Active']);
        $user = User::factory()->create(['role' => 'caregiver', 'name' => 'Jane Smith']);
        $caregiver = new Caregiver([
            'user_id' => $user->id,
            'status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);
        $caregiver->save();
        Availability::factory()->create([
            'caregiver_id' => $caregiver->id,
            'date' => now()->addDays(5)->toDateString(),
            'time_slots' => ['morning'],
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->count('caregiver.availabilities', 1)
        );
    }
}
