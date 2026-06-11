<?php

namespace App\Jobs;

use App\Events\BookingInvitationSent;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\Caregiver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyCaregiversJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $tries = 3;

    public $backoff = 10;

    public function __construct(
        public Booking $booking,
        public array $caregiverIds,
    ) {}

    public function handle(): void
    {
        $existing = BookingCaregiverNotification::where('booking_id', $this->booking->id)
            ->whereIn('caregiver_id', $this->caregiverIds)
            ->pluck('caregiver_id')
            ->toArray();

        $newIds = array_values(array_diff($this->caregiverIds, $existing));

        if (empty($newIds)) {
            return;
        }

        $now = now();
        $inserts = array_map(fn (int $id): array => [
            'booking_id' => $this->booking->id,
            'caregiver_id' => $id,
            'notified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $newIds);

        BookingCaregiverNotification::insert($inserts);

        Caregiver::whereIn('id', $newIds)->get()
            ->each(fn (Caregiver $caregiver) => event(new BookingInvitationSent($this->booking, $caregiver)));
    }
}
