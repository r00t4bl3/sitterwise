<?php

use App\Enums\BookingStatus;
use App\Enums\CaregiverStatus;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin sees dashboard with stats', function () {
    $admin = User::factory()->create(['role' => 'admin']);

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
});

test('caregiver sees dashboard with caregiver data', function () {
    $user = User::factory()->create(['role' => 'caregiver', 'name' => 'Jane Smith']);

    $caregiver = new Caregiver([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'rating' => 4.5,
        'phone' => '+11234567890',
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
        ->where('caregiver.status', CaregiverStatus::Active->label())
        ->has('stats', fn ($page) => $page
            ->where('completed_jobs', 1)
            ->where('total_earned', 100)
            ->where('rating', '4.50')
        )
        ->has('caregiver.nextJob')
        ->has('caregiver.newInvites')
    );
});

test('client sees dashboard with stats and bookings', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $user->id]);

    // Active booking
    Booking::factory()->forClient($client)->create([
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => now()->addDays(1),
        'end_datetime' => now()->addDays(1)->addHours(4),
    ]);

    // Past booking
    Booking::factory()->forClient($client)->create([
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
        ->has('client.nextBooking')
        ->has('client.recentBookings', 1)
    );
});

test('unauthenticated user is redirected to login', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

test('caregiver sees future availabilities', function () {
    $user = User::factory()->create(['role' => 'caregiver', 'name' => 'Jane Smith']);

    $caregiver = new Caregiver([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'phone' => '+11234567890',
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
});

test('admin dashboard loads with bookings that require payment', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Booking::factory()
        ->withBookingGroup(fn ($group) => $group->state(['requires_payment' => true]))
        ->create([
            'payment_status' => 'pending',
            'status' => BookingStatus::Confirmed->value,
            'start_datetime' => now()->addDays(1),
            'end_datetime' => now()->addDays(1)->addHours(4),
        ]);

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->where('stats.troubledMissingPayment', 1)
    );
});
