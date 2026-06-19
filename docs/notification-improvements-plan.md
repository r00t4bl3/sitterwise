# Notification Improvements Plan

## Overview

Implement three notification features:
1. **Booking Reminder** — wire up existing infrastructure with a new console command
2. **Payment Required** — send email at booking creation, show inline Stripe form on booking page, send SMS reminder after 24h
3. **Client SMS Opt-Out** — add `sms_opted_out` field to clients table

---

## Part A: Client SMS Opt-Out

Add `sms_opted_out` to clients following the caregivers pattern.

### Steps

| # | File | Change |
|---|------|--------|
| 1 | `database/migrations/2026_03_28_191335_create_clients_table.php` | Add `$table->boolean('sms_opted_out')->default(false);` |
| 2 | `app/Models/Client.php` | Add `'sms_opted_out'` to `$fillable`, `'sms_opted_out' => 'boolean'` to `$casts` |
| 3 | `database/factories/ClientFactory.php` | Add `'sms_opted_out' => false` |
| 4 | `app/Models/User.php` | Update `routeNotificationForSms()`: return phone only if `!$this->client->sms_opted_out` |

---

## Part B: Booking Reminder (24h before start)

Infrastructure already exists — just needs a trigger.

**Existing chain:**
- `BookingReminderTriggered` event
- `SendBookingReminderNotifications` listener (registered in `AppServiceProvider`)
- `BookingReminderNotification` (database + mail channels)
- `CaregiverBookingReminderMail` (SendGrid template `d-c141f95e479746dd8af8d96aa1c64067`)

### Steps

| # | File | Change |
|---|------|--------|
| 5 | `app/Console/Commands/SendBookingReminders.php` | **Create.** `#[Signature('app:send-booking-reminders')]`. Query `Booking::where('status', 'confirmed')->whereBetween('start_datetime', [now()->addHours(23), now()->addHours(24)])`. Dispatch `BookingReminderTriggered` per booking. |
| 6 | `routes/console.php` | Add `Schedule::command('app:send-booking-reminders')->hourly();` |
| 7 | `tests/Feature/Console/SendBookingRemindersTest.php` | **Create.** Happy path, no matches, missing caregiver. |

---

## Part C: Payment Required Email (at booking creation)

Send a separate email when a booking is created that requires payment. Uses a new SendGrid template.

### New Files

**`app/Mail/ClientPaymentRequiredMail.php`**
- SendGrid mailable
- Template ID: `d-9f4b24bb450140d9bd2c1628b705fbc1`
- Accepts `BookingGroup $bookingGroup`
- Data: `$bookingGroup->toEmailData()` + `payment_link = route('bookings.show', $firstBooking)`

**`app/Notifications/ClientPaymentRequiredNotification.php`**
- `via()` = `['database', 'mail']`
- `toMail()` returns `ClientPaymentRequiredMail`
- `toArray()` for in-app DB notification

### Template Data Mapping

| Template Field | Source |
|----------------|--------|
| `{{first_name}}` | `$bookingGroup->toEmailData()['first_name']` |
| `{{booking_date}}` | `$bookingGroup->toEmailData()['dates'][0]` |
| `{{start_time}}` | `$bookingGroup->toEmailData()['start_time']` |
| `{{end_time}}` | `$bookingGroup->toEmailData()['end_time']` |
| `{{location}}` | `$bookingGroup->toEmailData()['location']` |
| `{{service_type}}` | `$bookingGroup->toEmailData()['service_type']` |
| `{{booking_id}}` | `$bookingGroup->toEmailData()['booking_id']` |
| `{{payment_link}}` | `route('bookings.show', $bookingGroup->bookings->first())` |

### Modified Listeners

**`app/Listeners/SendBookingCreatedNotifications.php`**
- After existing client notification, check:
  ```php
  if ($client?->user && $event->booking->requires_payment && $event->booking->payment_status === 'pending') {
      $client->user->notify(new ClientPaymentRequiredNotification($event->booking));
  }
  ```

**`app/Listeners/SendBookingGroupCreatedNotifications.php`**
- Same check after group notification using `$group->requires_payment`

### Test

**`tests/Feature/Booking/PaymentRequiredNotificationTest.php`**
- Dispatch event, assert notification sent to client
- Verify payload fields
- Handle missing client gracefully

---

## Part D: Inline Stripe Form on Booking Show Page

The `payment_link` in the email points to the booking detail page (`/bookings/{ulid}`). On that page, when the booking requires payment and no payment method is saved, render an inline Stripe Embedded Checkout form.

### Flow

1. Client visits `/bookings/{ulid}`
2. `ClientBookingService::show()` runs:
   - If `request('session_id')` is present → retrieve Stripe session & store payment method (redirect return)
   - If `requires_payment && payment_status === 'pending' && no existing payment method` → create Stripe Setup Intent with `return_url = route('bookings.show', $booking) . '?session_id={CHECKOUT_SESSION_ID}'`, pass `client_secret` to Inertia
3. Frontend renders `<StripeCheckout clientSecret={...} />` conditionally
4. User adds card → Stripe redirects to `/bookings/{ulid}?session_id=...`
5. `ClientBookingService::show()` detects `session_id`, stores payment method, page loads fresh

### Backend Changes

| # | File | Change |
|---|------|--------|
| 8 | `app/Services/ClientPayment/Contracts/ClientPaymentServiceInterface.php` | Add optional param: `createSetupIntent(?string $returnUrl = null): array;` |
| 9 | `app/Services/ClientPayment/ClientPaymentService.php` | Update `createSetupIntent(?string $returnUrl = null)`: if `$returnUrl` provided, use it instead of `/payments`. |
| 10 | `app/Services/Booking/ClientBookingService.php` | Inject `ClientPaymentServiceFactory`. In `show()`: handle `session_id` return flow; create setup intent with booking return_url when payment needed; pass `requires_payment`, `payment_status`, `payment_setup_intent` (nullable), `has_payment_method` to Inertia. |

### Frontend Changes

| # | File | Change |
|---|------|--------|
| 11 | `resources/js/pages/client/bookings/show.tsx` | Add `requires_payment`, `payment_status`, `payment_setup_intent`, `has_payment_method` to `Booking` type. Below fees section, conditionally render "Payment Required" card with `<StripeCheckout clientSecret={payment_setup_intent} />`. |
| 12 | Same file, imports | Add `StripeCheckout` from `@/components/stripe/stripe-checkout`, add `CreditCard` lucide icon. |

---

## Part E: Payment Reminder SMS (24h later)

Send SMS to clients whose booking requires payment but remains unpaid after 24 hours.

### SMS Text

> Hi {{first_name}}, this is Sitterwise following up on your {{booking_date}} reservation. We're ready to match you with a caregiver as soon as your payment info is on file! Your card won't be charged until after care is complete. Add it here: {{payment_link}} Questions? Just reply or call 619-663-4379.

### Steps

| # | File | Change |
|---|------|--------|
| 13 | New migration | Add `nullable timestamp payment_reminder_sent_at` to `bookings` table |
| 14 | `app/Notifications/ClientPaymentSmsReminderNotification.php` | **Create.** `via()` = `[SmsChannel::class]`. `toSms()` returns message object with the text above. `payment_link = route('bookings.show', $booking)`. |
| 15 | `app/Console/Commands/SendPaymentSmsReminders.php` | **Create.** `#[Signature('app:send-payment-sms-reminders')]`. Query: `Booking::where('requires_payment', true)->where('payment_status', 'pending')->whereNull('payment_reminder_sent_at')->where('created_at', '<', now()->subHours(24))`. Per match: notify via `ClientPaymentSmsReminderNotification`, set `payment_reminder_sent_at = now()`. |
| 16 | `routes/console.php` | Add `Schedule::command('app:send-payment-sms-reminders')->hourly();` |
| 17 | `tests/Feature/Console/SendPaymentSmsRemindersTest.php` | **Create.** Matching bookings, opted-out client (no SMS sent), no matches. |

---

## Execution Order

1. **Part A** — migration + model + factory + User routing (foundation for SMS opt-out)
2. **Part B** — reminder command (isolated, no dependencies)
3. **Part C** — payment email + notification + listener changes
4. **Part D** — Stripe return_url + inline form (depends on Part C's payment detection)
5. **Part E** — SMS reminder command + notification (depends on Part A's opt-out)
6. Run `vendor/bin/pint --format agent` then full test suite
