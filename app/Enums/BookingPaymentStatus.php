<?php

namespace App\Enums;

enum BookingPaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Refunded => 'Refunded',
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
            self::Pending => [
                'bg' => 'bg-yellow-100',
                'text' => 'text-yellow-800',
                'border' => 'border-yellow-300',
            ],
            self::Paid => [
                'bg' => 'bg-emerald-100',
                'text' => 'text-emerald-800',
                'border' => 'border-emerald-300',
            ],
            self::Failed => [
                'bg' => 'bg-red-100',
                'text' => 'text-red-800',
                'border' => 'border-red-300',
            ],
            self::Refunded => [
                'bg' => 'bg-gray-100',
                'text' => 'text-gray-800',
                'border' => 'border-gray-300',
            ],
        };
    }
}
