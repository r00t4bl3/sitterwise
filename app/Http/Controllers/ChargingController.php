<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChargeBookingRequest;
use App\Models\Booking;
use App\Services\Billing\JobBillingService;
use App\Services\CaregiverPayout\CaregiverPayoutService;
use Illuminate\Http\JsonResponse;

class ChargingController extends Controller
{
    protected const PLATFORM_FEE = 1200;

    public function __construct(
        protected JobBillingService $billingService,
        protected CaregiverPayoutService $payoutService
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

        if (! $booking->caregiver_id) {
            return response()->json([
                'success' => false,
                'message' => 'This booking has no assigned caregiver.',
            ], 400);
        }

        $reimbursement = (int) ($validated['reimbursement'] ?? 0) * 100;
        $tip = (int) ($validated['tip'] ?? 0) * 100;
        $notes = $validated['notes'] ?? null;

        $booking->update([
            'reimbursement' => $reimbursement / 100,
            'tip' => $tip / 100,
            'admin_notes' => $notes
                ? $booking->admin_notes."\n".now()->toDateTimeString().': '.$notes
                : $booking->admin_notes,
        ]);

        $chargeResult = $this->billingService->charge($booking);

        if (! $chargeResult['success']) {
            return response()->json([
                'success' => false,
                'step' => 'charge_client',
                'message' => $chargeResult['message'],
            ], 422);
        }

        $caregiver = $booking->caregiver;
        $baseAmount = (int) ($booking->total_amount * 100);
        $grossPayout = $baseAmount + $reimbursement;
        $netPayout = $grossPayout - self::PLATFORM_FEE;

        $payoutResult = $this->payoutService->transferFunds(
            $caregiver,
            $netPayout
        );

        if (! $payoutResult['success']) {
            return response()->json([
                'success' => false,
                'step' => 'transfer',
                'message' => 'Client charged but caregiver payout failed: '.$payoutResult['message'],
                'client_charged' => true,
                'payment_intent_id' => $chargeResult['payment_intent_id'] ?? null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'step' => 'complete',
            'message' => 'Payment processed and caregiver paid',
            'client_amount' => $chargeResult['amount'],
            'caregiver_payout' => $netPayout / 100,
            'transfer_id' => $payoutResult['transfer_id'] ?? null,
            'payout_id' => $payoutResult['payout_id'] ?? null,
        ]);
    }

    public function calculateTotal(Booking $booking): JsonResponse
    {
        $total = $this->billingService->calculateTotal($booking);
        $baseAmount = (int) ($booking->total_amount * 100);
        $reimbursement = (int) (($booking->reimbursement ?? 0) * 100);
        $grossPayout = $baseAmount + $reimbursement;
        $netPayout = $grossPayout - self::PLATFORM_FEE;

        return response()->json([
            'base_amount' => $booking->total_amount,
            'reimbursement' => $booking->reimbursement ?? 0,
            'tip' => $booking->tip ?? 0,
            'total' => $total,
            'caregiver_gross' => $grossPayout / 100,
            'platform_fee' => self::PLATFORM_FEE / 100,
            'caregiver_net' => max(0, $netPayout / 100),
        ]);
    }
}
