<?php

namespace App\Http\Controllers;

use App\Http\Requests\CaregiverConnectStripeRequest;
use App\Models\Caregiver;
use App\Services\CaregiverPayout\CaregiverPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CaregiverPayoutController extends Controller
{
    public function __construct(
        protected CaregiverPayoutService $payoutService
    ) {}

    public function index()
    {
        $caregiver = Auth::user()->caregiver;
        $stripeStatus = $this->payoutService->getAccountStatus($caregiver);
        $payoutMethods = $this->payoutService->getPayoutMethods($caregiver);
        $payouts = $this->payoutService->getPayoutHistory($caregiver);

        return Inertia::render('caregiver/payouts/index', [
            'stripeStatus' => $stripeStatus,
            'payoutMethods' => $payoutMethods,
            'payouts' => $payouts,
        ]);
    }

    public function connect(CaregiverConnectStripeRequest $request): JsonResponse
    {
        $caregiver = $request->caregiver();

        if ($caregiver->stripe_account_id) {
            return response()->json([
                'error' => 'Account already connected',
            ], 400);
        }

        try {
            $accountId = $this->payoutService->createConnectAccount($caregiver);

            return response()->json([
                'success' => true,
                'stripe_account_id' => $accountId,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create Stripe account: '.$e->getMessage(),
            ], 500);
        }
    }

    public function onboarding()
    {
        $caregiver = Auth::user()->caregiver;

        try {
            $accountLink = $this->payoutService->createAccountLink($caregiver);

            return Inertia::render('caregiver/payouts/index', [
                'url' => $accountLink['url'],
            ]);
        } catch (\Exception $e) {
            return redirect('/payouts')->with('error', 'Failed to create account link: '.$e->getMessage());
        }
    }

    public function status()
    {
        $caregiver = Auth::user()->caregiver;
        $stripeStatus = $this->payoutService->getAccountStatus($caregiver);

        return inertia('caregiver/payouts/index', [
            'stripeStatus' => $stripeStatus,
            'payoutMethods' => $this->payoutService->getPayoutMethods($caregiver),
        ]);
    }

    public function return()
    {
        $caregiverId = request()->query('caregiver_id');

        if (! $caregiverId) {
            return redirect('/payouts')->with('error', 'Invalid return URL');
        }

        $caregiver = Caregiver::find($caregiverId);

        if (! $caregiver || $caregiver->user_id !== Auth::id()) {
            return redirect('/payouts')->with('error', 'Unauthorized');
        }

        try {
            $this->payoutService->handleStripeReturn($caregiver);

            return Inertia::render('caregiver/payouts/return', [
                'success' => true,
            ]);
        } catch (\Exception $e) {
            return Inertia::render('caregiver/payouts/return', [
                'success' => false,
            ]);
        }
    }

    public function refresh()
    {
        $caregiverId = request()->query('caregiver_id');

        if (! $caregiverId) {
            return redirect('/payouts')->with('error', 'Invalid refresh URL');
        }

        $caregiver = Caregiver::find($caregiverId);

        if (! $caregiver || $caregiver->user_id !== Auth::id()) {
            return redirect('/payouts')->with('error', 'Unauthorized');
        }

        return Inertia::render('caregiver/payouts/refresh');
    }
}
