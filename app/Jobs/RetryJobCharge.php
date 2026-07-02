<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\Billing\JobBillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryJobCharge implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $backoff = 0;

    public function __construct(
        public Booking $booking
    ) {}

    public function handle(JobBillingService $billingService): void
    {
        if ($this->booking->payment_status === 'charged' ||
            $this->booking->payment_status === 'succeeded') {
            Log::info('Booking already charged, skipping retry', [
                'booking_id' => $this->booking->id,
            ]);

            return;
        }

        if ($this->booking->charge_attempt_count >= 4) {
            Log::warning('Max retry attempts reached, not retrying', [
                'booking_id' => $this->booking->id,
                'attempts' => $this->booking->charge_attempt_count,
            ]);

            return;
        }

        Log::info('Retrying payment charge', [
            'booking_id' => $this->booking->id,
            'attempt' => $this->booking->charge_attempt_count + 1,
        ]);

        $billingService->charge($this->booking);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RetryJobCharge failed permanently', [
            'booking_id' => $this->booking->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
