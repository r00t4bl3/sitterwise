<?php

namespace App\Services;

use App\Models\Booking;
use App\Support\Settings;
use Carbon\Carbon;

/**
 * Decides whether a booking is a "Lifesaver" (a last-minute / stale-unclaimed
 * job worth rescuing). Rules (any true → Lifesaver):
 *   1. Unaccepted for `lifesaver.hours_unclaimed` hours after the first
 *      caregiver notification round.
 *   2. Created less than `lifesaver.short_notice_hours` before its start.
 *   3. An admin manually toggled the flag (lifesaver_override).
 *
 * All thresholds come from the web-editable settings store.
 */
class LifesaverService
{
    // 'reserved' is a live status value used by the caregiver reserve flow but is
    // not a BookingStatus enum case, so these are literal strings.
    private const OPEN_STATUSES = ['received', 'pending', 'reserved'];

    /**
     * Is the booking a Lifesaver right now (for display: caregiver card + admin).
     */
    public function isLifesaver(Booking $booking): bool
    {
        if ($booking->lifesaver_override !== null) {
            return $booking->lifesaver_override;
        }

        return $this->isShortNotice($booking) || $this->isUnclaimedTooLong($booking);
    }

    /**
     * Did the caregiver who took this booking rescue a Lifesaver? Used for the
     * Lifesaver badges — derivable after the fact from timestamps, so no snapshot
     * column is needed.
     */
    public function wasLifesaverRescue(Booking $booking): bool
    {
        if ($booking->lifesaver_override !== null) {
            return $booking->lifesaver_override;
        }

        if ($this->isShortNotice($booking)) {
            return true;
        }

        $firstNotified = $this->firstNotifiedAt($booking);

        return $firstNotified !== null
            && $booking->confirmed_at !== null
            && $firstNotified->diffInHours($booking->confirmed_at, false) >= $this->hoursUnclaimed();
    }

    private function isShortNotice(Booking $booking): bool
    {
        if ($booking->start_datetime === null || $booking->created_at === null) {
            return false;
        }

        return $booking->created_at->diffInHours($booking->start_datetime, false) < $this->shortNoticeHours();
    }

    private function isUnclaimedTooLong(Booking $booking): bool
    {
        if ($booking->caregiver_id !== null) {
            return false;
        }

        if (! in_array($booking->status, self::OPEN_STATUSES, true)) {
            return false;
        }

        $firstNotified = $this->firstNotifiedAt($booking);

        return $firstNotified !== null
            && $firstNotified->copy()->addHours($this->hoursUnclaimed())->isPast();
    }

    private function firstNotifiedAt(Booking $booking): ?Carbon
    {
        $earliest = $booking->caregiverNotifications()->min('notified_at');

        return $earliest ? Carbon::parse($earliest) : null;
    }

    private function hoursUnclaimed(): int
    {
        return (int) Settings::get('lifesaver.hours_unclaimed', 10);
    }

    private function shortNoticeHours(): int
    {
        return (int) Settings::get('lifesaver.short_notice_hours', 18);
    }
}
