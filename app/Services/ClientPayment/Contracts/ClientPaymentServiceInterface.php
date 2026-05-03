<?php

namespace App\Services\ClientPayment\Contracts;

use Inertia\Response as InertiaResponse;
use Stripe\PaymentMethod;

interface ClientPaymentServiceInterface
{
    public function index(): InertiaResponse;

    public function showPaymentMethods(): array;

    public function createSetupIntent(): array;

    public function retrieveSetupIntent(string $sessionId): ?array;

    public function retrievePaymentMethod(string $paymentMethodId): PaymentMethod;

    public function storePaymentMethod(array $data): array;

    public function setDefaultPaymentMethod(int $paymentMethodId): array;

    public function deletePaymentMethod(int $paymentMethodId): array;
}
