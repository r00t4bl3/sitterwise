<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\Booking\GuestBookingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GuestBookingController extends Controller
{
    public function __construct(
        private GuestBookingService $guestBookingService,
    ) {}

    public function create()
    {
        return $this->guestBookingService->create();
    }

    public function store(Request $request)
    {
        return $this->guestBookingService->store($request);
    }

    public function confirmation(Booking $booking)
    {
        return Inertia::render('guest/bookings/confirmation', [
            'booking' => [
                'id' => $booking->id,
                'ulid' => $booking->ulid,
                'service_type' => $booking->service_type,
                'location_type' => $booking->location_type,
                'start_datetime' => $booking->start_datetime,
                'end_datetime' => $booking->end_datetime,
                'status' => $booking->status,
                'client_first_name' => $booking->client_first_name,
                'client_last_name' => $booking->client_last_name,
                'address_line1' => $booking->address_line1,
                'address_city' => $booking->address_city,
                'address_state' => $booking->address_state,
                'address_zip' => $booking->address_zip,
            ],
        ]);
    }
}
