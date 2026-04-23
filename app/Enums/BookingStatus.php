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

    /**
     * Get the Tailwind CSS color classes for this status.
     *
     * @return array{bg: string, text: string, border: string}
     */
    public function colors(): array
    {
        return match ($this) {
            self::Received => [
                'bg' => 'bg-violet-100',
                'text' => 'text-violet-800',
                'border' => 'border-violet-300',
            ],
            self::Pending => [
                'bg' => 'bg-yellow-100',
                'text' => 'text-yellow-800',
                'border' => 'border-yellow-300',
            ],
            self::Confirmed => [
                'bg' => 'bg-blue-100',
                'text' => 'text-blue-800',
                'border' => 'border-blue-300',
            ],
            self::Completed => [
                'bg' => 'bg-gray-100',
                'text' => 'text-gray-800',
                'border' => 'border-gray-300',
            ],
            self::Paid => [
                'bg' => 'bg-emerald-100',
                'text' => 'text-emerald-800',
                'border' => 'border-emerald-300',
            ],
            self::Cancelled => [
                'bg' => 'bg-red-100',
                'text' => 'text-red-800',
                'border' => 'border-red-300',
            ],
        };
    }
}
