# Caregiver Stripe Integration Completion Plan

## Current Status

The onboarding flow (Connect Express) and payout method syncing are implemented. The UI has been overhauled to provide a professional payout history dashboard with a slide-out management sheet for payout methods. The foundation for tracking payouts (database and models) is now in place.

## Business Flow (Payout Trigger)

The payout flow is initiated by the **bookings** table:

1.  **Caregiver**: Clicks "Checkout" on a completed job.
    - Sets `checkout_at` = current timestamp.
    - Calculates `total_working_hour`.
    - Updates `status` to "completed" (or "pending_payment").
2.  **Admin**: Reviews booking details (hours, reimbursement, tip), adjusts if needed, clicks "Process Payment".
3.  **System**:
    - Calculates payout: `total_amount` + `reimbursement` + `tip` (minus platform fee).
    - Checks if client has paid (`payment_status` = 'succeeded').
    - Creates a record in `caregiver_payouts` (status: `pending`).
    - Triggers `transferFunds` via a job.
4.  **Result**: Payout record appears in caregiver's history with status `paid` (or `failed`).

## Goals

1.  Implement fund transfers from platform to caregivers.
2.  Automate account status updates via webhooks.
3.  Improve error handling and monitoring for payouts.
4.  Enhance the caregiver payout management UI (Mostly Completed).

## Implementation Steps

### Phase 0: Database & Schema Updates

- [x] **Bookings Table Updates**:
    - Add `checkout_at` (timestamp, nullable) to track when caregiver checked out.
    - Add `total_working_hour` (decimal, nullable) to store calculated hours.
    - _Note: Migration created._

### Phase 1: Payout Execution (The Core Missing Piece)

- [ ] **Update `CaregiverPayoutService`**:
    - Implement a `transferFunds(Caregiver $caregiver, int $amount, string $currency)` method.
    - Implement logic to handle "Transfer" (Platform $\rightarrow$ Connected Account).
- [ ] **Create Payout Job/Command**:
    - Implement a background job to handle batch payouts or individual job-completion payouts.
- [x] **Database Tracking**:
    - Create a `caregiver_payouts` table to track every transfer (amount, status, stripe_transfer_id, caregiver_id, caregiver_payout_method_id).

### Phase 2: Reliability & State Sync

#### Client-Side Payment Events

- [x] **`checkout.session.completed`**:
    - Sync the payment method created via Stripe Checkout to `ClientPaymentMethod`.
    - Retrieves the SetupIntent → payment method → persists card details.
- [x] **`charge.dispute.created`**:
    - Log dispute details (booking, amount, reason) and notify admins.
    - Mark the related booking's payment as disputed.
- [x] **`setup_intent.succeeded`**:
    - Confirm payment method setup and sync to `ClientPaymentMethod` record.
- [x] **`setup_intent.setup_failed`**:
    - Log the failure with client/error context.
- [x] **`payment_method.attached`**:
    - Sync newly attached payment methods to `ClientPaymentMethod` records.
- [x] **`payment_method.detached`**:
    - Mark detached payment methods as `inactive` in the database.
- [x] **`charge.refunded`**:
    - Mark the related booking's payment as `refunded` and log refund details.

#### Connect-Side Events

- [ ] **`account.updated`**:
    - Update `Caregiver` model's Stripe status (e.g., `stripe_status = 'verified'`, `charges_enabled`, `payouts_enabled`).
- [ ] **`transfer.created`**:
    - Sync transfer status to `CaregiverPayout` records.
- [x] **`transfer.reversed`**:
    - Mark the related `CaregiverPayout` as `reversed`, log a warning.

- [ ] **Optimized Status Checks**:
    - Update `CaregiverPayoutController` to check local database status first before calling the Stripe API.

### Phase 3: Error Handling & UX

- [ ] **Granular Error Handling**:
    - Refactor `CaregiverPayoutController::onboarding` to handle specific Stripe API exceptions.
- [ ] **UI Improvements**:
    - [ ] Add a "Sync Now" button in `resources/js/pages/caregiver/payouts/index.tsx` to trigger `syncPayoutMethods()` manually.
    - [x] Display payout history (from the new `caregiver_payouts` table) on the caregiver dashboard.
    - [x] Implement "Payout Methods" management via a Sheet component.

### Phase 4: Testing & Verification

- [x] **Webhook Feature Tests**:
    - 21 tests covering all webhook event handlers (13 new, 8 pre-existing).
    - Tests exercise: `payment_intent.succeeded`, `payment_intent.failed`, `checkout.session.completed`, `charge.dispute.created`, `setup_intent.succeeded`, `setup_intent.setup_failed`, `payment_method.attached`, `payment_method.detached`, `transfer.reversed`, `charge.refunded`.
    - Includes edge cases: missing bookings, missing metadata, invalid signatures, handler exceptions returning `success: true`.
- [x] **Webhook Error Resilience**:
    - Try-catch wrapper around all event handlers prevents any single handler exception from returning 500 to Stripe.
    - Handler exceptions are logged with full trace for debugging; webhook returns `success: true` to prevent Stripe retry loops.
- [ ] **Feature Tests (remaining)**:
    - Write Pest tests for the transfer logic.
