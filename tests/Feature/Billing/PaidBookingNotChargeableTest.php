<?php

use App\Jobs\RetryJobCharge;
use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use App\Services\Billing\JobBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

test('paymentSettled treats charged, succeeded and paid as settled', function () {
    $booking = new Booking;

    foreach (['charged', 'succeeded', 'paid'] as $status) {
        $booking->payment_status = $status;
        expect($booking->paymentSettled())->toBeTrue();
    }

    foreach (['pending', 'failed', 'refunded', null] as $status) {
        $booking->payment_status = $status;
        expect($booking->paymentSettled())->toBeFalse();
    }
});

test('RetryJobCharge does not charge a booking already marked paid', function () {
    $booking = Booking::factory()->forClient(Client::factory()->create())->create([
        'payment_status' => 'paid',
    ]);

    $billing = mock(JobBillingService::class);
    $billing->shouldNotReceive('charge');

    (new RetryJobCharge($booking))->handle($billing);
});

test('admin processPayment refuses a booking already marked paid', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $booking = Booking::factory()->forClient(Client::factory()->create())->create([
        'status' => 'completed',
        'payment_status' => 'paid',
    ]);

    $this->actingAs($admin)
        ->post("/bookings/{$booking->id}/process-payment")
        ->assertRedirect()
        ->assertSessionHas('error', 'This booking has already been charged.');
});

test('a pending booking is still chargeable (unchanged)', function () {
    $booking = new Booking;
    $booking->payment_status = 'pending';

    expect($booking->paymentSettled())->toBeFalse();
});
