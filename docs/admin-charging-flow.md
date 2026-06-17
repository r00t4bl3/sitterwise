# Admin Charging Flow — Full Process Documentation

## Overview

When an admin charges a client for a completed booking, the flow spans backend services, Stripe API, database records, webhooks, notifications, and retry logic.

---

## Step-by-Step Flow

### 1. Admin Initiates Charge

There are **two UI paths** (the old `ChargeBookingController` routes are commented out):

**Path A — Transactions Page** (primary)
- `resources/js/pages/admin/transactions/index.tsx`
- Admin clicks a completed booking → opens payment sheet
- Can adjust: `checkout_at`, `total_working_hour`, `reimbursement`, `reimbursement_description`, `tip`, `bonus`
- Hourly rates × working hours recalculate totals in real-time
- On submit → `POST /bookings/{id}/process-payment`

**Path B — Old Charge Page** (route commented out)
- `resources/js/pages/admin/bookings/charge.tsx`
- Route: `/admin/bookings/charge?booking_id=X` → `ChargeBookingController::create`
- Sends `POST /admin/bookings/{id}/charge` with `reimbursement`, `tip`, `notes`

### 2. Booking Adjustment (`AdminBookingService::processPayment`)

**File**: `app/Services/Booking/AdminBookingService.php:1301`

1. Validates input: `checkout_at`, `total_working_hour`, `reimbursement`, `reimbursement_description`, `tip`, `bonus`
2. Updates the `Booking` record with adjusted values
3. The `Booking` model's `updating` boot hook triggers `calculateTotalWorkingHours()` and `calculateTotalAmount()` → recalculates `total_amount`, `charge_to_client`, `paid_to_caregiver`, `sitterwise_cut` based on `PricingRule` rates
4. Delegates to `JobBillingService::charge($booking)` for the actual Stripe charge

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

### 4. Caregiver Payout (`ChargingController::charge` path)

**File**: `app/Http/Controllers/ChargingController.php:20` (old path)

After the client charge succeeds:
1. Calculates payout: `gross = total_amount + reimbursement`, `net = gross - $12 platform fee`
2. Calls `CaregiverPayoutService::transferFunds($caregiver, $netPayout)`
3. `CaregiverPayoutService` (`app/Services/CaregiverPayout/CaregiverPayoutService.php:158`):
   - Finds caregiver's default active `CaregiverPayoutMethod`
   - Calls `Stripe::transfers->create()` to transfer funds to the caregiver's Stripe Connect account
   - Creates a `CaregiverPayout` record with status `paid` (or `failed` on error)

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
| `app/Http/Controllers/ChargingController.php` | Old charge endpoint (routes commented out) |
| `app/Http/Controllers/TransactionController.php` | Transactions page (active) |
| `resources/js/pages/admin/transactions/index.tsx` | Transactions UI |
| `resources/js/pages/admin/bookings/charge.tsx` | Old charge page UI |
