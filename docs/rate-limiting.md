# Rate Limiting

## Overview

All public-facing POST endpoints are protected by Laravel's `throttle` middleware using named rate limiters defined in `App\Providers\AppServiceProvider`.

Rate limiters are **disabled in the `testing` environment** to prevent flaky tests. All limits enforced at minimum `perMinute` granularity.

## Fortify Routes

Processed by `App\Http\Middleware\ThrottleFortifyRoutes` (registered in `config/fortify.php`).

| Route Name | HTTP Method | Limiter | Limits | User Feedback |
|---|---|---|---|---|
| `register.store` | POST | `register` | 3/min per IP | Redirect back with `errors.email` |
| `password.email` | POST | `forgot-password` | 3/min per IP, 1/5min per email | Redirect back with `errors.email` |
| `login.store` | POST | `login` (Fortify) | 5/min per email+IP | — |
| `two-factor.login.store` | POST | `two-factor` (Fortify) | 5/min per session | — |

## Caregiver Application

Applied as `->middleware('throttle:<name>')` in `routes/web.php`.

| Route | Limiter | Limits | User Feedback |
|---|---|---|---|
| `POST /caregiver/apply/send-otp` | `caregiver-otp-send` | 3/min per IP | Flash `error` toast |
| `POST /caregiver/apply/verify-otp` | `caregiver-otp-verify` | 5/min per IP | Flash `error` toast |
| `POST /caregiver/apply/save-progress` | `caregiver-save-progress` | 20/min per session | None (JSON endpoint) |
| `POST /caregiver/apply/submit` | `caregiver-submit` | 5/min per IP | Flash `error` toast |
| `POST /caregiver/apply/.../replace-reference` | `caregiver-replace-reference` | 3/min per IP | Flash `error` toast |

## Reference Portal

| Route | Limiter | Limits | User Feedback |
|---|---|---|---|
| `POST /references/{token}` | `reference-submit` | 5/min per IP | Flash `error` toast |

## Guest Booking

| Route | Limiter | Limits | User Feedback |
|---|---|---|---|
| `POST /book` | `guest-booking` | 5/min per IP | Flash `error` toast |
| `POST /book/payment/{token}/status` | `guest-booking` | 5/min per IP | Flash `error` toast |
| `POST /book/payment/{token}/verify` | `guest-booking` | 5/min per IP | Flash `error` toast |

## Other Protected Endpoints

| Route | Limiter | Limits | Source |
|---|---|---|---|
| `POST /reset-password` | `reset-password` | 3/min per IP | `ThrottleFortifyRoutes` |
| `PUT /settings/password` | — | 6/min | `routes/settings.php` |

## Design Decisions

### Email uniqueness makes certain second limits redundant

`register` and `caregiver-submit` originally had a second limit (`1/hour per email`). These were removed because the `users.email` column has a UNIQUE constraint — duplicate submissions with the same email would be rejected at the database level regardless of rate limiting.

### `forgot-password` retains per-email limiting

Unlike registration, the same email can request a password reset multiple times. The limit of 1 per 5 minutes per email prevents abuse (cost of email delivery) while allowing legitimate retries in a reasonable timeframe.

### Flash messages vs validation errors

Fortify routes (`register`, `forgot-password`) use `withErrors(['email' => '...'])` because their forms already render `errors.email`. All other public endpoints use `with('error', '...')` rendered as a toast via the `ToasterMessage` component.

## Files

| File | Purpose |
|---|---|
| `app/Providers/AppServiceProvider.php` | All named rate limiter definitions |
| `app/Http/Middleware/ThrottleFortifyRoutes.php` | Applies rate limiters to Fortify routes |
| `config/fortify.php` | Registers `ThrottleFortifyRoutes` in Fortify's middleware stack |
| `routes/web.php` | `throttle:` middleware on caregiver, reference, and guest booking routes |
| `resources/js/components/toaster-message.tsx` | Renders `flash.error` as a toast notification |
