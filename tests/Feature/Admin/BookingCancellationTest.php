<?php

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->seed(AttributeDefinitionSeeder::class);
    $this->seed(CertificationTypeSeeder::class);
    $this->seed(LocationSeeder::class);
    $this->seed(SpecialtyTypeSeeder::class);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create();
    $this->clientUser = $this->client->user;
});

test('admin can cancel a booking via cancel endpoint', function () {
    $booking = Booking::factory()
        ->forClient($this->client)
        ->withBookingGroup(fn ($group) => $group->state(['service_type' => ServiceType::GroupChildcareInvoiced->value]))
        ->create([
            'status' => BookingStatus::Confirmed->value,
            'start_datetime' => now()->addDays(10),
            'end_datetime' => now()->addDays(10)->addHours(4),
        ]);

    $this->actingAs($this->admin)
        ->post(route('bookings.cancel', $booking), [
            'reason' => 'Client requested cancellation',
        ])
        ->assertRedirect();

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Cancelled->value);
});

test('update endpoint rejects cancelled status', function () {
    $booking = Booking::factory()
        ->forClient($this->client)
        ->withBookingGroup(fn ($group) => $group->state(['service_type' => ServiceType::GroupChildcareInvoiced->value]))
        ->create([
            'status' => BookingStatus::Confirmed->value,
            'start_datetime' => now()->addDays(10),
            'end_datetime' => now()->addDays(10)->addHours(4),
        ]);

    $this->actingAs($this->admin)
        ->patch(route('bookings.update', $booking), [
            'client_id' => $booking->client_id,
            'service_type' => $booking->bookingGroup->service_type,
            'location_type' => $booking->bookingGroup->location_type,
            'start_datetime' => $booking->start_datetime->toISOString(),
            'end_datetime' => $booking->end_datetime->toISOString(),
            'status' => BookingStatus::Cancelled->value,
            'payment_status' => BookingPaymentStatus::Pending->value,
        ])
        ->assertSessionHasErrors('status');

    $booking->refresh();
    expect($booking->status)->not->toBe(BookingStatus::Cancelled->value);
});

test('financial amounts zeroed on cancellation', function () {
    $booking = Booking::factory()
        ->forClient($this->client)
        ->withBookingGroup(fn ($group) => $group->state(['service_type' => ServiceType::GroupChildcareInvoiced->value]))
        ->create([
            'status' => BookingStatus::Confirmed->value,
            'charge_to_client_hourly' => 25.00,
            'paid_to_caregiver' => 100.00,
            'sitterwise_cut' => 50.00,
            'total_working_hour' => 4,
            'start_datetime' => now()->addDays(10),
            'end_datetime' => now()->addDays(10)->addHours(4),
        ]);

    $this->actingAs($this->admin)
        ->post(route('bookings.cancel', $booking), [
            'reason' => 'Cancellation for financial zeroing test',
        ]);

    $booking->refresh();
    expect((float) $booking->charge_to_client)->toBe(0.0)
        ->and((float) $booking->paid_to_caregiver)->toBe(0.0)
        ->and((float) $booking->sitterwise_cut)->toBe(0.0)
        ->and((float) $booking->total_service_amount)->toBe(0.0)
        ->and((float) $booking->total_amount)->toBe(0.0);
});

test('cancelled booking excluded from admin dashboard stats', function () {
    Booking::factory()->forClient($this->client)->create([
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => now()->addDays(1),
        'end_datetime' => now()->addDays(1)->addHours(4),
    ]);

    Booking::factory()->forClient($this->client)->cancelled()->create([
        'start_datetime' => now()->addDays(2),
        'end_datetime' => now()->addDays(2)->addHours(4),
    ]);

    $response = $this->actingAs($this->admin)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->has('stats')
    );
});

test('cancelled booking excluded from client dashboard', function () {
    Booking::factory()->forClient($this->client)->create([
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => now()->addDays(1),
        'end_datetime' => now()->addDays(1)->addHours(4),
    ]);

    Booking::factory()->forClient($this->client)->cancelled()->create([
        'start_datetime' => now()->addDays(2),
        'end_datetime' => now()->addDays(2)->addHours(4),
    ]);

    $response = $this->actingAs($this->clientUser)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->where('stats.active_bookings', 1)
        ->where('stats.past_bookings', 0)
    );
});

test('cancelled booking excluded from caregiver dashboard', function () {
    $caregiver = Caregiver::factory()->create();

    Booking::factory()->create([
        'caregiver_id' => $caregiver->id,
        'status' => BookingStatus::Completed->value,
        'paid_to_caregiver_total' => 100.00,
        'start_datetime' => now()->subDays(2),
        'end_datetime' => now()->subDays(2)->addHours(4),
    ]);

    Booking::factory()->create([
        'caregiver_id' => $caregiver->id,
        'status' => BookingStatus::Cancelled->value,
        'start_datetime' => now()->subDays(1),
        'end_datetime' => now()->subDays(1)->addHours(4),
    ]);

    $response = $this->actingAs($caregiver->user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->where('stats.completed_jobs', 1)
    );
});

test('cancelled booking still visible in admin booking list', function () {
    Booking::factory()->forClient($this->client)->cancelled()->create([
        'start_datetime' => now()->addDays(10),
        'end_datetime' => now()->addDays(10)->addHours(4),
    ]);

    $this->actingAs($this->admin)
        ->get(route('bookings.index'))
        ->assertSuccessful();
});

test('caregiver cannot cancel a booking', function () {
    $caregiver = Caregiver::factory()->create();
    $booking = Booking::factory()->forClient($this->client)->create([
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => now()->addDays(10),
        'end_datetime' => now()->addDays(10)->addHours(4),
    ]);

    $this->actingAs($caregiver->user)
        ->post(route('bookings.cancel', $booking), [
            'reason' => 'Caregiver attempting cancellation',
        ])
        ->assertForbidden();
});

test('guest cannot cancel a booking', function () {
    $booking = Booking::factory()->forClient($this->client)->create([
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => now()->addDays(10),
        'end_datetime' => now()->addDays(10)->addHours(4),
    ]);

    $this->patch(route('bookings.update', $booking), [
        'status' => BookingStatus::Cancelled->value,
    ])->assertRedirect(route('login'));
});
