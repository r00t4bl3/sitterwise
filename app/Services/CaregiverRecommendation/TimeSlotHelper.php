<?php

namespace App\Services\CaregiverRecommendation;

class TimeSlotHelper
{
    /**
     * Determine required time slots for a date range.
     *
     * Each date maps to morning (06:00-12:00), afternoon (12:00-18:00),
     * and/or evening (18:00-23:00) based on the booking's start/end times.
     *
     * @return array<string, string[]> e.g. ['2026-06-10' => ['morning', 'afternoon']]
     */
    public static function getRequiredTimeSlots(
        string|\DateTimeInterface $startDate,
        string|\DateTimeInterface $endDate,
    ): array {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        $requiredSlots = [];

        $current = clone $start;
        while ($current <= $end) {
            $dateKey = $current->format('Y-m-d');
            $slots = [];

            $dayStart = ($current->format('Y-m-d') === $start->format('Y-m-d'))
                ? $start
                : new \DateTime($dateKey.' 00:00:00');

            $dayEnd = ($current->format('Y-m-d') === $end->format('Y-m-d'))
                ? $end
                : new \DateTime($dateKey.' 23:59:59');

            $morningStart = new \DateTime($dateKey.' 06:00:00');
            $morningEnd = new \DateTime($dateKey.' 12:00:00');
            $afternoonStart = new \DateTime($dateKey.' 12:00:00');
            $afternoonEnd = new \DateTime($dateKey.' 18:00:00');
            $eveningStart = new \DateTime($dateKey.' 18:00:00');
            $eveningEnd = new \DateTime($dateKey.' 23:00:00');

            if ($dayStart < $morningEnd && $dayEnd > $morningStart) {
                $slots[] = 'morning';
            }
            if ($dayStart < $afternoonEnd && $dayEnd > $afternoonStart) {
                $slots[] = 'afternoon';
            }
            if ($dayStart < $eveningEnd && $dayEnd > $eveningStart) {
                $slots[] = 'evening';
            }

            $requiredSlots[$dateKey] = $slots;
            $current->modify('+1 day');
        }

        return $requiredSlots;
    }
}
