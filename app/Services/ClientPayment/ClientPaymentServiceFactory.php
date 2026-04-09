<?php

namespace App\Services\ClientPayment;

use App\Services\ClientPayment\Contracts\ClientPaymentServiceInterface;

class ClientPaymentServiceFactory
{
    public function make(): ClientPaymentServiceInterface
    {
        return app(ClientPaymentService::class);
    }
}
