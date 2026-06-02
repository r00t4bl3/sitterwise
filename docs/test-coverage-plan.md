# Plan: Add Missing Feature Tests (Excludes Import Services)

## Objective
Close test gaps for critical billing, webhook, notification, and lifecycle behaviors without touching import/seed logic.

## Scope Additions (files to create)

### 1. Stripe Retry & Failure Handling
File: `tests/Feature/Admin/PaymentRetryTest.php`
- Payment failure handler queues `RetryJobCharge` with correct delays: 0s / 1h / 1d / 3d
- Handler stops retrying after `charge_attempt_count >= 4`
- Handler notifies client and admins via notification + mail
- `RetryJobCharge` skips if booking already charged/captured
- Retry job does not re-run after max attempts

### 2. Tip Charging
File: `tests/Feature/Booking/TipChargeTest.php` (or `Client/TipChargeTest.php`)
- `TipChargeService::charge` succeeds and updates `booking.tip` + marks `ClientPayment` captured
- Prevents duplicate pending tip (`ClientPayment` metadata `type=tip`)
- Rejects `<= 0` tip amounts
- Falls back to default `ClientPaymentMethod` when none provided
- Records error on `CardException`/`ApiErrorException` without crashing

### 3. Stripe Webhook Handler
File: `tests/Feature/Admin/StripeWebhookTest.php`
- Invalid signature returns failure
- `payment_intent.succeeded` sets `payment_status=charged`, links `ClientPayment`, sets `actual_amount`
- `payment_intent.payment_failed` increments `charge_attempt_count`, sets status `failed`, invokes `PaymentFailureHandler`
- Handles missing booking gracefully (no exceptions)

### 4. Twilio Webhook Coverage
File: `tests/Feature/BroadcastSmsTest.php` -> extend
Add:
- Status callback updates matching `BroadcastMessage` delivery status
- Inbound SMS handles `STOP`/`STOPALL`/`UNSUBSCRIBE`/`CANCEL`/`END`/`QUIT` (already partly covered)

### 5. Booking Notifications (Invitation + Reminder)
Files:
- `tests/Feature/Booking/BookingNotificationTest.php`
- `tests/Feature/Admin/BookingTest.php` -> add 2 tests

Scenarios:
- `BookingInvitationSent` dispatches `BookingInvitationNotification` to caregiver user
- `BookingReminderTriggered` triggers reminder queue/listener hook (smoke)
- `BookingReceipt` fires after charge success (smoke)
- Admin notify endpoint dedup + `BookingCaregiverNotification` creation (partially exists, verify coverage)

### 6. Assignment Resolution Completeness
File: `tests/Feature/Caregiver/CaregiverAssignmentResolutionTest.php`
- Admin can mark `completed`
- Admin can mark `reassigned`
- Admin can mark `no_show`
- Admin can mark `cancelled_by_sitterwise`
- Rejects invalid/terminal statuses
- Each resolution emits expected notification (smoke)

### 7. SMS Broadcast Dispatch + Delivery Tracking
File: `tests/Feature/Superadmin/SmsBroadcastDispatchTest.php`
- `SendBroadcastMessage` job dispatched per eligible caregiver, respecting throttle
- `SmsBroadcast` + `BroadcastMessage` records created
- Status callback updates delivery state on `BroadcastMessage`

### 8. Booking Receipt Notification (Isolated)
File: `tests/Feature/Booking/BookingReceiptTest.php`
- `BookingReceipt` event sends mail with review URL
- Does not alter booking status

### 9. Payment Failure Notification (Client + Admin)
File: `tests/Feature/Booking/PaymentFailureNotificationTest.php`
- Checks database notification record payload for client
- Checks database notification record payload for admin
- Mail mailable contains booking + attempt info

### 10. CleanupExpiredReservations Command
File: `tests/Feature/Booking/CleanupExpiredReservationsTest.php`
- Releases only reserved bookings past TTL
- Leaves non-expired reserved bookings intact
- Resets `reserved_by` and `reservation_expires_at`

## Execution Notes
- Use `Mockery::mock(JobBillingService::class)` / `mock(StripeWebhookHandler::class)` to isolate Stripe calls.
- Use event fakes (`Event::fake`) for reminder/invitation/receipt tests.
- Use `Storage::fake()` only if PDF/mail attachments surface.
- Run: `php artisan test --compact --filter=PaymentRetryTest|TipChargeTest|StripeWebhookTest|BookingNotificationTest|CaregiverAssignmentResolutionTest|SmsBroadcastDispatchTest|BookingReceiptTest|PaymentFailureNotificationTest|CleanupExpiredReservationsTest`

## Tradeoffs / Open Questions
- Should retry tests assert exact `DateInterval` objects or use `Carbon::add...()` tolerance?
- Tip charging requires a survey of controller path (`BookingReviewController`) to confirm if Feature-level via HTTP or unit-level to service.
- Twilio status callback route target needs confirmation before writing controller-level test.

Justification: these 8-10 files add coverage for the highest-risk billing, scheduling, and notification paths already shipped in production, with no overlap into import/seed work.
