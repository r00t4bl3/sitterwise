<?php

namespace App\Services\CaregiverRecommendation;

use App\Models\Availability;
use App\Models\Booking;
use App\Models\BookingAvailabilitySlot;

class AvailabilityReservationService
{
    /**
     * Reserve time slots for a booking on the caregiver's availability.
     *
     * For each date in the booking's date range, determines which time slots
     * overlap (morning/afternoon/evening) and creates a BookingAvailabilitySlot
     * record for each.
     */
    public function reserve(Booking $booking): void
    {
        if (! $booking->caregiver_id) {
            return;
        }

        $dateSlots = TimeSlotHelper::getRequiredTimeSlots(
            $booking->start_datetime,
            $booking->end_datetime,
        );

        foreach ($dateSlots as $date => $slots) {
            $availability = Availability::where('caregiver_id', $booking->caregiver_id)
                ->whereDate('date', $date)
                ->first();

            if (! $availability) {
                continue;
            }

            foreach ($slots as $slot) {
                BookingAvailabilitySlot::firstOrCreate([
                    'booking_id' => $booking->id,
                    'caregiver_id' => $booking->caregiver_id,
                    'availability_id' => $availability->id,
                    'date' => $date,
                    'time_slot' => $slot,
                ]);
            }
        }
    }

    /**
     * Release all reserved time slots for a booking.
     */
    public function release(Booking $booking): void
    {
        BookingAvailabilitySlot::where('booking_id', $booking->id)->delete();
    }
}
