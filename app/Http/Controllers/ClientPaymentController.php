<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentMethodRequest;
use App\Services\ClientPayment\ClientPaymentServiceFactory;
use Illuminate\Http\Request;

class ClientPaymentController extends Controller
{
    protected $service;

    public function __construct(ClientPaymentServiceFactory $factory)
    {
        $this->service = $factory->make();
    }

    public function index(Request $request)
    {
        if ($request->has('session_id')) {
            $paymentMethodData = $this->service->retrieveSetupIntent(
                $request->query('session_id')
            );

            if ($paymentMethodData) {
                $this->service->storePaymentMethod($paymentMethodData);
            }
        }

        return $this->service->index();
    }

    public function getSetupIntent(Request $request)
    {
        return response()->json($this->service->createSetupIntent());
    }

    public function storePaymentMethod(StorePaymentMethodRequest $request)
    {
        $validated = $request->validated();

        return response()->json($this->service->storePaymentMethod($validated));
    }

    public function setDefault(Request $request, int $paymentMethodId)
    {
        return response()->json($this->service->setDefaultPaymentMethod($paymentMethodId));
    }

    public function destroy(int $paymentMethodId)
    {
        return response()->json($this->service->deletePaymentMethod($paymentMethodId));
    }
}
