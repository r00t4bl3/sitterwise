<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Received = 'received';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }
}
