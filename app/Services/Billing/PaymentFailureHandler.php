<?php

namespace App\Services\Billing;

use App\Jobs\RetryJobCharge;
use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Support\Settings;
use Illuminate\Support\Facades\Log;

class PaymentFailureHandler
{
    public function handle(Booking $booking, ?string $errorCode = null, string $errorMessage = ''): void
    {
        $client = $booking->client;
        $attemptCount = $booking->charge_attempt_count;
        // Single source of truth for the retry cap, shared with RetryJobCharge
        // so the two never disagree (raise this setting to keep retrying longer
        // for chronic-failure clients).
        $maxAttempts = (int) Settings::get('billing.max_charge_attempts', 4);

        $this->notifyClient($client, $booking, $attemptCount, $errorMessage);

        /**
         * Admins are notified on the first failure (heads-up) and the final
         * give-up (action needed). The intermediate retries are automated and
         * every admin account receives its own copy on two channels, so
         * per-attempt notices turned one bad card into an inbox flood.
         */
        if ($attemptCount <= 1 || $attemptCount >= $maxAttempts) {
            $this->notifyAdmins($booking, $attemptCount, $errorMessage);
        }

        if ($attemptCount < $maxAttempts) {
            $delay = $this->getRetryDelay($attemptCount);

            RetryJobCharge::dispatch($booking)
                ->delay(now()->add($delay));

            Log::info('Payment retry queued', [
                'booking_id' => $booking->id,
                'attempt' => $attemptCount + 1,
                'delay' => $delay,
            ]);
        } else {
            Log::warning('Payment failed permanently - max retries exceeded', [
                'booking_id' => $booking->id,
                'attempts' => $attemptCount,
            ]);
        }
    }

    protected function notifyClient(Client $client, Booking $booking, int $attemptCount, string $errorMessage): void
    {
        try {
            $client->notify(new PaymentFailedNotification(
                $booking,
                $attemptCount,
                $errorMessage,
                'client'
            ));
        } catch (\Exception $e) {
            Log::error('Failed to notify client of payment failure', [
                'client_id' => $client->id,
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyAdmins(Booking $booking, int $attemptCount, string $errorMessage): void
    {
        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();

        foreach ($admins as $admin) {
            try {
                $admin->notify(new PaymentFailedNotification(
                    $booking,
                    $attemptCount,
                    $errorMessage,
                    'admin'
                ));
            } catch (\Exception $e) {
                Log::error('Failed to notify admin of payment failure', [
                    'admin_id' => $admin->id,
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function getRetryDelay(int $attemptCount): \DateInterval
    {
        return match ($attemptCount) {
            0 => new \DateInterval('PT0S'),
            1 => new \DateInterval('PT1H'),
            2 => new \DateInterval('P1D'),
            3 => new \DateInterval('P3D'),
            default => new \DateInterval('PT0S'),
        };
    }
}
