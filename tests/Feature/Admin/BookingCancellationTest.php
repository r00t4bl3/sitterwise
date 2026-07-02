<?php

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Events\BookingCancelled;
use App\Listeners\SendBookingCancelledNotifications;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

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

    Notification::fake();
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

test('admin can cancel an unassigned booking (no caregiver)', function () {
    $booking = Booking::factory()
        ->forClient($this->client)
        ->withBookingGroup(fn ($group) => $group->state(['service_type' => ServiceType::GroupChildcareInvoiced->value]))
        ->create([
            'status' => BookingStatus::Received->value,
            'caregiver_id' => null,
            'start_datetime' => now()->addDays(10),
            'end_datetime' => now()->addDays(10)->addHours(4),
        ]);

    $this->actingAs($this->admin)
        ->post(route('bookings.cancel', $booking), [
            'reason' => 'No caregiver claimed it in time',
        ])
        ->assertRedirect();

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled->value);
});

test('cancel_group cancels every active sibling in the group', function () {
    $group = BookingGroup::factory()->create([
        'client_id' => $this->client->id,
        'service_type' => ServiceType::Babysitter->value,
    ]);

    $bookings = Booking::factory()->count(3)->create([
        'booking_group_id' => $group->id,
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => now()->addDays(10),
        'end_datetime' => now()->addDays(10)->addHours(4),
    ]);

    $this->actingAs($this->admin)
        ->post(route('bookings.cancel', $bookings[0]), [
            'reason' => 'Family emergency — cancel all dates',
            'cancel_group' => true,
        ])
        ->assertRedirect();

    foreach ($bookings as $booking) {
        expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled->value);
    }
});

test('cancel without cancel_group leaves siblings confirmed', function () {
    $group = BookingGroup::factory()->create([
        'client_id' => $this->client->id,
        'service_type' => ServiceType::Babysitter->value,
    ]);

    $bookings = Booking::factory()->count(3)->create([
        'booking_group_id' => $group->id,
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => now()->addDays(10),
        'end_datetime' => now()->addDays(10)->addHours(4),
    ]);

    $this->actingAs($this->admin)
        ->post(route('bookings.cancel', $bookings[0]), [
            'reason' => 'Only this date',
        ])
        ->assertRedirect();

    expect($bookings[0]->fresh()->status)->toBe(BookingStatus::Cancelled->value)
        ->and($bookings[1]->fresh()->status)->toBe(BookingStatus::Confirmed->value)
        ->and($bookings[2]->fresh()->status)->toBe(BookingStatus::Confirmed->value);
});

test('cancellation notification listener is queued', function () {
    expect(app(SendBookingCancelledNotifications::class))
        ->toBeInstanceOf(ShouldQueue::class);
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
        ->where('stats.totalBookings', 1)
        ->where('stats.completedBookings', 0)
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
        ->where('stats.completedJobs', 1)
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

test('cancelling a booking dispatches BookingCancelled event', function () {
    Event::fake();

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
            'reason' => 'Test reason',
        ]);

    Event::assertDispatched(BookingCancelled::class, function ($event) use ($booking) {
        return $event->booking->id === $booking->id
            && $event->reason === 'Test reason'
            && $event->cancelledBy->id === $this->admin->id;
    });
});

test('sanity: direct notify works', function () {
    $this->clientUser->notify(new BookingCancelledNotification(
        booking: Booking::factory()->forClient($this->client)->create(),
        reason: 'test',
        cancelledBy: $this->admin,
    ));

    Notification::assertSentTo(
        $this->clientUser,
        BookingCancelledNotification::class,
    );
});

test('listener notifies client user', function () {
    $group = BookingGroup::factory()->create([
        'client_id' => $this->client->id,
        'service_type' => ServiceType::GroupChildcareInvoiced->value,
    ]);

    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'caregiver_id' => null,
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => now()->addDays(10),
        'end_datetime' => now()->addDays(10)->addHours(4),
    ]);

    $booking->load('client.user');

    expect($booking->client)->not->toBeNull();
    expect($booking->client->user)->not->toBeNull();

    $event = new BookingCancelled(
        booking: $booking,
        reason: 'Test reason',
        cancelledBy: $this->admin,
    );

    $listener = app(SendBookingCancelledNotifications::class);
    $listener->handle($event);

    Notification::assertSentTo(
        $this->clientUser,
        BookingCancelledNotification::class,
    );
});

test('listener notifies caregiver when assigned', function () {
    $caregiver = Caregiver::factory()->create();

    $group = BookingGroup::factory()->create([
        'client_id' => $this->client->id,
        'service_type' => ServiceType::GroupChildcareInvoiced->value,
    ]);

    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'caregiver_id' => $caregiver->id,
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => now()->addDays(10),
        'end_datetime' => now()->addDays(10)->addHours(4),
    ]);

    $event = new BookingCancelled(
        booking: $booking,
        reason: 'Test reason',
        cancelledBy: $this->admin,
    );

    $listener = app(SendBookingCancelledNotifications::class);
    $listener->handle($event);

    Notification::assertSentTo(
        $caregiver->user,
        BookingCancelledNotification::class,
    );
});

test('listener notifies all admins', function () {
    $group = BookingGroup::factory()->create([
        'client_id' => $this->client->id,
        'service_type' => ServiceType::GroupChildcareInvoiced->value,
    ]);

    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'caregiver_id' => null,
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => now()->addDays(10),
        'end_datetime' => now()->addDays(10)->addHours(4),
    ]);

    $otherAdmin = User::factory()->create(['role' => 'admin']);

    $event = new BookingCancelled(
        booking: $booking,
        reason: 'Test reason',
        cancelledBy: $this->admin,
    );

    $listener = app(SendBookingCancelledNotifications::class);
    $listener->handle($event);

    Notification::assertSentTo(
        [$this->admin, $otherAdmin],
        BookingCancelledNotification::class,
    );
});
