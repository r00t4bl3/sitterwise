<?php

use App\Enums\BookingStatus;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use App\Models\Client;
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
            'phone' => '1234567890',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
        ]);
        $caregiver->save();

        // Completed booking for earnings
        Booking::factory()->create([
            'caregiver_id' => $caregiver->id,
            'status' => BookingStatus::Completed->value,
            'paid_to_caregiver_total' => 100.00,
            'start_datetime' => now()->subDays(2),
            'end_datetime' => now()->subDays(2)->addHours(4),
        ]);

        // Future confirmed booking
        Booking::factory()->create([
            'caregiver_id' => $caregiver->id,
            'status' => BookingStatus::Confirmed->value,
            'start_datetime' => now()->addDays(1),
            'end_datetime' => now()->addDays(1)->addHours(4),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('caregiver.firstName', 'Jane')
            ->where('caregiver.lastName', 'Smith')
            ->where('caregiver.status.name', 'Active')
            ->where('stats.total_earned', 100)
            ->where('stats.completed_jobs', 1)
            ->has('caregiver.nextJob')
            ->has('caregiver.newInvites')
        );
    }

    public function test_client_sees_dashboard_with_stats_and_bookings()
    {
        $user = User::factory()->create(['role' => 'client']);
        $client = Client::factory()->create(['user_id' => $user->id]);

        // Active booking
        Booking::factory()->create([
            'client_id' => $client->id,
            'status' => BookingStatus::Confirmed->value,
            'start_datetime' => now()->addDays(1),
            'end_datetime' => now()->addDays(1)->addHours(4),
        ]);

        // Past booking
        Booking::factory()->create([
            'client_id' => $client->id,
            'status' => BookingStatus::Completed->value,
            'start_datetime' => now()->subDays(1),
            'end_datetime' => now()->subDays(1)->addHours(4),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('user.role', 'client')
            ->where('stats.active_bookings', 1)
            ->where('stats.past_bookings', 1)
            ->has('client.next_booking')
            ->has('client.recent_bookings', 1)
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
            'phone' => '1234567890',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
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
