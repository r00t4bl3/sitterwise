<?php

namespace App\Services\Booking;

use App\Services\Booking\Contracts\BookingServiceInterface;
use Illuminate\Support\Facades\Auth;

class BookingServiceFactory
{
    public function make(): BookingServiceInterface
    {
        $user = Auth::user();

        if (! $user) {
            dd('No authenticated user found.'); // Debugging line

            return app(AdminBookingService::class);
        }

        return match ($user->role) {
            'caregiver' => app(CaregiverBookingService::class),
            default => app(AdminBookingService::class),
        };
    }
}
