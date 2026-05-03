<?php

namespace App\Services\ClientPayment;

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
use App\Services\ClientPayment\Contracts\ClientPaymentServiceInterface;
use Illuminate\Support\Facades\Auth;
use Inertia\Response as InertiaResponse;
use Stripe\PaymentMethod;
use Stripe\StripeClient;

class ClientPaymentService implements ClientPaymentServiceInterface
{
    protected StripeClient $stripe;

    protected ?Client $client = null;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    protected function getClient(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        $user = Auth::user();

        return $user->client;
    }

    public function index(): InertiaResponse
    {
        $client = $this->getClient();

        $payments = ClientPayment::where('client_id', $client->id)
            ->with(['booking', 'paymentMethod'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return inertia('client/payments/index', [
            'payments' => $payments,
            'paymentMethods' => $this->showPaymentMethods(),
        ]);
    }

    public function showPaymentMethods(): array
    {
        $client = $this->getClient();

        $methods = ClientPaymentMethod::where('client_id', $client->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $methods->map(fn ($method) => [
            'id' => $method->id,
            'brand' => $method->brand,
            'last4' => $method->last4,
            'exp_month' => $method->exp_month,
            'exp_year' => $method->exp_year,
            'is_default' => $method->is_default,
            'status' => $method->status,
        ])->toArray();
    }

    public function createSetupIntent(): array
    {
        $client = $this->getClient();

        if (! $client->stripe_customer_id) {
            $stripeCustomer = $this->stripe->customers->create([
                'email' => $client->user->email,
                'name' => $client->full_name,
            ]);

            $client->update(['stripe_customer_id' => $stripeCustomer->id]);
        }

        $checkoutSession = $this->stripe->checkout->sessions->create([
            'customer' => $client->stripe_customer_id,
            'ui_mode' => 'embedded_page',
            'mode' => 'setup',
            'payment_method_types' => ['card'],
            'return_url' => config('app.url').'/payments?session_id={CHECKOUT_SESSION_ID}',
        ]);

        return [
            'client_secret' => $checkoutSession->client_secret,
            'session_id' => $checkoutSession->id,
        ];
    }

    public function retrieveSetupIntent(string $sessionId): ?array
    {
        try {
            $session = $this->stripe->checkout->sessions->retrieve($sessionId);

            if ($session->status === 'complete' && $session->setup_intent) {
                $setupIntent = $this->stripe->setupIntents->retrieve($session->setup_intent);

                if ($setupIntent->status === 'succeeded' && $setupIntent->payment_method) {
                    $paymentMethod = $this->stripe->paymentMethods->retrieve(
                        $setupIntent->payment_method
                    );

                    return [
                        'payment_method_id' => $setupIntent->payment_method,
                        'brand' => $paymentMethod->card->brand,
                        'last4' => $paymentMethod->card->last4,
                        'exp_month' => $paymentMethod->card->exp_month,
                        'exp_year' => $paymentMethod->card->exp_year,
                        'metadata' => [
                            'stripe_payment_method_id' => $setupIntent->payment_method,
                            'funding' => $paymentMethod->card->funding ?? null,
                            'country' => $paymentMethod->card->country ?? null,
                            'card_expires_after' => $paymentMethod->card->expires_after ?? null,
                            'billing_name' => $paymentMethod->billing_details->name ?? null,
                            'billing_email' => $paymentMethod->billing_details->email ?? null,
                            'created' => $paymentMethod->created,
                            'type' => $paymentMethod->type,
                            'customer' => $paymentMethod->customer ?? null,
                        ],
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve setup intent: '.$e->getMessage());
        }

        return null;
    }

    public function retrievePaymentMethod(string $paymentMethodId): PaymentMethod
    {
        return $this->stripe->paymentMethods->retrieve($paymentMethodId);
    }

    public function storePaymentMethod(array $data): array
    {
        $client = $this->getClient();

        if (! $client->stripe_customer_id) {
            $stripeCustomer = $this->stripe->customers->create([
                'email' => $client->user->email,
                'name' => $client->full_name,
            ]);

            $client->update(['stripe_customer_id' => $stripeCustomer->id]);
        }

        $existingCount = ClientPaymentMethod::where('client_id', $client->id)->count();

        $paymentMethod = ClientPaymentMethod::create([
            'client_id' => $client->id,
            'provider' => 'stripe',
            'provider_method_id' => $data['payment_method_id'],
            'brand' => $data['brand'],
            'last4' => $data['last4'],
            'exp_month' => $data['exp_month'],
            'exp_year' => $data['exp_year'],
            'status' => 'active',
            'is_default' => $existingCount === 0,
            'metadata' => $data['metadata'] ?? [],
        ]);

        $this->stripe->paymentMethods->attach($data['payment_method_id'], [
            'customer' => $client->stripe_customer_id,
        ]);

        if ($paymentMethod->is_default) {
            $this->stripe->customers->update($client->stripe_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => $data['payment_method_id'],
                ],
            ]);
        }

        return [
            'id' => $paymentMethod->id,
            'brand' => $paymentMethod->brand,
            'last4' => $paymentMethod->last4,
            'is_default' => $paymentMethod->is_default,
        ];
    }

    public function setDefaultPaymentMethod(int $paymentMethodId): array
    {
        $client = $this->getClient();

        $paymentMethod = ClientPaymentMethod::where('client_id', $client->id)
            ->findOrFail($paymentMethodId);

        ClientPaymentMethod::where('client_id', $client->id)
            ->where('id', '!=', $paymentMethodId)
            ->update(['is_default' => false]);

        $paymentMethod->update(['is_default' => true]);

        $this->stripe->customers->update($client->stripe_customer_id, [
            'invoice_settings' => [
                'default_payment_method' => $paymentMethod->provider_method_id,
            ],
        ]);

        return [
            'success' => true,
            'message' => 'Default payment method updated',
        ];
    }

    public function deletePaymentMethod(int $paymentMethodId): array
    {
        $client = $this->getClient();

        $paymentMethod = ClientPaymentMethod::where('client_id', $client->id)
            ->findOrFail($paymentMethodId);

        $wasDefault = $paymentMethod->is_default;

        $this->stripe->paymentMethods->detach($paymentMethod->provider_method_id);

        $paymentMethod->delete();

        if ($wasDefault) {
            $newDefault = ClientPaymentMethod::where('client_id', $client->id)->first();
            if ($newDefault) {
                $this->setDefaultPaymentMethod($newDefault->id);
            }
        }

        return [
            'success' => true,
            'message' => 'Payment method removed',
        ];
    }
}
