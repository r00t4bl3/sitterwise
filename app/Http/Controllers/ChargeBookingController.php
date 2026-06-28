<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CaregiverPayoutMethod;
use Illuminate\Http\Request;

class ChargeBookingController extends Controller
{
    public function create(Request $request)
    {
        $bookingId = $request->query('booking_id');

        if ($bookingId) {
            $booking = Booking::with(['client', 'caregiver'])
                ->findOrFail($bookingId);

            $payoutMethods = [];
            $defaultPayoutMethod = null;

            if ($booking->caregiver) {
                $payoutMethods = CaregiverPayoutMethod::where('caregiver_id', $booking->caregiver_id)
                    ->where('status', 'active')
                    ->orderBy('is_default', 'desc')
                    ->get()
                    ->map(fn ($method) => [
                        'id' => $method->id,
                        'bank_name' => $method->bank_name,
                        'last4' => $method->last4,
                        'is_default' => $method->is_default,
                    ])
                    ->toArray();

                $defaultPayoutMethod = collect($payoutMethods)->firstWhere('is_default', true);
            }

            return inertia('admin/bookings/charge', [
                'booking' => [
                    'id' => $booking->id,
                    'total_amount' => $booking->total_amount,
                    'reimbursement' => $booking->reimbursement,
                    'tip' => $booking->tip,
                    'bonus' => $booking->bonus,
                    'payment_status' => $booking->payment_status,
                    'charge_to_client' => $booking->charge_to_client,
                    'paid_to_caregiver' => $booking->paid_to_caregiver,
                    'sitterwise_cut' => $booking->sitterwise_cut,
                    'paid_to_caregiver_total' => $booking->paid_to_caregiver_total,
                    'total_service_amount' => $booking->total_service_amount,
                    'client' => [
                        'full_name' => $booking->client->full_name,
                    ],
                    'caregiver' => $booking->caregiver
                        ? [
                            'id' => $booking->caregiver->id,
                            'name' => $booking->caregiver->full_name,
                        ]
                        : null,
                    'service_type' => $booking->service_type,
                    'start_datetime' => $booking->start_datetime,
                ],
                'payout_methods' => $payoutMethods,
                'default_payout_method' => $defaultPayoutMethod,
            ]);
        }

        return inertia('admin/bookings/charge');
    }
}
