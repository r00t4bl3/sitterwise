<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChargeBookingRequest;
use App\Models\Booking;
use App\Models\PricingRule;
use App\Services\Billing\JobBillingService;
use App\Services\CaregiverPayout\CaregiverPayoutService;
use Illuminate\Http\JsonResponse;

class ChargingController extends Controller
{
    public function __construct(
        protected JobBillingService $billingService,
        protected CaregiverPayoutService $payoutService
    ) {}

    public function charge(ChargeBookingRequest $request, Booking $booking): JsonResponse
    {
        $validated = $request->validated();

        if ($booking->paymentSettled()) {
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

        if ($booking->payment_form !== PricingRule::PAYMENT_FORM_STRIPE) {
            return response()->json([
                'success' => false,
                'message' => 'This booking is settled via '.($booking->payment_form ?? 'another method').', not a card charge.',
            ], 400);
        }

        if (! $booking->caregiver_id) {
            return response()->json([
                'success' => false,
                'message' => 'This booking has no assigned caregiver.',
            ], 400);
        }

        $reimbursement = (float) ($validated['reimbursement'] ?? 0);
        $tip = (float) ($validated['tip'] ?? 0);
        $notes = $validated['notes'] ?? null;

        $booking->update([
            'reimbursement' => $reimbursement,
            'tip' => $tip,
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

        if (config('services.stripe.enable_caregiver_transfers')) {
            $caregiver = $booking->caregiver;
            $transferAmount = (int) round(
                ($booking->paid_to_caregiver_total - ($booking->tip ?? 0)) * 100
            );

            $payoutResult = $this->payoutService->transferFunds(
                $caregiver,
                $transferAmount,
                idempotencyKey: "booking_{$booking->id}_payout_{$transferAmount}"
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
        }

        return response()->json([
            'success' => true,
            'step' => 'complete',
            'message' => 'Payment processed',
            'client_amount' => $chargeResult['amount'],
        ]);
    }

    public function calculateTotal(Booking $booking): JsonResponse
    {
        return response()->json([
            'charge_to_client' => $booking->charge_to_client,
            'reimbursement' => $booking->reimbursement ?? 0,
            'bonus' => $booking->bonus ?? 0,
            'tip' => $booking->tip ?? 0,
            'total_service_amount' => $booking->total_service_amount,
            'total_amount' => $booking->total_amount,
            'paid_to_caregiver' => $booking->paid_to_caregiver,
            'sitterwise_cut' => $booking->sitterwise_cut,
            'paid_to_caregiver_total' => $booking->paid_to_caregiver_total,
        ]);
    }
}
