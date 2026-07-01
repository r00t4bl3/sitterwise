# Admin Charging Flow — Full Process Documentation

## Overview

When an admin charges a client for a completed booking, the flow spans backend services, Stripe API, database records, webhooks, notifications, and retry logic.

---

## Step-by-Step Flow

### 1. Admin Initiates Charge

There are **two UI paths**:

**Path A — Transactions Page** (active, primary)
- `resources/js/pages/admin/transactions/index.tsx`
- Admin clicks a completed booking → opens payment sheet
- Can adjust: `checkout_at`, `total_working_hour`, `reimbursement`, `reimbursement_description`, `tip`, `bonus`
- Hourly rates × working hours recalculate totals in real-time
- On submit → `POST /bookings/{id}/process-payment` → `AdminBookingService::processPayment`

**Path B — Charge Booking Page** (dormant alternative)
- `resources/js/pages/admin/bookings/charge.tsx`
- Route: `GET /admin/bookings/charge?booking_id=X` → `ChargeBookingController::create`
- Links from the bookings index page are currently hidden (`{false && ...}`) pending future workflow decision
- Sends `POST /admin/bookings/{booking}/charge` with `reimbursement`, `tip`, `notes` → `ChargingController::charge`

### 2. Booking Adjustment & Charge (`AdminBookingService::processPayment`)

**File**: `app/Services/Booking/AdminBookingService.php:1293`

1. Validates input: `checkout_at`, `total_working_hour`, `reimbursement`, `reimbursement_description`, `tip`, `bonus`
2. Updates the `Booking` record with adjusted values
3. The `Booking` model's `updating` boot hook triggers `calculateTotalWorkingHours()` and `calculateTotalAmount()` → recalculates `total_amount`, `charge_to_client`, `paid_to_caregiver`, `sitterwise_cut` based on `PricingRule` rates
4. Delegates to `JobBillingService::charge($booking)` for the actual Stripe charge
5. If `config('services.stripe.enable_caregiver_transfers')` is true, transfers the caregiver portion (`paid_to_caregiver_total - tip`) via `CaregiverPayoutService::transferFunds()`
   - Transfer failure **does not** fail the charge — logs a warning and returns a `warning` flash message

### 3. Stripe Charge (`JobBillingService::charge`)

**File**: `app/Services/Billing/JobBillingService.php:23`

**Pre-flight checks:**
- `requires_payment` must be true
- `payment_status` must not be `charged` or `captured`
- Client must have a `stripe_customer_id`
- Client must have a default active `ClientPaymentMethod`
- `total_service_amount` must be > 0

**Execution:**
1. Creates a `ClientPayment` record with status `pending` (stores amount breakdown in metadata)
2. Calls `Stripe::paymentIntents->create()` with:
   - `amount`: `total_service_amount` in cents
   - `customer`: client's Stripe customer ID
   - `payment_method`: client's default payment method's `provider_method_id`
   - `off_session: true`, `confirm: true` (charges immediately without user interaction)
   - `metadata`: `booking_id`, `client_id`
3. On **success**:
   - Booking → `status: 'paid'`, `payment_status: 'charged'`, stores `stripe_payment_intent_id` and `actual_amount`
   - ClientPayment → `status: 'captured'`, stores `provider_payment_id`, `paid_at`
   - Fires `BookingReceipt` event (sends receipt email with review link)
4. On **failure** (CardException or ApiErrorException):
   - Booking → `payment_status: 'failed'`, increments `charge_attempt_count`
   - ClientPayment → `status: 'failed'`, stores `error_code` and `error_message`
   - Delegates to `PaymentFailureHandler`

### 4. Caregiver Payout (Config-Gated)

**Files**: 
- `app/Http/Controllers/ChargingController.php:65` (Path B)
- `app/Services/Booking/AdminBookingService.php:1335` (Path A)

Both charge paths conditionally call `CaregiverPayoutService::transferFunds()` when `config('services.stripe.enable_caregiver_transfers')` is `true` (default `false` — W-2 model, all money stays in platform).

When enabled, after the client charge succeeds:
1. Calculates transfer amount: `(paid_to_caregiver_total - tip) * 100` (in cents)
   - Uses the `Booking` model's pre-computed `paid_to_caregiver_total` from `PricingRule` rates
   - Subtracts `tip` since tips are charged separately via `TipChargeService`
2. Calls `CaregiverPayoutService::transferFunds($caregiver, $transferAmount)`
3. `CaregiverPayoutService` (`app/Services/CaregiverPayout/CaregiverPayoutService.php:158`):
   - Finds caregiver's default active `CaregiverPayoutMethod`
   - Calls `Stripe::transfers->create()` to transfer funds to the caregiver's Stripe Connect account
   - Creates a `CaregiverPayout` record with status `paid` (or `failed` on error)

**Transfer failure behavior:**
- **Path A** (Transactions): Logs a warning, returns a `warning` flash message — client was already charged, so transfer failure does not roll back the charge
- **Path B** (Charge page): Returns a JSON error with `step: 'transfer'` — client charge already succeeded, but transfer failed

### 5. Failure Handling (`PaymentFailureHandler`)

**File**: `app/Services/Billing/PaymentFailureHandler.php`

On charge failure:
1. **Notifies the client** via `PaymentFailedNotification` (database + email) — tells them to update payment method
2. **Notifies all admins** via `PaymentFailedNotification` (database + email)
3. **Schedules retry** via `RetryJobCharge` job (max 4 attempts total):
   - Attempt 1: immediate (delay 0s)
   - Attempt 2: +1 hour
   - Attempt 3: +1 day
   - Attempt 4: +3 days
   - After 4 failures: logs permanent failure, no more retries

### 6. Stripe Webhooks (Safety Net)

**File**: `app/Services/Webhooks/StripeWebhookHandler.php`

Handles async Stripe events as a safety net:
- `payment_intent.succeeded`: Updates booking to `payment_status: 'charged'`, ClientPayment to `captured`
- `payment_intent.payment_failed`: Updates booking to `payment_status: 'failed'`, ClientPayment to `failed`, dispatches `PaymentFailureHandler`

### 7. Client Payment Method Sync

**Files**:
- `app/Services/ClientPayment/ClientPaymentService.php`
- `app/Console/Commands/SyncClientPaymentMethods.php`

Client payment methods (`ClientPaymentMethod` records) are populated through four channels:

1. **Stripe webhooks** (`payment_method.attached`, `setup_intent.succeeded`, `checkout.session.completed`) — captures new payment methods in real-time
2. **User setup flow** — client adds a card via Stripe EmbeddedCheckout on the payments page
3. **Auto-sync fallback** — `showPaymentMethods()` calls `syncPaymentMethodsFromStripe()` if no local methods exist and the client has a `stripe_customer_id`
4. **Backfill command** — `php artisan payments:sync-client-methods` for one-time catch-up

The sync method `syncPaymentMethodsFromStripe`:
1. Fetches all card payment methods from `GET /v1/customers/{id}/payment_methods`
2. `updateOrCreate`s each as a `ClientPaymentMethod` record (matched by `provider_method_id`)
3. Sets the first synced method as `is_default: true` if the client has no existing default, and updates Stripe customer's `invoice_settings.default_payment_method`
4. Fails gracefully on Stripe errors — logs the error, returns empty array, does not block the page

**Backfill command**:
```bash
# Backfill all clients (safe, idempotent)
php artisan payments:sync-client-methods

# Verify with a specific client
php artisan payments:sync-client-methods --client=42
```

---

## Key Database Tables

| Table | Purpose |
|---|---|
| `bookings` | Stores `payment_status` (`pending`/`charged`/`failed`/`captured`), `stripe_payment_intent_id`, `actual_amount`, `charge_attempt_count` |
| `client_payments` | Payment audit log — amount, status, provider, error details, metadata |
| `client_payment_methods` | Client's saved Stripe payment methods (card tokens) |
| `caregiver_payouts` | Caregiver payout records — amount, status, Stripe transfer ID |
| `caregiver_payout_methods` | Caregiver's bank account details via Stripe Connect |

## Key Files

| File | Role |
|---|---|
| `app/Services/Booking/AdminBookingService.php` | Entry point — adjusts booking then charges |
| `app/Services/Billing/JobBillingService.php` | Core Stripe charge logic |
| `app/Services/CaregiverPayout/CaregiverPayoutService.php` | Stripe Connect transfers to caregivers |
| `app/Services/Billing/PaymentFailureHandler.php` | Failure notifications + retry scheduling |
| `app/Jobs/RetryJobCharge.php` | Queued retry job with exponential backoff |
| `app/Services/Webhooks/StripeWebhookHandler.php` | Async webhook handler (safety net) |
| `app/Http/Controllers/ChargingController.php` | Charge endpoint (Path B) + `calculateTotal` API |
| `app/Http/Controllers/ChargeBookingController.php` | Renders the charge page (Path B) |
| `app/Http/Controllers/TransactionController.php` | Transactions page (Path A) |
| `resources/js/pages/admin/transactions/index.tsx` | Transactions UI (active) |
| `resources/js/pages/admin/bookings/charge.tsx` | Charge page UI (dormant alternative) |
| `app/Services/Billing/TipChargeService.php` | Separate tip charging flow via client review |
| `app/Services/ClientPayment/ClientPaymentService.php` | Payment method management + Stripe sync |
| `app/Console/Commands/SyncClientPaymentMethods.php` | Backfill command for legacy payment methods |
| `config/services.php` | `enable_caregiver_transfers` flag (default `false`) |
