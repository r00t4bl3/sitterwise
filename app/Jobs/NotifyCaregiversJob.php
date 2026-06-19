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
        $now = now();

        $existing = BookingCaregiverNotification::where('booking_id', $this->booking->id)
            ->whereIn('caregiver_id', $this->caregiverIds)
            ->pluck('caregiver_id')
            ->toArray();

        $newIds = array_diff($this->caregiverIds, $existing);
        $existingIds = array_intersect($this->caregiverIds, $existing);

        if (! empty($newIds)) {
            BookingCaregiverNotification::insert(array_map(fn (int $id): array => [
                'booking_id' => $this->booking->id,
                'caregiver_id' => $id,
                'notified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $newIds));
        }

        if (! empty($existingIds)) {
            BookingCaregiverNotification::where('booking_id', $this->booking->id)
                ->whereIn('caregiver_id', $existingIds)
                ->update([
                    'notified_at' => $now,
                    'responded_at' => null,
                    'claimed' => false,
                    'updated_at' => $now,
                ]);
        }

        $allIds = array_merge($newIds, $existingIds);

        if (empty($allIds)) {
            return;
        }

        Caregiver::whereIn('id', $allIds)->get()
            ->each(fn (Caregiver $caregiver) => event(new BookingInvitationSent($this->booking, $caregiver)));
    }
}
