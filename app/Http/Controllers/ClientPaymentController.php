<?php

namespace App\Http\Controllers;

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

    public function storePaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
            'brand' => 'required|string',
            'last4' => 'required|string',
            'exp_month' => 'required|integer',
            'exp_year' => 'required|integer',
        ]);

        return response()->json($this->service->storePaymentMethod($request->all()));
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
