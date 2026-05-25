<?php

namespace App\Enums;

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
