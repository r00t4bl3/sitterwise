<?php

use App\Enums\BookingStatus;
use App\Enums\CaregiverStatus;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\PricingRule;
use App\Models\User;
use App\Services\CaregiverBadgeService;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

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

test('unassigned count is scoped to the current month and excludes cancelled', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00', 'America/Los_Angeles'));
    $admin = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create();

    // Genuine upcoming unassigned booking this month → counts.
    Booking::factory()->forClient($client)->create([
        'caregiver_id' => null,
        'status' => BookingStatus::Received->value,
        'cancelled_at' => null,
        'start_datetime' => '2026-07-20T18:00:00Z',
        'end_datetime' => '2026-07-20T22:00:00Z',
    ]);

    // Cancelled booking this month with a stale received status → excluded.
    Booking::factory()->forClient($client)->create([
        'caregiver_id' => null,
        'status' => BookingStatus::Received->value,
        'cancelled_at' => now(),
        'start_datetime' => '2026-07-22T18:00:00Z',
        'end_datetime' => '2026-07-22T22:00:00Z',
    ]);

    // Unassigned booking next month → excluded (out of the current-month window).
    Booking::factory()->forClient($client)->create([
        'caregiver_id' => null,
        'status' => BookingStatus::Received->value,
        'cancelled_at' => null,
        'start_datetime' => '2026-08-20T18:00:00Z',
        'end_datetime' => '2026-08-20T22:00:00Z',
    ]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.troubledUnassigned', 1)
        );

    Carbon::setTestNow();
});

test('unassigned count excludes past-dated bookings that can no longer be assigned (#307)', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00', 'America/Los_Angeles'));
    $admin = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create();

    // Upcoming unassigned → counts.
    Booking::factory()->forClient($client)->create([
        'caregiver_id' => null,
        'status' => BookingStatus::Received->value,
        'start_datetime' => '2026-07-20T18:00:00Z',
        'end_datetime' => '2026-07-20T22:00:00Z',
    ]);

    // Past-dated unassigned this month → excluded.
    Booking::factory()->forClient($client)->create([
        'caregiver_id' => null,
        'status' => BookingStatus::Received->value,
        'start_datetime' => '2026-07-10T18:00:00Z',
        'end_datetime' => '2026-07-10T22:00:00Z',
    ]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.troubledUnassigned', 1)
        );

    Carbon::setTestNow();
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
            ->where('completedJobs', 1)
            ->where('totalEarned', 0)
            ->where('rating', '4.50')
        )
        ->has('caregiver.nextJob')
        ->has('caregiver.newInvites')
    );
});

test('the first caregiver dashboard load records a badge baseline without celebrating pre-existing badges', function () {
    $user = User::factory()->create(['role' => 'caregiver']);
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

    expect($caregiver->seen_badge_slugs)->toBeNull();

    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    expect($props['caregiver']['newlyEarnedBadges'])->toBeEmpty();
    // Baseline recorded, so already-held badges are never celebrated retroactively.
    expect($caregiver->fresh()->seen_badge_slugs)->not->toBeNull();
});

test('a caregiver dashboard load surfaces newly-earned badges and records them as seen', function () {
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = new Caregiver([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'phone' => '+11234567890',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
    ]);
    // Seen nothing yet, but an active caregiver already qualifies for a badge.
    $caregiver->seen_badge_slugs = [];
    $caregiver->save();

    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertOk();

    expect($response->viewData('page')['props']['caregiver']['newlyEarnedBadges'])->not->toBeEmpty();
    // The snapshot is now recorded, so the same badges won't be celebrated again.
    expect($caregiver->fresh()->seen_badge_slugs)->not->toBeEmpty();
});

test('already-seen badges are not celebrated again on a caregiver dashboard load', function () {
    $user = User::factory()->create(['role' => 'caregiver']);
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

    // Pre-seed the snapshot with every badge the caregiver currently qualifies for.
    $caregiver->update([
        'seen_badge_slugs' => collect(app(CaregiverBadgeService::class)->badgesFor($caregiver))
            ->where('earned', true)
            ->pluck('slug')
            ->all(),
    ]);

    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertOk();

    expect($response->viewData('page')['props']['caregiver']['newlyEarnedBadges'])->toBeEmpty();
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
        ->where('stats.totalBookings', 2)
        ->where('stats.completedBookings', 1)
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

    // A Stripe (card-charged) pricing rule so the group resolves payment_form
    // to Stripe — the missing-payment card only counts card-charged jobs.
    PricingRule::create([
        'service_type' => 'babysitter',
        'number_of_children' => 1,
        'is_for_pets' => false,
        'charge_to_client' => 35.00,
        'paid_to_caregiver' => 23.00,
        'payment_form' => 'Stripe',
        'sitterwise_cut' => 12.00,
    ]);

    Booking::factory()
        ->withBookingGroup(fn ($group) => $group->state([
            'requires_payment' => true,
            'service_type' => 'babysitter',
        ]))
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

test('this-month stats use the business timezone, not UTC (regression for #277)', function () {
    $this->seed([
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        CertificationTypeSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    // 2026-06-30 23:30 Pacific = 2026-07-01 06:30 UTC. A naive-UTC dashboard
    // would treat "now" as July; the business-timezone dashboard knows it's June.
    Carbon::setTestNow(Carbon::parse('2026-06-30 23:30:00', 'America/Los_Angeles'));

    // Two completed bookings in June, one in July (stored UTC).
    Booking::factory()->completed()->create(['start_datetime' => '2026-06-15T18:00:00Z']);
    Booking::factory()->completed()->create(['start_datetime' => '2026-06-20T18:00:00Z']);
    Booking::factory()->completed()->create(['start_datetime' => '2026-07-02T18:00:00Z']);

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    // Correct (June) = 2. Under the old UTC bug this would be 1 (the July row).
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->where('stats.thisMonthCompleted', 2)
    );

    Carbon::setTestNow();
});
