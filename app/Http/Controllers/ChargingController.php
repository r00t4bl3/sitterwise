<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChargeBookingRequest;
use App\Models\Booking;
use App\Services\Billing\JobBillingService;
use Illuminate\Http\JsonResponse;

class ChargingController extends Controller
{
    public function __construct(
        protected JobBillingService $billingService
    ) {}

    public function charge(ChargeBookingRequest $request, Booking $booking): JsonResponse
    {
        $validated = $request->validated();

        if ($booking->payment_status === 'charged' || $booking->payment_status === 'captured') {
            return response()->json([
                'success' => false,
                'message' => 'This booking has already been charged.',
            ], 400);
        }

        if (! $booking->requires_payment) {
            return response()->json([
                'success' => false,
                'message' => 'This booking does not require payment.',
            ], 400);
        }

        $reimbursement = $validated['reimbursement'] ?? 0;
        $tip = $validated['tip'] ?? 0;
        $notes = $validated['notes'] ?? null;

        $booking->update([
            'reimbursement' => $reimbursement,
            'tip' => $tip,
            'admin_notes' => $notes ? $booking->admin_notes."\n".now()->toDateTimeString().': '.$notes : $booking->admin_notes,
        ]);

        $result = $this->billingService->charge($booking);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function calculateTotal(Booking $booking): JsonResponse
    {
        $total = $this->billingService->calculateTotal($booking);

        return response()->json([
            'base_amount' => $booking->total_amount,
            'reimbursement' => $booking->reimbursement ?? 0,
            'tip' => $booking->tip ?? 0,
            'total' => $total,
        ]);
    }
}
