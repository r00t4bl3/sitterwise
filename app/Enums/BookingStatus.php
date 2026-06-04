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
                'bg' => 'bg-red-300/50 dark:bg-red-900/30',
                'text' => 'text-red-800/80 dark:text-red-300',
                'border' => 'border-red-500/50 dark:border-red-700/50',
            ],
            self::Pending => [
                'bg' => 'bg-yellow-100 dark:bg-yellow-900/40',
                'text' => 'text-yellow-800 dark:text-yellow-300',
                'border' => 'border-yellow-300 dark:border-yellow-700/50',
            ],
            self::Confirmed => [
                'bg' => 'bg-blue-100 dark:bg-blue-900/40',
                'text' => 'text-blue-800 dark:text-blue-300',
                'border' => 'border-blue-300 dark:border-blue-700/50',
            ],
            self::Completed => [
                'bg' => 'bg-violet-100 dark:bg-violet-900/40',
                'text' => 'text-violet-800 dark:text-violet-300',
                'border' => 'border-violet-300 dark:border-violet-700/50',
            ],
            self::Paid => [
                'bg' => 'bg-emerald-100 dark:bg-emerald-900/40',
                'text' => 'text-emerald-800 dark:text-emerald-300',
                'border' => 'border-emerald-300 dark:border-emerald-700/50',
            ],
            self::Cancelled => [
                'bg' => 'bg-gray-100 dark:bg-gray-800/50',
                'text' => 'text-gray-800 dark:text-gray-400',
                'border' => 'border-gray-300 dark:border-gray-700/50',
            ],
        };
    }
}
