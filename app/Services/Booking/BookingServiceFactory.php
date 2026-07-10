<?php

namespace App\Services\Booking;

use App\Services\Booking\Contracts\BookingServiceInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

class BookingServiceFactory
{
    public function make(): BookingServiceInterface
    {
        $user = Auth::user();

        if (! $user) {
            // Booking routes are auth-gated, so this only happens if the factory
            // is reached unauthenticated — send them to log in rather than
            // silently resolving an admin service.
            throw new AuthenticationException;
        }

        return match ($user->role) {
            'caregiver' => app(CaregiverBookingService::class),
            'client' => app(ClientBookingService::class),
            default => app(AdminBookingService::class),
        };
    }
}
