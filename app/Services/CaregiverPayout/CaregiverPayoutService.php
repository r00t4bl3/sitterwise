<?php

namespace App\Services\CaregiverPayout;

use App\Models\Caregiver;
use App\Models\CaregiverPayout;
use App\Models\CaregiverPayoutMethod;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class CaregiverPayoutService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function createConnectAccount(Caregiver $caregiver): string
    {
        $account = $this->stripe->accounts->create([
            'type' => 'express',
            'country' => 'US',
            'business_type' => 'individual',
            'individual' => [
                'first_name' => $caregiver->first_name,
                'last_name' => $caregiver->last_name,
                'email' => $caregiver->user->email,
                'phone' => $caregiver->phone,
                'address' => [
                    'line1' => $caregiver->address_line1 ?? '',
                    'line2' => $caregiver->address_line2 ?? '',
                    'city' => $caregiver->address_city ?? '',
                    'state' => $caregiver->address_state ?? '',
                    'postal_code' => $caregiver->address_zip ?? '',
                ],
            ],
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
        ]);

        $caregiver->update(['stripe_account_id' => $account->id]);

        return $account->id;
    }

    public function createAccountLink(Caregiver $caregiver): array
    {
        if (! $caregiver->stripe_account_id) {
            $this->createConnectAccount($caregiver);
        }

        $accountLink = $this->stripe->accountLinks->create([
            'account' => $caregiver->stripe_account_id,
            'refresh_url' => config('app.url').'/payouts/stripe/refresh?caregiver_id='.$caregiver->id,
            'return_url' => config('app.url').'/payouts/stripe/return?caregiver_id='.$caregiver->id,
            'type' => 'account_onboarding',
        ]);

        return [
            'url' => $accountLink->url,
        ];
    }

    public function getAccountStatus(Caregiver $caregiver): array
    {
        if (! $caregiver->stripe_account_id) {
            return [
                'connected' => false,
                'status' => null,
                'details_submitted' => false,
                'charges_enabled' => false,
                'payouts_enabled' => false,
            ];
        }

        $account = $this->stripe->accounts->retrieve($caregiver->stripe_account_id);

        return [
            'connected' => true,
            'status' => $account->charges_enabled ? 'active' : 'pending',
            'details_submitted' => $account->details_submitted ?? false,
            'charges_enabled' => $account->charges_enabled ?? false,
            'payouts_enabled' => $account->payouts_enabled ?? false,
            'requirements' => $account->requirements ?? null,
        ];
    }

    public function handleStripeReturn(Caregiver $caregiver): void
    {
        if ($caregiver->stripe_account_id) {
            $status = $this->getAccountStatus($caregiver);

            if ($status['charges_enabled'] && $status['payouts_enabled']) {
                $this->syncPayoutMethods($caregiver);
            }
        }
    }

    protected function syncPayoutMethods(Caregiver $caregiver): void
    {
        try {
            $account = $this->stripe->accounts->retrieve($caregiver->stripe_account_id, [
                'expand' => ['external_accounts'],
            ]);
            $externalAccounts = $account->external_accounts->data ?? [];
        } catch (\Exception $e) {
            return;
        }

        foreach ($externalAccounts as $bankAccount) {
            $existingMethod = CaregiverPayoutMethod::where('caregiver_id', $caregiver->id)
                ->where('provider_method_id', $bankAccount->id)
                ->first();

            if (! $existingMethod) {
                CaregiverPayoutMethod::create([
                    'caregiver_id' => $caregiver->id,
                    'provider' => 'stripe_connect',
                    'provider_method_id' => $bankAccount->id,
                    'account_type' => $bankAccount->object ?? 'bank_account',
                    'bank_name' => $bankAccount->bank_name ?? 'Bank Account',
                    'last4' => $bankAccount->last4 ?? '',
                    'status' => 'active',
                    'is_default' => CaregiverPayoutMethod::where('caregiver_id', $caregiver->id)->count() === 0,
                ]);
            }
        }
    }

    public function getPayoutMethods(Caregiver $caregiver): array
    {
        return CaregiverPayoutMethod::where('caregiver_id', $caregiver->id)
            ->orderBy('is_default', 'desc')
            ->get()
            ->map(fn ($method) => [
                'id' => $method->id,
                'bank_name' => $method->bank_name,
                'last4' => $method->last4,
                'account_type' => $method->account_type,
                'is_default' => $method->is_default,
                'status' => $method->status,
            ])
            ->toArray();
    }

    public function getPayoutHistory(Caregiver $caregiver)
    {
        return $caregiver->payouts()
            ->with('payoutMethod')
            ->orderBy('payout_date', 'desc')
            ->paginate(10);
    }

    public function transferFunds(Caregiver $caregiver, int $amount, ?int $payoutMethodId = null, ?string $idempotencyKey = null): array
    {
        $payoutMethod = $payoutMethodId
            ? CaregiverPayoutMethod::where('id', $payoutMethodId)
                ->where('caregiver_id', $caregiver->id)
                ->first()
            : CaregiverPayoutMethod::where('caregiver_id', $caregiver->id)
                ->where('is_default', true)
                ->where('status', 'active')
                ->first();

        if (! $payoutMethod) {
            return [
                'success' => false,
                'message' => 'No active payout method found for caregiver',
            ];
        }

        if (! $caregiver->stripe_account_id) {
            return [
                'success' => false,
                'message' => 'Caregiver does not have a Stripe connected account',
            ];
        }

        $amountInCents = $amount;

        try {
            $transfer = $this->stripe->transfers->create([
                'amount' => $amountInCents,
                'currency' => 'usd',
                'destination' => $caregiver->stripe_account_id,
                'transfer_group' => "booking_payout_{$caregiver->id}",
                'metadata' => [
                    'caregiver_id' => $caregiver->id,
                    'payout_method_id' => $payoutMethod->id,
                ],
            ], $idempotencyKey ? ['idempotency_key' => $idempotencyKey] : []);

            $payout = CaregiverPayout::create([
                'caregiver_id' => $caregiver->id,
                'caregiver_payout_method_id' => $payoutMethod->id,
                'amount' => $amount / 100,
                'currency' => 'usd',
                'status' => 'paid',
                'provider_transfer_id' => $transfer->id,
                'payout_date' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'Payout successful',
                'transfer_id' => $transfer->id,
                'payout_id' => $payout->id,
            ];
        } catch (ApiErrorException $e) {
            CaregiverPayout::create([
                'caregiver_id' => $caregiver->id,
                'caregiver_payout_method_id' => $payoutMethod->id,
                'amount' => $amount / 100,
                'currency' => 'usd',
                'status' => 'failed',
                'provider_transfer_id' => null,
                'payout_date' => now(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
