<?php

namespace App\Enums;

/**
 * Represents the resolution state of a CaregiverAssignment.
 *
 * - null:              Actively assigned. Caregiver is expected to work this booking.
 * - completed:         Caregiver completed the job. Set during checkout/payment flow.
 * - backed_out:        Caregiver voluntarily backed out via the back-out form.
 * - backed_out_excused: Admin excused a caregiver's backout retroactively.
 * - reassigned:        Admin swapped the caregiver for another (edit sheet or Replace flow).
 * - no_show:           Caregiver did not show up for the job.
 * - cancelled_by_sitterwise: Entire booking was cancelled by admin.
 */
enum AssignmentResolution: string
{
    case Completed = 'completed';
    case BackedOut = 'backed_out';
    case BackedOutExcused = 'backed_out_excused';
    case Reassigned = 'reassigned';
    case NoShow = 'no_show';
    case CancelledBySitterwise = 'cancelled_by_sitterwise';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completed',
            self::BackedOut => 'Backed Out',
            self::BackedOutExcused => 'Backed Out (Excused)',
            self::Reassigned => 'Reassigned',
            self::NoShow => 'No-Show',
            self::CancelledBySitterwise => 'Cancelled by Sitterwise',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => '#22C55E',
            self::BackedOut => '#EF4444',
            self::BackedOutExcused => '#F59E0B',
            self::Reassigned => '#0EA5E9',
            self::NoShow => '#DC2626',
            self::CancelledBySitterwise => '#6B7280',
        };
    }
}
