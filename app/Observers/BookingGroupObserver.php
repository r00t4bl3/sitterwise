<?php

namespace App\Observers;

use App\Enums\BookingStatus;
use App\Models\BookingGroup;

class BookingGroupObserver
{
    /**
     * When shared fields that affect pricing change on a BookingGroup,
     * reprice all child bookings that are not yet finalized.
     */
    public function updated(BookingGroup $group): void
    {
        if (! $group->isDirty(['service_type', 'children', 'pets'])) {
            return;
        }

        $group->loadMissing('bookings');

        $finalized = [
            BookingStatus::Completed->value,
            BookingStatus::Paid->value,
            BookingStatus::Cancelled->value,
        ];
        $updatedIds = [];

        foreach ($group->bookings as $booking) {
            if (in_array($booking->status, $finalized, true) || $booking->end_datetime->isPast()) {
                continue;
            }

            $booking->calculateHourlyRate($group);
            $booking->calculateTotalAmount();
            $booking->saveQuietly();
            $updatedIds[] = $booking->id;
        }

        if (! empty($updatedIds)) {
            logger()->info('BookingGroupObserver repriced bookings', [
                'group_id' => $group->id,
                'booking_ids' => $updatedIds,
                'dirty_fields' => array_keys($group->getDirty()),
            ]);
        }
    }
}
