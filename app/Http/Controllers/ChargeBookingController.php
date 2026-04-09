<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class ChargeBookingController extends Controller
{
    public function create(Request $request)
    {
        $bookingId = $request->query('booking_id');

        if ($bookingId) {
            $booking = Booking::with('client')
                ->findOrFail($bookingId);

            return inertia('admin/bookings/charge', [
                'booking' => [
                    'id' => $booking->id,
                    'total_amount' => $booking->total_amount,
                    'reimbursement' => $booking->reimbursement,
                    'tip' => $booking->tip,
                    'payment_status' => $booking->payment_status,
                    'client' => [
                        'full_name' => $booking->client->full_name,
                    ],
                    'service_type' => $booking->service_type,
                    'start_datetime' => $booking->start_datetime,
                ],
            ]);
        }

        return inertia('admin/bookings/charge');
    }
}
