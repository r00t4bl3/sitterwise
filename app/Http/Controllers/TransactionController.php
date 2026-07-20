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
            }, 'client.user', 'caregiver', 'payments', 'hotel'])
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
            ->orderBy('start_datetime', 'desc')
            ->paginate(10)
            ->through(function ($booking) {
                return [
                    'id' => $booking->id,
                    'start_datetime' => $booking->start_datetime,
                    'end_datetime' => $booking->end_datetime,
                    'total_price' => $booking->total_amount,
                    'status' => $booking->status,
                    'payment_status' => $booking->payment_status,
                    'charge' => $this->chargeSummary($booking),
                    'payment_form' => $booking->payment_form,
                    'requires_payment' => $booking->requires_payment,
                    'checkout_at' => $booking->checkout_at,
                    'total_working_hour' => $booking->total_working_hour,
                    'children' => $booking->children,
                    'pets' => $booking->pets,
                    'service_type' => $booking->service_type,
                    'service_type_label' => $booking->service_type_label,
                    'reimbursement' => $booking->reimbursement,
                    'reimbursement_description' => $booking->reimbursement_description,
                    'tip' => $booking->tip,
                    'bonus' => $booking->bonus,
                    'paid_to_caregiver' => $booking->paid_to_caregiver,
                    'sitterwise_cut' => $booking->sitterwise_cut,
                    'lifesaver_bonus' => $booking->lifesaver_bonus,
                    'charge_to_client' => $booking->charge_to_client,
                    'charge_to_client_hourly' => $booking->charge_to_client_hourly,
                    'paid_to_caregiver_hourly' => $booking->paid_to_caregiver_hourly,
                    'sitterwise_cut_hourly' => $booking->sitterwise_cut_hourly,
                    'hotel_fee' => $booking->hotel_fee,
                    'location_type' => $booking->location_type,
                    'hotel' => $booking->hotel ? [
                        'id' => $booking->hotel->id,
                        'name' => $booking->hotel->name,
                        'line1' => $booking->hotel->line1,
                        'line2' => $booking->hotel->line2,
                        'city' => $booking->hotel->city,
                        'state' => $booking->hotel->state,
                        'zip' => $booking->hotel->zip,
                        'parking_instructions' => $booking->hotel->parking_instructions,
                        'resort_fee' => $booking->hotel->resort_fee,
                        'contact_name' => $booking->hotel->contact_name,
                        'contact_phone' => $booking->hotel->contact_phone,
                    ] : null,
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

    /**
     * Summarize the booking's charge outcome, keeping the service charge and the
     * (separately-charged) tip distinct so a failed service charge is never
     * masked by a succeeded tip.
     *
     * @return array{service_state: string, service_error: ?string, attempt_count: int, last_attempt_at: mixed, tip_state: ?string, tip_amount: ?float}
     */
    private function chargeSummary(Booking $booking): array
    {
        $isTip = fn ($payment): bool => ($payment->metadata['type'] ?? null) === 'tip';

        $latestService = $booking->payments->reject($isTip)->sortByDesc('created_at')->first();
        $latestTip = $booking->payments->filter($isTip)->sortByDesc('created_at')->first();

        $serviceState = $this->serviceChargeState($booking);

        return [
            'service_state' => $serviceState,
            'service_error' => $serviceState === 'failed' ? $latestService?->error_message : null,
            'attempt_count' => (int) ($booking->charge_attempt_count ?? 0),
            'last_attempt_at' => $booking->last_charge_attempt_at,
            'tip_state' => $latestTip?->status,
            'tip_amount' => $latestTip ? (float) $latestTip->amount : null,
        ];
    }

    /**
     * Normalize the service charge's runtime payment_status vocabulary
     * (charged/charging/failed/...) into a display state.
     */
    private function serviceChargeState(Booking $booking): string
    {
        if ($booking->paymentSettled()
            || $booking->status === BookingStatus::Paid->value) {
            return 'succeeded';
        }

        return match ($booking->payment_status) {
            'failed' => 'failed',
            'charging' => 'processing',
            'refunded' => 'refunded',
            'disputed' => 'disputed',
            default => 'pending',
        };
    }
}
