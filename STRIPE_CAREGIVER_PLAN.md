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

- [ ] **Webhook Enhancement**:
    - Update `StripeWebhookHandler::handleAccountUpdated` to update the `Caregiver` model's status (e.g., `stripe_status = 'verified'`).
    - Add handlers for `transfer.failed` and `payout.failed` to notify administrators/caregivers.
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

- [ ] **Feature Tests**:
    - Write Pest tests for the transfer logic.
    - Write tests for webhook state updates.
    - Mock Stripe API responses to verify error handling paths.
