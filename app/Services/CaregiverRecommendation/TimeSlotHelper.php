<?php

namespace App\Services\CaregiverRecommendation;

class TimeSlotHelper
{
    private const PT = 'America/Los_Angeles';

    /**
     * Determine required time slots for a date range.
     *
     * Each date maps to morning (06:00-12:00 PT), afternoon (12:00-18:00 PT),
     * and/or evening (18:00-23:00 PT) based on the booking's PT wall-clock times.
     *
     * @return array<string, string[]> e.g. ['2026-06-10' => ['morning', 'afternoon']]
     */
    public static function getRequiredTimeSlots(
        string|\DateTimeInterface $startDate,
        string|\DateTimeInterface $endDate,
    ): array {
        $tz = new \DateTimeZone(self::PT);

        $start = (new \DateTime($startDate))->setTimezone($tz);
        $end = (new \DateTime($endDate))->setTimezone($tz);

        $requiredSlots = [];

        $current = clone $start;
        while ($current <= $end) {
            $dateKey = $current->format('Y-m-d');
            $slots = [];

            $dayStart = ($current->format('Y-m-d') === $start->format('Y-m-d'))
                ? $start
                : new \DateTime($dateKey.' 00:00:00', $tz);

            $dayEnd = ($current->format('Y-m-d') === $end->format('Y-m-d'))
                ? $end
                : new \DateTime($dateKey.' 23:59:59', $tz);

            $morningStart = new \DateTime($dateKey.' 06:00:00', $tz);
            $morningEnd = new \DateTime($dateKey.' 12:00:00', $tz);
            $afternoonStart = new \DateTime($dateKey.' 12:00:00', $tz);
            $afternoonEnd = new \DateTime($dateKey.' 18:00:00', $tz);
            $eveningStart = new \DateTime($dateKey.' 18:00:00', $tz);
            $eveningEnd = new \DateTime($dateKey.' 23:00:00', $tz);

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
