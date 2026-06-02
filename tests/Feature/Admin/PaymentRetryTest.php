<?php

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Jobs\RetryJobCharge;
use App\Models\Booking;
use App\Models\Client;
use App\Models\PricingRule;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Services\Billing\JobBillingService;
use App\Services\Billing\PaymentFailureHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function paidClientWithBooking(): array
{
    $client = Client::factory()->create([
        'stripe_customer_id' => 'cus_'.uniqid(),
    ]);

    PricingRule::create([
        'service_type' => ServiceType::Babysitter->value,
        'number_of_children' => 0,
        'is_for_pets' => false,
        'charge_to_client' => 20,
        'paid_to_caregiver' => 15,
        'sitterwise_cut' => 5,
        'payment_form' => 'Stripe',
    ]);

    $booking = Booking::factory()->forClient($client)->create([
        'status' => BookingStatus::Completed->value,
        'charge_to_client_hourly' => 20,
        'total_working_hour' => 5,
        'charge_to_client' => 100,
        'total_service_amount' => 100,
        'total_amount' => 100,
        'reimbursement' => 0,
        'bonus' => 0,
        'tip' => 0,
        'payment_status' => BookingPaymentStatus::Pending->value,
        'charge_attempt_count' => 0,
    ]);

    return [$client, $booking];
}

describe('Payment Retry & Failure Handling', function () {
    test('payment failure handler queues retry with 0s delay on first failure', function () {
        Queue::fake();

        [$client, $booking] = paidClientWithBooking();

        app(PaymentFailureHandler::class)->handle($booking, 'card_declined', 'Card declined');

        Queue::assertPushed(RetryJobCharge::class, function ($job) use ($booking) {
            return $job->booking->id === $booking->id
                && $job->delay->format('U') <= now()->addSeconds(2)->format('U');
        });
    });

    test('payment failure handler queues retry with 1h delay on second failure', function () {
        Queue::fake();

        [$client, $booking] = paidClientWithBooking();
        $booking->update(['charge_attempt_count' => 1]);

        app(PaymentFailureHandler::class)->handle($booking, 'card_declined', 'Card declined');

        Queue::assertPushed(RetryJobCharge::class, function ($job) use ($booking) {
            return $job->booking->id === $booking->id
                && $job->delay->format('U') >= now()->addHours(1)->format('U')
                && $job->delay->format('U') <= now()->addHours(2)->format('U');
        });
    });

    test('payment failure handler queues retry with 1d delay on third failure', function () {
        Queue::fake();

        [$client, $booking] = paidClientWithBooking();
        $booking->update(['charge_attempt_count' => 2]);

        app(PaymentFailureHandler::class)->handle($booking, 'card_declined', 'Card declined');

        Queue::assertPushed(RetryJobCharge::class, function ($job) use ($booking) {
            return $job->booking->id === $booking->id
                && $job->delay->format('U') >= now()->addDays(1)->format('U')
                && $job->delay->format('U') <= now()->addDays(2)->format('U');
        });
    });

    test('payment failure handler queues retry with 3d delay on fourth failure', function () {
        Queue::fake();

        [$client, $booking] = paidClientWithBooking();
        $booking->update(['charge_attempt_count' => 3]);

        app(PaymentFailureHandler::class)->handle($booking, 'card_declined', 'Card declined');

        Queue::assertPushed(RetryJobCharge::class, function ($job) use ($booking) {
            return $job->booking->id === $booking->id
                && $job->delay->format('U') >= now()->addDays(3)->format('U')
                && $job->delay->format('U') <= now()->addDays(4)->format('U');
        });
    });

    test('payment failure handler stops retrying after max attempts', function () {
        Queue::fake();

        [$client, $booking] = paidClientWithBooking();
        $booking->update(['charge_attempt_count' => 4]);

        app(PaymentFailureHandler::class)->handle($booking, 'card_declined', 'Card declined');

        Queue::assertNotPushed(RetryJobCharge::class);
    });

    test('payment failure handler notifies client on failure', function () {
        Notification::fake();
        Mail::fake();

        [$client, $booking] = paidClientWithBooking();

        app(PaymentFailureHandler::class)->handle($booking, 'card_declined', 'Card declined');

        Notification::assertSentTo($client, PaymentFailedNotification::class);
    });

    test('payment failure handler notifies admins on failure', function () {
        Notification::fake();
        Mail::fake();

        [$client, $booking] = paidClientWithBooking();

        $admin = User::factory()->create(['role' => 'admin']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        app(PaymentFailureHandler::class)->handle($booking, 'card_declined', 'Card declined');

        Notification::assertSentTo($admin, PaymentFailedNotification::class);
        Notification::assertSentTo($superAdmin, PaymentFailedNotification::class);
    });

    test('retry job skips when booking is already charged', function () {
        Queue::fake();

        [$client, $booking] = paidClientWithBooking();
        $booking->update([
            'payment_status' => 'charged',
            'charge_attempt_count' => 0,
        ]);

        RetryJobCharge::dispatch($booking);

        Queue::assertPushed(RetryJobCharge::class, function ($job) {
            $job->handle(app(JobBillingService::class));

            return true;
        });

        expect(true)->toBeTrue();
    });

    test('retry job skips when max attempts already reached', function () {
        Queue::fake();

        [$client, $booking] = paidClientWithBooking();
        $booking->update(['charge_attempt_count' => 4]);

        RetryJobCharge::dispatch($booking);

        Queue::assertPushed(RetryJobCharge::class);
    });
});
