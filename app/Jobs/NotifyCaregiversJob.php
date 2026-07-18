<?php

namespace App\Jobs;

use App\Enums\BookingStatus;
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

    /**
     * @param  int[]|null  $bookingIds  All booking dates in the group to record notifications for.
     *                                  Defaults to just the primary booking. The invitation itself is
     *                                  still sent once per caregiver — the primary booking's message
     *                                  already conveys the full multi-day trip.
     */
    public function __construct(
        public Booking $booking,
        public array $caregiverIds,
        public ?array $bookingIds = null,
    ) {}

    public function handle(): void
    {
        $now = now();

        if (empty($this->caregiverIds)) {
            return;
        }

        $notifiableStatuses = [BookingStatus::Received->value, BookingStatus::Pending->value];

        // Re-check the booking's CURRENT status. The admin's guard runs at dispatch
        // time, but this queued job (and its retries) can run after the booking was
        // accepted or cancelled — don't invite caregivers to a job that is no longer
        // open.
        $booking = $this->booking->fresh();

        if (! $booking || ! in_array($booking->status, $notifiableStatuses, true)) {
            return;
        }

        $bookingIds = ! empty($this->bookingIds) ? $this->bookingIds : [$booking->id];

        // In a multi-day group some dates may have been filled while others remain
        // open — only record against the ones still notifiable.
        $openBookingIds = Booking::whereIn('id', $bookingIds)
            ->whereIn('status', $notifiableStatuses)
            ->pluck('id')
            ->all();

        if (empty($openBookingIds)) {
            return;
        }

        foreach ($openBookingIds as $bookingId) {
            $this->recordNotifications((int) $bookingId, $now);
        }

        Caregiver::whereIn('id', $this->caregiverIds)->get()
            ->each(fn (Caregiver $caregiver) => event(new BookingInvitationSent($booking, $caregiver)));
    }

    private function recordNotifications(int $bookingId, \DateTimeInterface $now): void
    {
        $existing = BookingCaregiverNotification::where('booking_id', $bookingId)
            ->whereIn('caregiver_id', $this->caregiverIds)
            ->pluck('caregiver_id')
            ->toArray();

        $newIds = array_diff($this->caregiverIds, $existing);
        $existingIds = array_intersect($this->caregiverIds, $existing);

        if (! empty($newIds)) {
            BookingCaregiverNotification::insert(array_map(fn (int $id): array => [
                'booking_id' => $bookingId,
                'caregiver_id' => $id,
                'notified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $newIds));
        }

        if (! empty($existingIds)) {
            BookingCaregiverNotification::where('booking_id', $bookingId)
                ->whereIn('caregiver_id', $existingIds)
                ->update([
                    'notified_at' => $now,
                    'responded_at' => null,
                    'claimed' => false,
                    'updated_at' => $now,
                ]);
        }
    }
}
