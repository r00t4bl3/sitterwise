# Sitterwise — Stripe “Charge After Job” Implementation Guide (Laravel)

## Purpose

This guide defines the exact implementation for a **charge-after-service** model using Stripe:

- Save client card upfront (no charge)
- Charge off-session after job completion
- Handle failures, retries, and webhooks
- Ensure safe payout timing

---

# 1. High-Level Flow

## Booking Phase (Before Job)

1. Create Stripe Customer
2. Create SetupIntent
3. Collect & save payment method

## Completion Phase (After Job)

4. Calculate final amount
5. Charge off-session (PaymentIntent)
6. Handle success / failure

## Post-Charge

7. Retry if needed
8. Notify client
9. Pay caregiver (only after success)

---

# 2. Database Requirements

## CLIENT

```text
stripe_customer_id (string, nullable)
```

## PAYMENT_METHOD

```text
owner_type = client
owner_id
provider = stripe
type = credit_card
is_default (boolean)
metadata (json: stripe_payment_method_id, brand, last4)
```

## JOB (add fields)

```text
payment_status (pending | charged | failed)
stripe_payment_intent_id (string, nullable)
actual_amount (decimal)
charge_attempt_count (int, default 0)
last_charge_attempt_at (timestamp, nullable)
```

---

# 3. Booking Phase Implementation

---

## Step 1 — Ensure Stripe Customer

### Service: `ClientPaymentProfileService`

```php
class ClientPaymentProfileService
{
    public function ensureCustomer(Client $client): string
    {
        if ($client->stripe_customer_id) {
            return $client->stripe_customer_id;
        }

        $customer = \Stripe\Customer::create([
            'email' => $client->email,
            'name' => $client->first_name . ' ' . $client->last_name,
        ]);

        $client->update([
            'stripe_customer_id' => $customer->id,
        ]);

        return $customer->id;
    }
}
```

---

## Step 2 — Create SetupIntent

### Controller

```php
public function createSetupIntent(Request $request)
{
    $client = auth()->user();

    $customerId = app(ClientPaymentProfileService::class)
        ->ensureCustomer($client);

    $intent = \Stripe\SetupIntent::create([
        'customer' => $customerId,
        'usage' => 'off_session',
    ]);

    return response()->json([
        'client_secret' => $intent->client_secret,
    ]);
}
```

---

## Step 3 — Frontend (Stripe Elements)

- Use Stripe.js + Elements
- Confirm SetupIntent using `client_secret`

Result:

```text
payment_method_id (pm_xxx)
```

---

## Step 4 — Store Payment Method

```php
PaymentMethod::create([
    'owner_type' => 'client',
    'owner_id' => $client->id,
    'provider' => 'stripe',
    'type' => 'credit_card',
    'is_default' => true,
    'metadata' => [
        'stripe_payment_method_id' => $pmId,
        'brand' => $brand,
        'last4' => $last4,
    ],
]);
```

---

## Step 5 — Attach Payment Method to Customer

```php
\Stripe\PaymentMethod::attach($pmId, [
    'customer' => $customerId,
]);
```

---

## Step 6 — Set Default Payment Method in Stripe

```php
\Stripe\Customer::update($customerId, [
    'invoice_settings' => [
        'default_payment_method' => $pmId,
    ],
]);
```

---

# 4. Charge After Job (Core Logic)

---

## Service: `JobBillingService`

```php
class JobBillingService
{
    public function charge(Job $job)
    {
        if ($job->payment_status === 'charged') {
            return;
        }

        $client = $job->client;

        $paymentMethod = PaymentMethod::where('owner_type', 'client')
            ->where('owner_id', $client->id)
            ->where('is_default', true)
            ->firstOrFail();

        $amount = app(JobPricingService::class)->calculateFinal($job);

        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $amount,
                'currency' => 'usd',
                'customer' => $client->stripe_customer_id,
                'payment_method' => $paymentMethod->metadata['stripe_payment_method_id'],
                'off_session' => true,
                'confirm' => true,
            ]);

            $job->update([
                'payment_status' => 'charged',
                'stripe_payment_intent_id' => $intent->id,
                'actual_amount' => $amount,
                'last_charge_attempt_at' => now(),
            ]);

        } catch (\Stripe\Exception\CardException $e) {

            $job->increment('charge_attempt_count');

            $job->update([
                'payment_status' => 'failed',
                'last_charge_attempt_at' => now(),
            ]);

            app(PaymentFailureHandler::class)->handle($job, $e);
        }
    }
}
```

---

# 5. Payment Failure Handling

---

## Service: `PaymentFailureHandler`

```php
class PaymentFailureHandler
{
    public function handle(Job $job, $exception)
    {
        $code = $exception->getError()->code;

        if ($code === 'authentication_required') {
            // Notify client to complete payment
        }

        if ($code === 'card_declined') {
            // Ask client to update card
        }

        dispatch(new RetryJobCharge($job))
            ->delay(now()->addHour());
    }
}
```

---

# 6. Retry Logic

---

## Queue Job: `RetryJobCharge`

```php
class RetryJobCharge implements ShouldQueue
{
    public function __construct(public Job $job) {}

    public function handle()
    {
        if ($this->job->payment_status === 'charged') {
            return;
        }

        app(JobBillingService::class)->charge($this->job);
    }
}
```

---

## Recommended Retry Schedule

- Immediately
- +1 hour
- +24 hours
- +3 days

---

# 7. Webhook Handling (CRITICAL)

---

## Route

```php
Route::post('/webhooks/stripe', StripeWebhookController::class);
```

---

## Controller

```php
class StripeWebhookController
{
    public function __invoke(Request $request)
    {
        app(StripeWebhookHandler::class)->handle($request);
    }
}
```

---

## Handler

```php
class StripeWebhookHandler
{
    public function handle($request)
    {
        $event = \Stripe\Webhook::constructEvent(
            $request->getContent(),
            $request->header('Stripe-Signature'),
            config('services.stripe.webhook_secret')
        );

        switch ($event->type) {

            case 'payment_intent.succeeded':
                $this->handleSuccess($event->data->object);
                break;

            case 'payment_intent.payment_failed':
                $this->handleFailure($event->data->object);
                break;
        }
    }
}
```

---

# 8. Payout Rule (IMPORTANT)

Only pay caregiver when:

```text
JOB.payment_status = charged
```

---

# 9. Notifications

Trigger notifications when:

## Payment Failed

- Email: “Please update your payment method”

## Authentication Required

- Provide link to complete payment

---

# 10. Security & Reliability Rules

- Always use webhooks as source of truth
- Never trust frontend success
- Use idempotency keys for charges
- Log all Stripe responses
- Never store raw card data

---

# 11. Final Checklist

✅ SetupIntent implemented
✅ Payment method stored
✅ Off-session charging works
✅ Retry system implemented
✅ Webhooks configured
✅ Payout delayed until success

---

# Final Notes

This model is:

- Flexible (final pricing after job)
- Scalable (multi-provider ready)
- Risk-aware (retry + failure handling)

But requires strict handling of:

- failed payments
- user notifications
- payout timing

---
