<?php

namespace App\Http\Controllers;

use App\Services\Webhooks\StripeWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeWebhookHandler $handler): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        $result = $handler->handle($payload, $signature);

        return response()->json($result);
    }
}
