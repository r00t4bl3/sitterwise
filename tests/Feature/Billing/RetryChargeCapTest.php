<?php

use App\Jobs\RetryJobCharge;
use App\Models\Booking;
use App\Models\Client;
use App\Services\Billing\PaymentFailureHandler;
use App\Support\Settings;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

function bookingWithAttempts(int $attempts): Booking
{
    $booking = Booking::factory()->forClient(Client::factory()->create())->create();
    $booking->forceFill(['charge_attempt_count' => $attempts])->saveQuietly();

    return $booking;
}

test('queues a retry while under the cap', function () {
    Bus::fake();
    Notification::fake();
    Settings::set('billing.max_charge_attempts', 4);

    app(PaymentFailureHandler::class)->handle(bookingWithAttempts(1), 'card_declined', 'Declined');

    Bus::assertDispatched(RetryJobCharge::class);
});

test('stops retrying once the cap is reached', function () {
    Bus::fake();
    Notification::fake();
    Settings::set('billing.max_charge_attempts', 4);

    app(PaymentFailureHandler::class)->handle(bookingWithAttempts(4), 'card_declined', 'Declined');

    Bus::assertNotDispatched(RetryJobCharge::class);
});

test('keeps retrying past 4 when max_charge_attempts is raised (configurable cap)', function () {
    Bus::fake();
    Notification::fake();
    Settings::set('billing.max_charge_attempts', 8);

    // Under the old hardcoded cap of 4 this would NOT retry; the setting now governs.
    app(PaymentFailureHandler::class)->handle(bookingWithAttempts(4), 'card_declined', 'Declined');

    Bus::assertDispatched(RetryJobCharge::class);
});
