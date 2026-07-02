<?php

namespace App\Services\ClientPayment;

use App\Enums\BookingPaymentStatus;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\ClientGroupBookingCreatedNotification;
use App\Services\ClientPayment\Contracts\ClientPaymentServiceInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
            ->orderBy('paid_at', 'desc')
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

        if ($methods->isEmpty() && $client->stripe_customer_id) {
            return $this->syncPaymentMethodsFromStripe($client);
        }

        return $this->formatMethods($methods);
    }

    public function syncPaymentMethodsFromStripe(?Client $client = null): array
    {
        $client = $client ?? $this->getClient();

        if (! $client->stripe_customer_id) {
            return [];
        }

        try {
            $stripeMethods = $this->stripe->customers->allPaymentMethods(
                $client->stripe_customer_id,
                ['type' => 'card']
            );
        } catch (\Exception $e) {
            Log::error('Failed to fetch payment methods from Stripe', [
                'client_id' => $client->id,
                'stripe_customer_id' => $client->stripe_customer_id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $hasDefault = ClientPaymentMethod::where('client_id', $client->id)
            ->where('is_default', true)
            ->exists();

        $synced = [];

        foreach ($stripeMethods as $pm) {
            $card = $pm->card ?? null;

            $method = ClientPaymentMethod::updateOrCreate(
                ['provider_method_id' => $pm->id],
                [
                    'client_id' => $client->id,
                    'provider' => 'stripe',
                    'brand' => $card->brand ?? 'unknown',
                    'last4' => $card->last4 ?? '****',
                    'exp_month' => $card->exp_month ?? 1,
                    'exp_year' => $card->exp_year ?? 2025,
                    'status' => 'active',
                    'is_default' => false,
                ]
            );

            if (! $hasDefault && empty($synced)) {
                $method->update(['is_default' => true]);

                try {
                    $this->stripe->customers->update($client->stripe_customer_id, [
                        'invoice_settings' => [
                            'default_payment_method' => $pm->id,
                        ],
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to update Stripe default payment method', [
                        'client_id' => $client->id,
                        'payment_method_id' => $pm->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $synced[] = $method;
        }

        Log::info('Synced payment methods from Stripe', [
            'client_id' => $client->id,
            'count' => count($synced),
        ]);

        return $this->formatMethods(collect($synced));
    }

    protected function formatMethods($methods): array
    {
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

    public function createSetupIntent(?string $returnUrl = null): array
    {
        $client = $this->getClient();

        $this->ensureStripeCustomer($client);

        $checkoutSession = $this->stripe->checkout->sessions->create([
            'customer' => $client->stripe_customer_id,
            'ui_mode' => 'embedded_page',
            'mode' => 'setup',
            'payment_method_types' => ['card'],
            'return_url' => $returnUrl ?? config('app.url').'/payments?session_id={CHECKOUT_SESSION_ID}',
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

        $this->ensureStripeCustomer($client);

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

        $this->sendDeferredBookingNotifications($client);

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

        $this->ensureStripeCustomer($client);

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

    protected function sendDeferredBookingNotifications(Client $client): void
    {
        $user = $client->user;

        if (! $user) {
            return;
        }

        $pendingBookings = Booking::whereHas('bookingGroup', fn ($q) => $q
            ->where('client_id', $client->id)
            ->where('requires_payment', true))
            ->where('payment_status', BookingPaymentStatus::Pending->value)
            ->get();

        foreach ($pendingBookings as $booking) {
            $user->notify(new BookingCreatedNotification($booking));
        }

        $pendingGroups = BookingGroup::where('client_id', $client->id)
            ->where('requires_payment', true)
            ->has('bookings', '>', 1)
            ->get();

        foreach ($pendingGroups as $group) {
            $user->notify(new ClientGroupBookingCreatedNotification($group));
        }
    }

    protected function ensureStripeCustomer(Client $client): void
    {
        if ($client->stripe_customer_id && str_starts_with($client->stripe_customer_id, 'pm_')) {
            $client->update(['stripe_customer_id' => null]);
        }

        if (! $client->stripe_customer_id) {
            $stripeCustomer = $this->stripe->customers->create([
                'email' => $client->user->email,
                'name' => $client->full_name,
            ]);

            $client->update(['stripe_customer_id' => $stripeCustomer->id]);
        }
    }
}
