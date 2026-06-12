# Spam Protection Plan

## Overview

Add multi-layer spam protection to all public-facing endpoints. Currently, only `login`, `two-factor-challenge`, and `settings/password` have rate limiting — everything else is unprotected.

## Inventory of Public POST Endpoints

### Fortify Authentication (registered by `laravel/fortify`)

| Method | URI | Abuse Impact | Existing Protection | Priority |
|--------|-----|-------------|-------------------|----------|
| POST | `/login` | Brute force login | 5/min per email+IP ✓ | — |
| POST | `/register` | Unlimited account creation | None | 🔴 |
| POST | `/forgot-password` | Unlimited password reset emails (cost) | None | 🔴 |
| POST | `/reset-password` | Brute force | Broker token (natural) | 🟢 |
| POST | `/two-factor-challenge` | 2FA brute force | 5/min per session ✓ | — |

### Caregiver Application

| Method | URI | Abuse Impact | Existing Protection | Priority |
|--------|-----|-------------|-------------------|----------|
| POST | `/caregiver/apply/send-otp` | Unlimited OTP emails (cost) | None | 🔴 |
| POST | `/caregiver/apply/verify-otp` | OTP brute-force (1M combos, 10min) | None | 🟡 |
| POST | `/caregiver/apply/save-progress` | DB bloat | VerifyEmail middleware | 🟢 |
| POST | `/caregiver/apply/submit` | Creates user + caregiver + 5 emails | VerifyEmail middleware | 🔴 |
| POST | `/caregiver/apply/.../replace-reference` | Reference email spam | Token ownership check | 🟢 |

### Guest Booking

| Method | URI | Abuse Impact | Existing Protection | Priority |
|--------|-----|-------------|-------------------|----------|
| POST | `/book` | Server resource / session storage | None | 🟡 |
| POST | `/book/payment/{token}/status` | Stripe polling abuse | None (session + token) | 🟢 |
| POST | `/book/payment/{token}/verify` | Stripe verify abuse | None (session + token) | 🟢 |

### Other Public

| Method | URI | Abuse Impact | Existing Protection | Priority |
|--------|-----|-------------|-------------------|----------|
| POST | `/references/{token}` | Reference form spam | 32-char token | 🟡 |
| POST | `/review/{booking}` | Review spam from links | Laravel signed URL | 🟢 |

### Webhooks (CSRF-excepted)

| Method | URI | Abuse Impact | Existing Protection | Priority |
|--------|-----|-------------|-------------------|----------|
| POST | `/webhooks/stripe` | Fake Stripe events | Stripe signature verification ✓ | — |
| POST | `/webhooks/twilio/status` | Fake SMS status callbacks | None | 🔵 |
| POST | `/webhooks/twilio/inbound` | Fake inbound SMS / opt-out manipulation | None | 🔵 |

## Implementation Plan

### Layer 1 — Rate Limiting

Add named rate limiters in `app/Providers/AppServiceProvider.php` following the pattern already used in `FortifyServiceProvider.php`.

Rate limiter definitions:

| Name | Limits | Keyed By | Custom Response |
|------|--------|----------|-----------------|
| `register` | 3/min, 1/hour | IP, email | Yes — redirect back with error |
| `forgot-password` | 3/min, 1/hour | IP, email | Yes — redirect back with error |
| `reset-password` | 3/min | IP | Default 429 |
| `caregiver-otp-send` | 3/min | IP | Yes — redirect back with error |
| `caregiver-otp-verify` | 5/min | IP | Yes — redirect back with error |
| `caregiver-submit` | 2/min, 1/hour | IP, verified_email session | Yes — redirect back with error |
| `caregiver-save-progress` | 20/min | verified_email session or IP | None (returns JSON) |
| `caregiver-replace-reference` | 3/min | IP | Yes — redirect back with error |
| `reference-submit` | 5/min | IP | Yes — redirect back with error |
| `guest-booking` | 5/min | IP | Yes — redirect back with error |

**Files to modify:**

- `app/Providers/AppServiceProvider.php` — add all rate limiter definitions in `boot()`
- `routes/web.php` — add `->middleware('throttle:<name>')` to caregiver, reference, and guest booking routes
- `bootstrap/app.php` — configure throttle middleware for Fortify routes (`register`, `forgot-password`, `reset-password`) via `withMiddleware()`

### Layer 2 — Submission Time Gate (Caregiver Application)

Add a minimum-time check in `CaregiverApplicationController::submit()`:

- Check `verified_at` session timestamp when present
- Reject if less than 30 seconds have elapsed since OTP verification
- **Does not apply when `verified_at` is absent** (dev/test environments auto-fill `verified_email` without setting `verified_at`, so existing tests pass unmodified)
- Log a warning to the `submission` channel

**File:** `app/Http/Controllers/CaregiverApplicationController.php`

### Layer 3 — Twilio Webhook Signature Validation (Lower Priority)

The Twilio webhooks are CSRF-excepted and have no authentication or signature verification. A malicious actor can POST fake status updates or inbound messages (e.g., spoofing an opt-out SMS).

**Twilio already provides request validation** via its SDK (already in `composer.json`):

```php
use Twilio\Security\RequestValidator;

$validator = new RequestValidator(config('services.twilio.auth_token'));
$isValid = $validator->validate(
    $request->getContent(),
    $request->headers->get('X-Twilio-Signature'),
    $request->fullUrl()
);
if (! $isValid) {
    abort(401);
}
```

**File:** `app/Http/Controllers/BroadcastSmsController.php` — add validation to `statusCallback()` and `inboundSms()` methods.

### Layer 4 — Cloudflare Turnstile (Optional)

Not implementing now. If needed later:
1. Add Turnstile site/secret keys to `.env`
2. Write a custom validation rule or use a package
3. Add Turnstile widget to the React forms (login, register, forgot-password, caregiver apply)
4. Validate token server-side

## Test Impact

The only change that affects existing tests is the submission time gate. It naturally skips in tests because:

- The `VerifyEmail` middleware (in `local`) auto-sets `verified_email` but **not** `verified_at`
- The `submit()` method's fallback (line 126) also doesn't set `verified_at`
- So the gate's condition `if ($verifiedAt && ...)` evaluates to `false` in tests

No test modifications needed.

## Files Summary

| File | Change |
|---|---|
| `app/Providers/AppServiceProvider.php` | Add 10 rate limiter definitions with custom responses |
| `routes/web.php` | Add `throttle:` middleware to relevant routes |
| `bootstrap/app.php` | Add throttle for Fortify routes |
| `app/Http/Controllers/CaregiverApplicationController.php` | Add submission time gate |
| `app/Http/Controllers/BroadcastSmsController.php` | Add Twilio signature validation (lower priority) |
