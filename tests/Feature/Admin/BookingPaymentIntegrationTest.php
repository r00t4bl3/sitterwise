<?php

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientPaymentMethod;
use App\Models\PricingRule;
use App\Models\User;
use App\Services\Billing\JobBillingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create(['stripe_customer_id' => 'cus_test123']);
    $this->paymentMethod = ClientPaymentMethod::factory()->create([
        'client_id' => $this->client->id,
        'is_default' => true,
        'status' => 'active',
    ]);

    // Create pricing rule to avoid 0 hourly rate during model calculation
    PricingRule::create([
        'service_type' => ServiceType::Babysitter->value,
        'number_of_children' => 0,
        'is_for_pets' => false,
        'charge_to_client' => 20,
        'paid_to_caregiver' => 15,
        'sitterwise_cut' => 5,
        'payment_form' => 'Stripe',
    ]);
});

it('processes manual payment and calls billing service', function () {
    $start = Carbon::parse('2026-05-01 10:00:00');
    $end = Carbon::parse('2026-05-01 15:00:00'); // 5 hours

    $booking = Booking::factory()->forClient($this->client)->create([
        'status' => BookingStatus::Completed->value,
        'charge_to_client_hourly' => 20,
        'start_datetime' => $start,
        'end_datetime' => $end,
        'total_working_hour' => 5,
        'payment_status' => BookingPaymentStatus::Pending->value,
    ]);

    $mockBillingService = Mockery::mock(JobBillingService::class);
    $mockBillingService->shouldReceive('charge')
        ->once()
        ->with(Mockery::on(fn ($b) => $b->id === $booking->id))
        ->andReturn(['success' => true, 'message' => 'Payment successful']);

    $this->app->instance(JobBillingService::class, $mockBillingService);

    $this->app->instance(Booking::class, $booking);

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware()
        ->post(route('bookings.processPayment', $booking), [
            'total_working_hour' => 5,
            'reimbursement' => 10,
            'bonus' => 0,
            'tip' => 5,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $booking->refresh();
    expect((float) $booking->total_working_hour)->toBe(5.0);
    expect((float) $booking->reimbursement)->toBe(10.0);
    expect((float) $booking->tip)->toBe(5.0);
    expect((float) $booking->total_service_amount)->toBe(110.0); // (20 * 5) + 10 + 0
    expect((float) $booking->total_amount)->toBe(115.0); // 110 + 5
});

it('does not mutate amounts or charge when the booking is already charged', function () {
    $booking = Booking::factory()->forClient($this->client)->create([
        'status' => BookingStatus::Paid->value,
        'payment_status' => 'charged',
        'charge_to_client_hourly' => 20,
        'start_datetime' => Carbon::parse('2026-05-01 10:00:00'),
        'end_datetime' => Carbon::parse('2026-05-01 15:00:00'), // 5 hours
        'total_working_hour' => 5,
        'reimbursement' => 10,
    ]);

    $mockBillingService = Mockery::mock(JobBillingService::class);
    $mockBillingService->shouldNotReceive('charge');
    $this->app->instance(JobBillingService::class, $mockBillingService);
    $this->app->instance(Booking::class, $booking);

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware()
        ->post(route('bookings.processPayment', $booking), [
            'total_working_hour' => 8,
            'reimbursement' => 999,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'This booking has already been charged.');

    $booking->refresh();
    expect((float) $booking->reimbursement)->toBe(10.0);
    expect((float) $booking->total_working_hour)->toBe(5.0);
});

it('handles payment failure and keeps booking details', function () {
    $start = Carbon::parse('2026-05-01 10:00:00');
    $end = Carbon::parse('2026-05-01 15:00:00'); // 5 hours

    $booking = Booking::factory()->forClient($this->client)->create([
        'status' => BookingStatus::Completed->value,
        'charge_to_client_hourly' => 20,
        'start_datetime' => $start,
        'end_datetime' => $end,
        'total_working_hour' => 5,
    ]);

    $mockBillingService = Mockery::mock(JobBillingService::class);
    $mockBillingService->shouldReceive('charge')
        ->once()
        ->with(Mockery::on(fn ($b) => $b->id === $booking->id))
        ->andReturn(['success' => false, 'message' => 'Card declined']);

    $this->app->instance(JobBillingService::class, $mockBillingService);

    $this->app->instance(Booking::class, $booking);

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware()
        ->post(route('bookings.processPayment', $booking), [
            'total_working_hour' => 5,
            'reimbursement' => 10,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Booking details saved, but payment failed: Card declined');

    $booking->refresh();
    expect((float) $booking->total_working_hour)->toBe(5.0);
    expect((float) $booking->reimbursement)->toBe(10.0);
    expect((float) $booking->total_service_amount)->toBe(110.0);
});
