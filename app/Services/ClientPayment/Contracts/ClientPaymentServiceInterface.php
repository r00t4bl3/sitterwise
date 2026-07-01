<?php

namespace App\Services\ClientPayment\Contracts;

use App\Models\Client;
use Inertia\Response as InertiaResponse;
use Stripe\PaymentMethod;

interface ClientPaymentServiceInterface
{
    public function index(): InertiaResponse;

    public function showPaymentMethods(): array;

    public function createSetupIntent(?string $returnUrl = null): array;

    public function retrieveSetupIntent(string $sessionId): ?array;

    public function retrievePaymentMethod(string $paymentMethodId): PaymentMethod;

    public function storePaymentMethod(array $data): array;

    public function setDefaultPaymentMethod(int $paymentMethodId): array;

    public function deletePaymentMethod(int $paymentMethodId): array;

    public function syncPaymentMethodsFromStripe(?Client $client = null): array;
}
