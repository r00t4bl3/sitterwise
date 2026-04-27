<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $bookings = Booking::query()
            ->whereIn('status', ['completed', 'paid'])
            ->with(['client' => function ($query) {
                $query->withExists(['paymentMethods as has_active_payment_method' => function ($query) {
                    $query->where('status', 'active');
                }]);
            }, 'client.user', 'caregiver'])
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('caregiver', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->through(function ($booking) {
                return [
                    'id' => $booking->id,
                    'start_datetime' => $booking->start_datetime,
                    'end_datetime' => $booking->end_datetime,
                    'total_price' => $booking->total_amount,
                    'status' => $booking->status,
                    'checkout_at' => $booking->checkout_at,
                    'total_working_hour' => $booking->total_working_hour,
                    'children' => $booking->children,
                    'pets' => $booking->pets,
                    'service_type' => $booking->service_type,
                    'reimbursement' => $booking->reimbursement,
                    'reimbursement_description' => $booking->reimbursement_description,
                    'tip' => $booking->tip,
                    'bonus' => $booking->bonus,
                    'paid_to_caregiver' => $booking->paid_to_caregiver,
                    'sitterwise_cut' => $booking->sitterwise_cut,
                    'charge_to_client' => $booking->charge_to_client,
                    'charge_to_client_hourly' => $booking->charge_to_client_hourly,
                    'paid_to_caregiver_hourly' => $booking->paid_to_caregiver_hourly,
                    'sitterwise_cut_hourly' => $booking->sitterwise_cut_hourly,
                    'client' => $booking->client ? [
                        'id' => $booking->client->id,
                        'first_name' => $booking->client->first_name,
                        'last_name' => $booking->client->last_name,
                        'user' => ['email' => $booking->client->user?->email],
                        'has_active_payment_method' => (bool) $booking->client->has_active_payment_method,
                    ] : null,
                    'caregiver' => $booking->caregiver ? [
                        'id' => $booking->caregiver->id,
                        'first_name' => $booking->caregiver->first_name,
                        'last_name' => $booking->caregiver->last_name,
                    ] : null,
                ];
            })
            ->withQueryString();

        return Inertia::render('admin/transactions/index', [
            'bookings' => $bookings,
            'filters' => [
                'search' => $search,
            ],
            'booking_statuses' => array_map(
                fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->label(),
                    'colors' => $case->colors(),
                ],
                BookingStatus::cases()
            ),
        ]);
    }
}
