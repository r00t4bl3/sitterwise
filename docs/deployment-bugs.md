# Sitterwise Bug Triage — Rows 148–160

**Date:** July 1, 2026 · **Source:** Bug Log rows 148–160 (all Open) · **Code:** bitbucket.org/r00t4bl3/sitterwise @ master (read-only clone)

Every bug was traced to specific code. Summary of who/what is needed:

| # | Bug | Severity | Root cause found | Fixable in code | Needs prod DB / Aji |
|---|-----|----------|------------------|-----------------|---------------------|
| 148 | Job links 404 (numeric ID vs ULID) | CRITICAL | Yes | ✅ DONE | Deploy + verify prod binder |
| 149 | Bare 404/403 on job pages | High | Yes | Yes | Deploy |
| 150 | Dashboard "today" in UTC | High | Yes | Yes | Deploy |
| 151 | 12,600 "pending" reviews | High | Yes | Yes | Deploy (no email risk — verified) |
| 152 | "83 unassigned" inflated | Medium | Yes | Yes + data repair | SQL repair on prod |
| 153 | Child records named "None" | Medium | Yes | ✅ DONE | Run cleanup on prod |
| 154 | No Cancel for unassigned bookings | Medium | Yes | ✅ DONE | Deploy |
| 155 | Accept button cut off on mobile | CRITICAL | Yes | ✅ DONE | Deploy |
| 156 | Cancel doesn't stick / reminder sent | High | Yes (3 defects) | ✅ DONE | Deploy |
| 157 | Payment Confirm silently no-ops | CRITICAL | Yes | ✅ DONE | 1 DB lookup to confirm #14459 |
| 158 | No payment feedback / double-charge risk | High | Yes | ✅ DONE | Deploy; test money path |
| 159 | Edit Booking blank white screen | High | Yes (reproduced) | ✅ DONE | Deploy |
| 160 | Transactions sorted by ID | Low | Yes | Yes (1-line) | Deploy |

---

## 148 — Caregiver job links 404 (CRITICAL) — ✅ RESOLVED

**Fix applied:** SMS (`BookingInvitationNotification.php:71`) and email (`booking-notification.blade.php:126`) both emit `route('jobs.short', $booking)` → `/j/{id}`. Decision: use the short route with its default numeric key (not ULID). Dead hardcoded `/bookings/available/{id}` CTA removed from the email. Resolves via the committed dual binder in `Booking::resolveRouteBinding` (numeric + ULID). Tests: binder + SMS link already covered; added an email-render test (`BookingInvitationNotificationTest`) asserting the short link is present and the dead route is absent.

⚠️ **Post-deploy check:** open a numeric `/j/<id>` on prod. If it still 404s, prod is running the old ULID-only binder — deploy didn't pick up `Booking.php`.

**Root cause (two problems):**
1. SMS "View & claim" link: `app/Notifications/BookingInvitationNotification.php:71` uses `route('jobs.short', $this->booking)` — the Booking model's default route key is the numeric row ID, so links come out as `/j/467`.
2. Email "View & Accept Booking" button: `resources/views/emails/booking-notification.blade.php:126` hardcodes `/bookings/available/{id}` — that route is **commented out** in `routes/web.php:115-116`. The email button 404s for every booking, old or new.

Note: the current code's `Booking::resolveRouteBinding` (Booking.php:131-138) accepts both numeric IDs and ULIDs, yet production 404s on numeric — production may be running an older ULID-only binder. Worth confirming what's deployed.

**Fix:** pass `->ulid` explicitly in both places (plus `SearchController.php:71`); point the email button at `route('jobs.short', $booking->ulid)`; keep the dual binder so old numeric links in caregivers' phones still work. Do NOT set a global `getRouteKeyName()` — it would flip every admin URL too.

## 149 — Bare 404 / 403 on job pages (High)

- Admin 403: `JobController.php:98-102` aborts with "Caregiver profile not found" for any user without a caregiver profile. Fix: redirect admins to the admin booking view.
- No "job already filled" state exists anywhere. **Worse (privacy leak):** an invited caregiver opening an already-claimed job sees the full detail page *including the client's phone and email* (`JobController.php:104-155`). Fix: claimed-by-someone-else → render a friendly "This job has already been filled" page.
- No Inertia error pages are registered at all (`bootstrap/app.php` withExceptions is empty) — all errors are raw Symfony pages. Fix: register friendly 403/404/500 pages.

## 150 — Dashboard "today" computed in UTC (High)

`config/app.php:68` sets timezone UTC; `scopeInToday` (`app/Models/Booking.php:443-446`) uses `now()->startOfDay()` — after 5 PM Pacific "today" is tomorrow. Same bug in `scopeInFuture` (line 438), the calendar month window (`AdminBookingService.php:73-74`), the monthly export window (`AdminBookingService.php:1252-1256`), and dashboard month/YTD stats.

**Fix:** compute all boundaries in `America/Los_Angeles`, convert to UTC for queries. One pattern, five call sites. (Availability code already does this correctly — the Booking scopes are the outliers.)

## 151 — 12,600 "pending" reviews (High)

`DashboardController.php:271-273` counts every completed booking ever without a rating — including the entire migrated Bubble history. Star breakdown (lines 274-280) uses exact integer equality but ratings are decimals (4.5 matches no bucket → 414 ≠ 430).

**Email-blast safety: VERIFIED SAFE.** The only review automation (`SendReviewReminders`, every 6h) is strictly windowed to bookings that ended 2–26h ago (email) / 48–72h ago (SMS). The backlog can never be mailed.

**New issue found while verifying:** that reminder command has no "already sent" flag — a normal booking can get up to ~4 duplicate review emails and ~4 SMS. Recommend a `review_reminder_sent_at` column. *(Suggest adding as bug #161.)*

**Fix:** scope pending count to eligible bookings (e.g. exclude rows with `bubble_id`); bucket the star breakdown by range/rounding.

## 152 — "83 unassigned" (Medium)

`DashboardController.php:94-98` counts `whereNull('caregiver_id')` + status received/pending + `inFuture()`. Two gaps: (1) the Bubble import maps any unrecognized status ("canceled", "cancelled by client"…) to **Received** while still setting `cancelled_at` (`ImportUserService.php:1320-1330`) — cancelled future bookings count as unassigned; (2) `inFuture()` has the UTC bug (#150).

**Fix:** add `->whereNull('cancelled_at')` (here and in `bookingsNeedingAttention`), fix `inFuture`, plus one-time repair: `UPDATE bookings SET status='cancelled' WHERE cancelled_at IS NOT NULL AND status IN ('received','pending')`. Verify first with the matching SELECT.

## 153 — Children named "None" (Medium) — ✅ RESOLVED

**Fix applied:**
- **Cleanup command:** `php artisan app:cleanup-junk-children [--dry-run] [--purge]`. Soft-deletes `client_children` rows with a junk name (`None`/`N/A`/`no kids`/… blocklist) and no birth data; strips junk + null entries from `booking_groups.children` JSON (and empties a group whose `children` is a junk string) via **direct `DB::table` updates to bypass the auto-repricing observer**. `--dry-run` reports counts without writing. `--purge` permanently force-deletes matching rows **including ones already soft-deleted by a prior run** (recycle-bin sweep) — targeted to the same junk criteria, so unrelated soft-deleted children are left alone. **→ run on prod: `--dry-run`, then default (soft-delete), verify, then `--purge`.**
- **Stop recurrence at import:** `ImportUserService::parseChildren` now treats a negation text as "no children" (no more `Child 2`/`Child 3` padding to a fake count) and filters junk parts; `parseChildEntry` rejects junk names. Shared `ImportUserService::isJunkChildName()` used by both parsers and the command.
- **Petsit-with-child root cause:** `StoreBookingRequest` now exempts `petsitter` and `companion_care` (alongside `group_childcare_invoiced`) from the "≥1 child" rule.
- **Latent SMS bug:** `BookingInvitationNotification::childAge` guards `birth_year: 0` (and empty birth_date/age) with `! empty(...)`, so it no longer reports an age of ~2026.

Tests: `CleanupJunkChildrenTest` (client_children + group JSON + junk-string + dry-run), `ImportChildParsingTest` (parser filters), `Admin/BookingTest` (petsitter allowed / babysitter still requires a child), `BookingInvitationNotificationTest` (birth_year 0). Full suite green.

Import-time only; no current path creates children from free text. `ImportUserService::parseChildren()` (lines 1969-1992) accepts any text fragment as a child name and pads with "Child 2", "Child 3" to match the kid count. The pets parser directly below it has a "none/no/n/a" filter — it was just never applied to children. Same issue in `parseChildEntry()` (line 1261) for client profiles.

**Why a petsit had a child at all:** `StoreBookingRequest` requires ≥1 child for every non-group service **including petsitter**. Fix: exempt petsitter/companion_care.

SMS counts junk children directly (`BookingInvitationNotification.php:55-69`); emails even print "None" as the children summary. Latent bonus bug: `birth_year: 0` makes the SMS compute **age 2026**.

**Fix:** cleanup artisan command with `--dry-run` (junk-name blocklist + no birth data), covering both `client_children` rows and `booking_groups.children` JSON. For historical bookings use direct DB updates to bypass auto-repricing observers.

## 154 — No Cancel for unassigned bookings (Medium) — ✅ RESOLVED

**Fix applied:** split the single JSX gate in `admin/bookings/show.tsx`. The `status !== 'cancelled'` fragment now always renders **Cancel Booking**; **Replace Caregiver** keeps its `caregiver_id` check (can't replace a caregiver that isn't assigned). Backend cancel already handled the no-caregiver case (assignment resolution is skipped when there's no unresolved assignment). Test: `BookingCancellationTest` "admin can cancel an unassigned booking".

Note on the "exclude paid bookings" consideration: left as-is. A paid+unassigned booking is practically impossible (payout needs a caregiver), and paid+assigned bookings already showed Cancel before this change — so this fix doesn't newly expose the money-zeroing risk. Worth a separate ticket if you want to block cancelling already-charged bookings outright.

One JSX condition: `admin/bookings/show.tsx:1073` gates both Replace Caregiver AND Cancel behind `booking.caregiver_id`. Backend cancel already works without a caregiver (`AdminBookingService.php:966-995`). **Fix:** split the condition — Replace keeps the caregiver check, Cancel only needs `status !== 'cancelled'`. Consider whether paid bookings should be excluded (cancel zeroes all money fields).

## 155 — Accept button invisible on portrait phones (CRITICAL) — ✅ RESOLVED

**Fix applied:**
- `min-w-0` on both grid panels + `min-w-0`/`break-all` (email) and `min-w-0`/`break-words` + `shrink-0` icons (address) on `caregiver/bookings/show.tsx` and `caregiver/jobs/show.tsx` — long unbreakable strings can no longer force the column past a portrait viewport.
- Accept button is now a sticky, full-width bottom bar on mobile (with `env(safe-area-inset-bottom)` padding), reverting to an inline right-aligned auto-width button on `sm:` and up. No longer pinned to the clipped right edge.
- **Bonus finding fixed:** `/j/{id}` (and `/jobs/{id}`) landed invited caregivers on a read-only page with no Accept button. `JobController::show` now redirects invited-but-unassigned caregivers to the accept page (`bookings.show`) when the booking is still claimable. No loop: the accept page only redirects once confirmed, at which point `caregiver_id` is set. Tests: `tests/Feature/Caregiver/JobShowTest.php` (redirect via `jobs.show` + `jobs.short`, assigned stays, unauthorized 403).

Left the layout's `overflow-x-hidden` in place (kept per triage) — the `min-w-0`/break fixes prevent the overflow at the source.

Three interacting causes on `caregiver/bookings/show.tsx`:
1. Layout clips overflow (`app-sidebar-layout.tsx:15` `overflow-x-hidden`) — wide content is invisible, not scrollable.
2. Grid children lack `min-w-0`; long unbreakable strings (client email, full-address maps link) force the column wider than a portrait viewport. Landscape fits — matching the symptom exactly.
3. The Accept button is `justify-end` — pinned to the clipped right edge (lines 609-610).

**Fix:** `min-w-0` + `break-words` on the offending elements, and make Accept a sticky full-width bottom bar on mobile (with iOS safe-area padding). Same treatment on `caregiver/jobs/show.tsx`.

**Bonus finding:** the `/j/{id}` page (`caregiver/jobs/show.tsx`) has **no accept button at all** — SMS links land invited caregivers on a read-only page. Leah's "not sure what changed but I can see it now" is consistent with bouncing between the two pages. Recommend `/j/` redirect unassigned invited caregivers to the accept page.

## 156 — Cancel doesn't stick, reminder still sent (High) — ✅ RESOLVED

**Fix applied (all three defects):**
- **A. Reminder race:** `SendBookingReminderNotifications` now reloads the booking (`->fresh()`) and skips unless it's still `confirmed` — and, for bookings that track assignments, unless an unresolved assignment still exists. A cancel landing between the hourly dispatch and the queued job no longer sends a reminder. (Legacy bookings without assignment rows are gated by status alone, so imports aren't silently skipped.)
- **B. Multi-day groups:** `AdminBookingService::cancel` accepts `cancel_group`. When set, every active sibling in the `booking_group` is cancelled in one transaction (money zeroed + assignment resolved per row), with a `BookingCancelled` event dispatched per booking. The admin cancel dialog shows a "This is a multi-day booking. Also cancel the other N date(s)…" checkbox whenever the group has active siblings. `cancel_group` added to `CancelBookingRequest`.
- **C. Sync notifications:** `SendBookingCancelledNotifications` now `implements ShouldQueue`, so a failing client/caregiver/admin notification can't surface an error for a cancel that already committed.

Tests: `BookingReminderTest` (skips cancelled, sends confirmed, listener queued); `BookingCancellationTest` (group cancel hits all siblings, single cancel leaves siblings confirmed, cancel listener queued). Adjusted a stale `BookingNotificationTest` helper (booking was `Received` but reminders only fire for `confirmed`). Full suite: 1228 passed.

Current cancel code is atomic (status + resolution in one transaction), but three real defects explain the symptoms:
- **A. Reminder race:** `SendBookingReminders` (hourly) filters `status='confirmed'` at dispatch; the queued listener sends **without re-checking** — a booking cancelled between dispatch and queue processing still gets the reminder. Fix: re-check status in the listener + exclude resolved assignments in the query.
- **B. Multi-day groups:** cancel affects only the single booking row; sibling rows in the group stay confirmed — on the schedule, sending reminders, needing a second cancel. This best explains "had to cancel twice." Fix: offer group-wide cancel in the dialog.
- **C.** Cancellation notifications run synchronously — if one throws, the admin sees an error for a cancel that actually succeeded. Fix: make the listener queued.

## 157 — Payment Confirm silent no-op (CRITICAL) — ✅ RESOLVED

**#14459 DB lookup (Aji, prod):** `charge_to_client_hourly = 35`, `total_working_hour = 4` → computed service charge = **$140 (> 0)**. So the `total_service_amount <= 0` guard was **NOT** the blocker for #14459 — the charge either went through silently or Stripe errored and the flash was dropped. Root cause confirmed to be the **missing feedback surface**, not the $0 guard.

**Fix applied (all on the feedback layer):**
- Mounted `<ToasterMessage />` on `admin/transactions/index.tsx` — backend `flash.error`/`flash.success` from `processPayment` now surface as toasts (previously dropped on ~this one page).
- Added an `onError` to the Confirm submit: closes the confirm dialog so the toast is visible, keeps the sheet open for retry (no more silent no-op).
- Defensive $0 guard: when computed service charge (service + reimbursement + bonus) is `≤ 0`, the Process/Confirm buttons are disabled and an inline warning explains why (covers the null-pricing/legacy-import class the triage described).
- Surfaced the previously-dropped `warning`/`info` flash types: `HandleInertiaRequests` now shares `warning`+`info` (was success/error only) and `ToasterMessage` renders them. This makes the `processPayment` "payment charged but caregiver payout failed" **warning** visible everywhere.

Tests: `tests/Feature/Middleware/InertiaSharedFlashTest.php` (warning/info + success/error shared). Existing `BookingPaymentIntegrationTest` already covers the backend success/error flash. Frontend feedback is visual — verify by processing a payment on staging.

Two-layer failure on `admin/transactions/index.tsx`:
- Frontend: `handlePaymentSubmit` (lines 188-200) silently `return`s if `selectedBooking` is null; Confirm is disabled while a prior request is in flight — both produce "nothing happens, no request."
- **The page renders no feedback at all:** `<ToasterMessage />` is mounted on ~40 pages but NOT this one, so backend flash errors are silently dropped. A failed charge is visually identical to no click.
- Backend most-likely abort for #14459: `JobBillingService.php:62-69` rejects `total_service_amount <= 0` — a null `charge_to_client_hourly` (no pricing rule match / legacy import) yields $0. **Confirm with:** `SELECT charge_to_client_hourly, total_working_hour FROM bookings WHERE id=14459;`

**Fix:** mount ToasterMessage + render form errors; show a warning/disable Confirm when computed charge is $0; surface the backend flash as a visible error.

## 158 — No feedback + double-charge risk (High) — ✅ RESOLVED

**Fix applied** (both charge stacks — `ChargingController` and `AdminBookingService::processPayment` — funnel through `JobBillingService::charge()`, so hardening that one method covers both):
- **Atomic claim under a row lock** (`claimForCharge`): a `lockForUpdate` transaction re-checks `payment_status` and sets an intermediate `charging` status before any Stripe call. A second concurrent confirm sees `charging`/`charged` and bails **before** touching Stripe. A `charging` claim older than 2 min is treated as abandoned (crash mid-charge) and may be retried, so bookings can't get stuck.
- **Stripe idempotency key** on `paymentIntents->create`: `booking_{id}_charge_{attempt}` — collapses duplicate requests (network retries, races) into one charge; `handleFailure` bumps the attempt count so a legitimate retry after a decline gets a fresh key.
- **Already-charged check moved to the top of `processPayment`**: bails before mutating reimbursement/tip, so a stray re-submit can't overwrite the figures that were actually charged (`ChargingController` already guarded first).
- Toasts/feedback: done under #157.

Tests: `tests/Feature/Billing/JobBillingServiceDoubleChargeTest.php` (idempotency key passed; in-flight `charging` never reaches Stripe; already-charged rejected; stale claim retried — Stripe mocked via reflection injection) + `BookingPaymentIntegrationTest` new "already charged → no mutate/charge" case. Full existing payment suite (Charging/Integration/Retry/Tip/EndToEnd) still green.

⚠️ **Deploy note:** `payment_status` now takes a transient `charging` value. Any dashboard/report that filters on `payment_status` should treat `charging` as in-progress (not paid/failed). Verify the money path on staging.

Spinners exist, but no success/error surface (see 157). **Double-charge risk is real:** `processPayment` (`AdminBookingService.php:1355-1426`) mutates reimbursement/tip BEFORE the already-charged check; the check is read-then-act with no lock and **no Stripe idempotency key** (`JobBillingService.php:93`). Two concurrent confirms → two live Stripe charges.

**Fix:** (1) row-lock + re-check + intermediate `charging` status; (2) pass an `idempotency_key` to `paymentIntents->create` — low-risk, high-value; (3) move the already-charged check to the top; (4) toasts. Note: two parallel charge stacks exist (ChargingController vs processPayment) with different guards — consolidating them would prevent recurrence.

## 159 — Edit Booking blank white screen (High) — ✅ RESOLVED

**Fix applied:**
- **Backend (the actual 500):** `Booking`'s `children`/`pets`/`sitter_preferences`/`special_considerations` accessors (in `HasGroupFields`) declared a strict `: array` return but passed the group's raw value through — a legacy string value → `TypeError` on `$booking->toArray()`, killing the JSON that feeds the edit sheet. Now coerced with `is_array(...) ? ... : []`, so any read (`toArray`, SMS, email) is safe. Also guarded the null-client crash in `AdminBookingService::show` (`if ($booking->client)` around `setRelation`; a soft-deleted client made the `HasOneThrough` null) and normalized `booking_group.children/pets` in the JSON payload.
- **Frontend (defense-in-depth so a bad shape degrades gracefully):** `personal-info-section.tsx` now builds `Array.isArray`-guarded, null-filtered `groupChildren`/`groupPets` for the summary. `StatusBadge` is null-safe (accepts `status: string | null | undefined` and missing `bookingStatuses`, shows `UNKNOWN`). Added a reusable `ErrorBoundary` (`components/error-boundary.tsx`) wrapping the edit-sheet content so any future render throw shows an in-panel message instead of blanking the page (the app previously had zero error boundaries).

Tests: `BookingEditSheetTest` (show json survives removed client; string children → `[]`; null child entries filtered). Full suite: 1232 passed.

**Empirically reproduced** by mounting the component against reconstructed Vernee Martin data. Not a React #185 loop — a plain render throw, and the app has **zero error boundaries**, so any throw blanks the whole page. Confirmed crashes in `personal-info-section.tsx:226-253`: children stored as a string → `.map is not a function`; a null entry in the array → reading `.name` of null. Backend also 500s on group bookings with no client (`AdminBookingService.php:422`).

**Fix:** `Array.isArray` guards + null filtering in the three render sites; null-safe `StatusBadge`; an error boundary around the sheet content (converts future crashes into an in-panel message); backend: `$booking->client?->setRelation(...)` and normalize children in the API response. The #153 cleanup also repairs existing trigger data.

## 160 — Transactions sort (Low)

`TransactionController.php:37` uses `->latest()` (created_at) — all migrated rows share the import-run timestamp, so MySQL tie-breaks by ID. **Fix (1 line):** `->orderByDesc('start_datetime')`.

---

## Extra issues discovered during triage (not in the log)

1. **Privacy leak:** claimed-job pages show the client's phone/email to any invited caregiver (see #149).
2. **Duplicate review reminders:** up to ~4 emails + 4 SMS per booking (see #151).
3. **No Stripe idempotency key** on payment intents (see #158).
4. **Dead email CTA route:** every "View & Accept Booking" email button 404s today (see #148).
5. `/j/{id}` page has no accept button (see #155).

## Recommended fix order

1. **#148 + #155** — caregivers can't reach or tap Accept; directly costs filled jobs.
2. **#157 + #158** — unbilled bookings + double-charge risk (money).
3. **#156 + #154** — cancellation integrity (caregivers showing up to cancelled jobs).
4. **#159 + #153 cleanup** — unblocks editing bookings.
5. **#150/151/152/160** — dashboard trust.
