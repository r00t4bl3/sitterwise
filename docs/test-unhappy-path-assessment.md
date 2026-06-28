# Test Unhappy Path Assessment

## Overall Numbers

| Metric | Count |
|---|---|
| Total test files | 97 |
| Feature tests | 77 |
| Unit tests | 18 |
| Architecture tests | 1 |
| Happy path only | ~48 files |
| Both happy + unhappy | ~37 files |
| Unhappy path only | ~11 files |

Roughly half the suite covers some negative scenario, but coverage is thin and uneven.

---

## Per-Scenario Coverage

### 401 — Unauthenticated

**Well covered.** ~30 feature tests assert `assertRedirect('/login')` or `assertRedirect(route('login'))` for guest users hitting protected routes. Laravel's Fortify-driven auth middleware handles this automatically.

**Not tested:** API-style 401 responses (this app is web/Inertia-only so this is low risk). The two `abort(401)` calls in `BroadcastSmsController` (Twilio signature verification) are untested.

---

### 403 — Forbidden / Unauthorized Role

**Good coverage.** 16 test files assert `assertStatus(403)` or `assertForbidden()`:

| Test File | What It Covers |
|---|---|
| `ApplicationManagementTest` | Admin-only routes blocked for non-admins |
| `Admin/ClientTest` | Client CRUD blocked for non-admins |
| `Admin/CaregiverTest` | Caregiver CRUD blocked for non-admins |
| `Admin/BookingTest` | Booking management blocked for non-admins |
| `BroadcastSmsTest` | Super-admin routes blocked for admins |
| `MilestoneViewTest` | Caregiver-only routes blocked for clients |
| `InterviewTalkingPointTest` | Super-admin routes blocked for admins |
| `BookingReviewTest` | Review ownership checks |
| `CaregiverCancellationTest` | Cancellation ownership checks |
| `CaregiverAssignmentResolutionTest` | Assignment ownership checks |
| `BookingRatingTest` | Rating ownership checks |
| `ApplicationLifecycleTest` | Status-based guards |
| `ApplicationCertificationVerificationTest` | Certification verification guards |

**Inconsistency:** Some 403 scenarios redirect with a flash message instead of returning `abort(403)`:
- `BookingReviewController::store()` line 33 → `Redirect::back()->with('error', 'Unauthorized or booking not completed')`
- `CaregiverPayoutController` lines 85, 112 → `redirect('/payouts')->with('error', 'Unauthorized')`

This means the user experience for "access denied" is inconsistent.

---

### 404 — Not Found

**Partial coverage.** Only 4 test files explicitly test 404 responses:

| Test File | What It Covers |
|---|---|
| `SmokeTest` | `/this-route-does-not-exist` returns 404 |
| `ReferencePortalTest` | Invalid reference tokens return 404 |
| `CaregiverInterviewTalkingPointTest` | Talking points not belonging to interview return 404 |
| `ApplicationManagementTest` | Non-existent application returns 404 |

**Not tested:** The many `firstOrFail()` and `findOrFail()` calls that produce 404s:

- `Booking::resolveRouteBinding()` — `firstOrFail()` (lines 131-138). Any invalid booking ULID/ID in a URL produces a 404. Affects ALL routes with `{booking}` parameter.
- `AdminAvailabilityService` — 2 `findOrFail()` calls
- `CaregiverAvailabilityService` — 1 `findOrFail()` call
- `ClientPaymentService` — 2 `findOrFail()` calls
- `ChargeBookingController` — 1 `findOrFail()` call

**Route model binding risk:** `Booking` model's custom `resolveRouteBinding()` method uses `firstOrFail()`, meaning any of these routes produce a raw 404 for invalid booking IDs:

- `guest.bookings.confirmation`
- `bookings.show`, `bookings.update`, `bookings.destroy`
- `jobs.show`, `jobs.checkout`, `jobs.rate`
- `review.create`, `review.store`
- Admin booking management routes
- Assignment routes

---

### 422 — Validation Errors

**Good in spots, weak elsewhere.**

| Well Tested | Weak / Untested |
|---|---|
| `CaregiverApplicationTest` — 50+ per-field validation assertions | Booking creation validation |
| `PricingRuleControllerTest` — per-field validation | Client management validation |
| `ProfileControllerTest` — profile field validation | Profile update edge cases |
| `ApplicationLifecycleTest` — state machine validation | |
| `Admin/BookingTest` — some validation paths | |

---

### 500 — Server Errors

**Zero test coverage.** No test anywhere asserts `assertStatus(500)`, `assertServerError()`, or verifies that exceptions produce graceful error pages.

29 `try/catch` blocks exist across controllers and services. Only 2 are tested (`BookingRouteBindingTest`, `BookingUlidTest` for `ModelNotFoundException`). The remaining 27 catch blocks are completely untested:

| File | Catch Block | Risk |
|---|---|---|
| `CaregiverApplicationController` | `catch (\Exception)` and `catch (\Throwable)` | Application submission failures return raw errors |
| `GuestBookingController` | 3× `catch (\Exception)` | Booking creation failures |
| `ClientController` | 3× `catch (\Exception)` | Payment method, profile photo failures |
| `CaregiverPayoutController` | 3× `catch (\Exception)` | Payout request failures |
| `GuestBookingService` | 4× `catch (\Exception)` | Booking creation failures |
| `ClientPaymentService` | `catch (CardException)`, `catch (ApiErrorException)` | Stripe failures |
| `TipChargeService` | `catch (CardException)`, `catch (ApiErrorException)`, `catch (\Exception)` | Tip charge failures |
| `JobBillingService` | `catch (CardException)`, `catch (ApiErrorException)` | Billing failures |
| `PaymentFailureHandler` | 2× `catch (\Exception)` | Payment failure handling |
| `CaregiverPayoutService` | `catch (\Exception)`, `catch (ApiErrorException)` | Payout transfer failures |
| `ImportUserService` | 3× `catch (\Exception)` | Import failures |
| `StripeWebhookHandler` | `catch (SignatureVerificationException)`, `catch (UnexpectedValueException)` | Webhook validation failures |
| `AdminBookingService` | `catch (QueryException)` | Database errors |

---

### 419 — Session Expired

**Not tested.** The CSRF token expiry / session timeout scenario has no test coverage. Currently would produce a Laravel default error page.

### 429 — Rate Limiting

**Tested.** 2 test files cover rate limiting:
- `RateLimitingTest` — general API rate limits
- `ThrottleFortifyRoutesTest` — Fortify-specific throttling

### 400 — Bad Request

**One file, all tests skipped.** `ChargingControllerTest` has 5 tests for 400 responses on invalid booking states, but all are skipped. The `JobController` `abort(400, 'Only completed jobs can be rated.')` at line 209 is untested.

---

## Business Logic Edge Cases

### Empty States

No tests verify graceful handling when pages have zero results:

| Page | Gap |
|---|---|
| Caregiver available bookings | If all bookings are claimed or confirmed, the listing is empty — no test for the empty-state UI |
| Caregiver job history | Filtering by status/search may return zero results — untested |
| Admin application list | Pagination with zero applications — untested |
| Admin client/caregiver list | Zero results after search — untested |

### Boundary Conditions

| Gap | Severity | Detail |
|---|---|---|
| No max tip amount | **High** | `StoreReviewRequest` and `ChargeBookingRequest` validate `'tip' => ['nullable', 'numeric', 'min:0']` — no upper bound. A $1,000,000 tip passes validation. |
| No max reimbursement | **High** | `ChargeBookingRequest` validates `'reimbursement' => ['nullable', 'numeric', 'min:0']` with no `max`. |
| Booking status not enum-validated | **Medium** | `StoreBookingRequest` accepts `'status' => ['required', 'string']` — any arbitrary string (e.g., `'invalid_status'`) is stored in the database. |
| Admin booking missing minimum 4-hour rule | **Medium** | Admin rules validate `end_datetime => after:start_datetime` but skip `MinimumBookingDuration` that client rules enforce. |
| $12 platform fee vs near-zero payout | **Medium** | `ChargingController::PLATFORM_FEE = 1200` is subtracted from payout. No test for when `netPayout < $12` (platform loses money). |

### State Machine Transitions

| Gap | Severity | Detail |
|---|---|---|
| Cancel Completed/Paid bookings | **Critical** | `AdminBookingService::cancel()` at line 905 only checks `status !== 'cancelled'`. A **completed** or **paid** booking can be cancelled, zeroing all financial records (`charge_to_client`, `paid_to_caregiver`, `sitterwise_cut` all set to `0`) — permanent data loss. |
| Checkout non-Confirmed booking | **Critical** | `JobController::checkout()` at line 174 sets `'status' => BookingStatus::Completed->value` without checking the current status is `Confirmed`. A caregiver could checkout a `Received`, `Pending`, or `Reserved` booking, silently marking it Completed. |
| Null caregiver on rating | **Critical** | `JobController::rate()` line 222 calls `$booking->caregiver->recalculateRating()` without null guard. If `caregiver_id` is null (valid for `Received`/`Pending` bookings), this throws a fatal error. |
| Null caregiver on review | **Critical** | `BookingReviewController::processReviewSubmission()` line 122 calls `$booking->caregiver->recalculateRating()` with no null check — same crash. |
| Re-charging reset-payment-status booking | **Medium** | `ChargingController::charge()` guards against `charged`/`captured` payment status, but not if `payment_status` was manually reset to `pending`/`failed`. |

### Payment Refund Scenarios

| Gap | Severity | Detail |
|---|---|---|
| Partial refund logic | **Critical** | `AdminBookingService::refund()` handles partial refunds but has no test coverage for edge cases (refund amount > original charge, refund amount = 0, multiple partial refunds exceeding total). |
| Full refund failure | **High** | If Stripe refund API fails, the booking status may be updated but the actual refund never processed — no test verifies this failure path. |
| Refund notification delivery | **High** | `BookingRefundedNotification` is sent after refund, but no test verifies the notification contains correct refund amount and booking details. |
| Refund idempotency | **Medium** | If a webhook is delivered twice (Stripe retry), the refund could be processed twice — no test for duplicate refund prevention. |

### Missing Relationship Guards

| Gap | Severity | Detail |
|---|---|---|
| Null client in JobController | **High** | `JobController::show()` line 120 accesses `$booking->client->first_name` — the `client()` relationship is a `HasOneThrough` via `BookingGroup`, which can resolve to null. |
| Null user in ApplicationController | **High** | `ApplicationController::show()` line 139 accesses `$caregiver->user->email` directly — crashes if `user_id` is null. |
| Null client in AdminBookingService | **High** | `AdminBookingService::show()` line 512 accesses `$booking->client->first_name` without null check. |

### Data Integrity / Cascade Deletes

| Gap | Severity | Detail |
|---|---|---|
| User deletion cascade | **High** | When a `User` is deleted, the DB cascades (`onDelete('cascade')`) to caregivers/clients. But what happens to bookings, applications, reviews, and other records linked to the deleted caregiver/client? No test verifies cascade behavior doesn't leave orphaned records. |
| Soft-delete vs hard-delete behavior | **High** | Several models use soft deletes. No test verifies that soft-deleted records are excluded from queries, or that hard-deleting a soft-deleted record properly cascades. |
| Orphaned bookings after client/caregiver delete | **High** | A booking links to both client and caregiver via `BookingGroup`. If either is deleted, the booking may become unviewable/unmanageable — no test verifies graceful handling of orphaned bookings. |
| Application data integrity | **Medium** | When a caregiver application is approved and a caregiver/user is created, no test verifies the application ↔ caregiver ID mapping remains consistent. |

### Duplicate Submission

| Gap | Severity | Detail |
|---|---|---|
| Duplicate guest bookings | **High** | `GuestBookingController::processPayment()` and `verifyPayment()` call `createBookingWithPayment()` every time. Navigating back and re-submitting (or refreshing during Stripe callback) creates duplicate `BookingGroup` + `Booking` records. |
| Silent review overwrite | **Low** | `BookingReviewController` uses `BookingRating::updateOrCreate()` which silently overwrites prior review data — untested. |

### Concurrency / Race Conditions

| Gap | Severity | Detail |
|---|---|---|
| Single-booking reservation without lock | **Medium** | `CaregiverBookingService::withSiblingLock()` only acquires `lockForUpdate` for groups. Single bookings bypass the lock entirely — two caregivers could concurrently reserve the same booking. |
| Caregiver status TOCTOU | **Medium** | `AdminBookingService::replaceCaregiver()` checks `status = Active` outside the transaction, then assigns inside it. Caregiver could pause between check and assignment. |

---

## File Upload Failures

### Upload Endpoints (7 total)

| # | Controller | Field(s) | Storage |
|---|---|---|---|
| 1 | `CaregiverApplicationController::submit()` | `personal.photo` | `photos/` |
| 2 | `CaregiverApplicationController::submit()` | `cpr_card` | `cpr-cards/` |
| 3 | `CaregiverApplicationController::submit()` | `trustline_upload` | `trustline-uploads/` |
| 4 | `CaregiverController::update()` | `profile_photo` | `profile-photos/` |
| 5 | `CaregiverController::update()` | `cert_files.*` | `certifications/` |
| 6 | `CaregiverController::updateProfilePhoto()` | `profile_photo` | `profile-photos/` |
| 7 | `ClientController::updateProfilePhoto()` | `profile_photo` | `profile-photos/` |

### Test Gaps

| Gap | Severity | Detail |
|---|---|---|
| Wrong MIME type rejection | **High** | No test sends `.pdf` for `profile_photo` (should be rejected by `image` rule). No test sends `.txt` for `cpr_card`. |
| File exceeds size limit | **High** | No test uploads oversized files — `personal.photo` > 5MB, `cpr_card` > 10MB, `profile_photo` > 1MB/2MB, `cert_files.*` > 5MB. |
| Certification file uploads (`cert_files.*`) | **High** | The `cert_files.*` upload in `CaregiverController::update()` has zero test coverage — neither validation rules nor the storage flow. |
| Storage failure in application submit | **High** | `CaregiverApplicationController::submit()` calls `$photo->store()`, `$cprCard->store()`, `$trustlineUpload->store()` — return values never checked. Disk failure silently loses uploaded documents. |
| Storage failure in caregiver update | **High** | `CaregiverController::update()` calls `storeAs()` for `profile_photo` and `cert_files.*` — return values never checked. |
| Corrupt / empty file upload | **Medium** | No test sends a 0-byte file or a corrupt image (e.g., `.jpg` with garbage bytes). |
| Photo resize failure fallback | **Medium** | `CaregiverApplicationController` catches ImageManager resize failure, logs a warning, and continues with the original — this fallback path is untested. |
| Profile photo update has dedicated endpoint + tests | ✅ | `CaregiverController::updateProfilePhoto()` and `ClientController::updateProfilePhoto()` check `$path === false` and have feature tests. |
| Photo resize success path | ✅ | `CaregiverApplicationTest` verifies file exists and width <= 1200px. |

---

## Notification / Queue Failures

### Queue Setup

| Setting | Value |
|---|---|
| `.env` active | `QUEUE_CONNECTION=sync` (development) |
| `.env.example` | `QUEUE_CONNECTION=database` |
| Horizon | Not installed |
| Failed jobs table | Exists in schema |

### Notification Coverage Summary

| Status | Count | Notifications |
|---|---|---|
| Fully tested | 10 | `BookingAccepted`, `BookingCreated`, `BookingInvitation`, `BookingReminder`, `BookingReceipt`, `BookingCancelled`, `GuestAccountSetup`, `PaymentFailed`, `AdminNewApplication`, `AdminCaregiverBackedOut`, `ReferenceCompleted` |
| Partially tested | 1 | `BookingReviewReminder` |
| **Untested** | **3** | `ClientPaymentSmsReminder`, `ClientGroupBookingCreated`, `AdminCaregiverArchived` |
| Dead code | 1 | `AdminGroupBookingCreated` — class exists but never instantiated |

### Listener Coverage

| Status | Listeners |
|---|---|
| Fully tested | 7 of 8 |
| **Untested** | `SendBookingGroupCreatedNotifications` — handles `BookingGroupCreated` event, zero test coverage |

### Job Coverage

| Job | Status | Detail |
|---|---|---|
| `NotifyCaregiversJob` | **Untested** | Dispatched from `AdminBookingService`; has `tries=3, backoff=10s` but no `failed()` method. Zero tests. |
| `RetryJobCharge` | Partial | Delay escalation tested; `failed()` method untested |
| `SendBroadcastMessage` | Partial | Dispatch tested; `failed()` method untested |

### Other Gaps

| Gap | Severity | Detail |
|---|---|---|
| `SendBookingCancelledNotifications` is synchronous | **Medium** | All 7 other listeners implement `ShouldQueue` — this one doesn't. Intentional or oversight? |
| No listener `failed()` methods | **Low** | None of the 8 listeners implement `failed()` for permanent job failure handling. |
| `Bus::fake()` never used | **Low** | Tests use `Notification::fake()` extensively (61 times) but never `Bus::fake()` for job dispatch verification. |

---

## Webhook Endpoints

### Stripe Webhooks

| Gap | Severity | Detail |
|---|---|---|
| Webhook signature validation failure | **High** | `StripeWebhookHandler` catches `SignatureVerificationException` but no test simulates an invalid signature — the catch block's response JSON and status code are untested. |
| Webhook payload validation | **High** | No test sends a malformed JSON payload to the webhook endpoint — should return `400` with a safe error message. |
| Idempotency — duplicate webhook delivery | **High** | Stripe may deliver the same webhook twice. No test verifies that processing a duplicate `payment_intent.succeeded` or `payment_intent.payment_failed` event doesn't double-charge or corrupt booking state. |
| Unsupported event type | **Medium** | No test for what happens when Stripe sends an event type the app doesn't handle — should return `200` (acknowledge receipt) without crashing. |
| Webhook happy path | **Medium** | `StripeWebhookHandler` and `StripeWebhookController` have **zero feature tests** — neither successful payment confirmation, nor payment failure handling is tested at the HTTP layer. |

### Twilio Webhooks

| Gap | Severity | Detail |
|---|---|---|
| Twilio signature verification | **High** | `BroadcastSmsController` has `abort(401, 'Invalid Twilio signature')` but no test verifies this is triggered on invalid signatures. |
| Twilio status callback | **Medium** | If Twilio sends SMS delivery status updates (delivered, failed, undelivered), no test verifies these are handled gracefully. |

---

## Architecture Tests

### Existing Coverage

`tests/Arch/ArchitectureTest.php` has 11 tests covering:
- Models extend `Model`
- Controllers extend base `Controller`
- Form Requests extend `FormRequest`
- Middleware has `handle()` method
- Factories extend base `Factory` / Seeders extend base `Seeder`
- Enums are backed enums
- Policies have `viewAny()` method
- No `DB::` calls in controllers
- No `env()` calls in `App` namespace
- Tests extend `TestCase`

### Gaps

| Gap | Severity | Detail |
|---|---|---|
| `dd()` in production code | **Critical** | `BookingServiceFactory.php:15` has a live `dd('No authenticated user found.')` |
| Facade usage beyond `DB` | **High** | 7 controllers use `Cache`, `Log`, `Mail`, `Notification`, `Storage` — only `DB` is banned by existing arch test. |
| `StoreReviewRequest` missing `authorize()` | **High** | 1 of 5 form requests without `authorize()` is a security gap — accepts everything by default. |
| No `declare(strict_types=1)` | **Medium** | All 228 PHP files in `app/` lack strict types. |
| No `final` service classes | **Medium** | 21 service classes — none declared `final`. |
| Dead routes / controller methods | **Low** | `ChargeBookingController` and `ChargingController` routes are commented out. `PricingRuleController::search()` has no route. |
| Missing namespace arch tests | **Low** | Mail (23), Notifications (18), Jobs (3), Events (15), Listeners (9), Commands (23) — 70+ classes with no architectural enforcement. |

---

## Error Handling Infrastructure

### What Exists Now

| Component | Status |
|---|---|
| Custom error pages (`resources/views/errors/`) | **None** |
| Inertia error component (`resources/js/pages/errors/`) | **None** |
| `bootstrap/app.php` exception handler | **Empty** — `withExceptions(function (Exceptions $exceptions): void { // })` |
| `Route::fallback()` | **None** |
| `APP_DEBUG` | **`true`** (shows full stack traces) |

### Gap Analysis

| Issue | Severity | Detail |
|---|---|---|
| No custom error pages | **High** | Users see raw Laravel/Symfony error pages for 403/404/500/503 |
| No Inertia error rendering | **High** | Error pages are plain HTML, breaking SPA experience (no navigation) |
| Empty exception handler | **High** | All exceptions fall through to Laravel defaults |
| No fallback route | **Medium** | Completely unmatched URLs produce default 404 |
| `APP_DEBUG=true` | **Medium** | Full stack traces shown to users with source code |
| `firstOrFail()` in route model binding | **Medium** | Invalid booking IDs produce raw 404 without friendly page |
| Inconsistent 403 handling | **Low** | Some use `abort(403)`, others redirect with flash |

---

## Exception Message Leaks

Several `catch (\Exception $e)` blocks flash `$e->getMessage()` directly to users, leaking internal error details:

| File | Approx Line | Pattern |
|---|---|---|
| `ClientController.php` | ~358 | `->with('error', $e->getMessage())` |
| `CaregiverPayoutController.php` | ~44 | `->with('error', $e->getMessage())` |

These should be replaced with generic user-safe messages and the actual exception logged server-side via `Log::error()`.

---

## Recommendations

### Immediate (infrastructure — prerequisite for meaningful tests)

1. Create Inertia error page component (`resources/js/pages/errors/error-page.tsx`) for 403/404/500/503
2. Configure `bootstrap/app.php` to render it in production (`$exceptions->respond()`)
3. Handle 419 with `back()->with([...])`
4. Add `Route::fallback()` for truly unmatched URLs
5. Sanitize `$e->getMessage()` flashes
6. Add `Log::error()` where exceptions are caught and surfaced

### High Priority (business logic)

7. Fix `dd()` call in `BookingServiceFactory.php:15` — ships to production
8. Add status guard to `AdminBookingService::cancel()` — prevent cancelling Completed/Paid bookings
9. Add status guard to `JobController::checkout()` — prevent checkout of non-Confirmed bookings
10. Add null guards before `$booking->caregiver->recalculateRating()` in `JobController::rate()` and `BookingReviewController`
11. Add null guards before `$booking->client->first_name` in `JobController::show()` and `AdminBookingService::show()`
12. Add max bounds to tip/reimbursement validation rules
13. Add enum validation to `StoreBookingRequest` status field
14. Fix `StoreReviewRequest` — add `authorize()` method or gate check
15. Add arch test banning all facades in controllers (`Cache`, `Log`, `Mail`, `Notification`, `Storage`)
16. Add arch test for `declare(strict_types=1)` across `app/`
17. Fix storage return-value checks in `CaregiverApplicationController::submit()` and `CaregiverController::update()`
18. Test wrong MIME type and oversized file upload rejection

### Medium Priority (test additions)

19. Test 500 error scenarios via mocked Stripe/database failures
20. Test `abort(401)` Twilio signature verification in `BroadcastSmsController`
21. Test `abort(400)` in `JobController` for rating non-completed jobs
22. Test payment/billing exception paths (`TipChargeService`, `JobBillingService`, `CaregiverPayoutService`)
23. Test 419 session expiry
24. Test 404 from route model binding with invalid booking IDs
25. Test untested notifications: `ClientPaymentSmsReminder`, `ClientGroupBookingCreated`, `AdminCaregiverArchived`
26. Test `NotifyCaregiversJob` dispatching and retry logic
27. Test `SendBookingGroupCreatedNotifications` listener
28. Test `RetryJobCharge::failed()` and `SendBroadcastMessage::failed()` methods
29. Test empty-state UI for caregiver bookings, admin applications, client/caregiver lists
30. Test duplicate-submission guard on guest booking payment flow
31. Fix or remove skipped `ChargingControllerTest`
32. Add arch tests for Mail, Notifications, Jobs, Events, Listeners, Commands namespaces
33. Audit whether `SendBookingCancelledNotifications` should implement `ShouldQueue`
34. Test webhook signature validation failure paths (Stripe + Twilio)
35. Test webhook idempotency — duplicate event delivery doesn't corrupt booking state
36. Test refund partial/full/failure scenarios
37. Test cascade delete behavior — ensure no orphaned records after User deletion
38. Test graceful handling of orphaned bookings (missing client/caregiver)

### Low Priority

39. Declare all service classes `final`
40. Remove or wire up dead `AdminGroupBookingCreatedNotification`
41. Clean up commented-out charge routes or restore them
42. Add listener `failed()` methods for permanent job failure

---

## Future Considerations

Areas not yet assessed but recommended for follow-up:

### Scheduled Commands

| Area | Why |
|---|---|
| Console command idempotency | Commands like `SendPaymentSmsReminders`, `SendReviewReminders`, `ArchiveLongTermInactive` should be tested for safe re-execution (no duplicate notifications, no double-archiving) |
| Empty-database behavior | Scheduled commands should handle an empty database gracefully (no caregivers to archive, no reviews to send) |
| Large-dataset performance | Commands processing hundreds/thousands of records should be tested for memory and time limits |

### Timezone Edge Cases

| Area | Why |
|---|---|
| DST transitions | Bookings spanning daylight saving time changes (spring forward loses an hour, fall back gains an hour) — untested |
| Cross-timezone bookings | Client in one timezone, caregiver in another — display times should be tested for consistency |
| Midnight boundary bookings | Bookings starting/ending at exactly midnight in different timezones |

### Search Edge Cases

| Area | Why |
|---|---|
| SQL injection attempts | Search inputs passed directly to `LIKE` queries — should be tested for malicious input |
| Special characters | Searches containing `%`, `_`, quotes, emoji, non-Latin characters |
| Very long search queries | Search strings exceeding reasonable length — should not cause query errors or page crashes |

### Third-Party Service Outages

| Service | Test Scenario |
|---|---|
| SendGrid | Email delivery failure — does the app queue a retry or notify the user? |
| Twilio | SMS delivery failure — is the sender notified? Is there a fallback? |
| Stripe | API timeout/unavailability — are pending payments handled gracefully? |
| Google Maps | Geocoding/address validation failure — does the app degrade gracefully? |
