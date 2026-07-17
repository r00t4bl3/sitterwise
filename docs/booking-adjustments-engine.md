# Booking Adjustments Engine — deferred design (build later)

> **Status: NOT built. Deferred by design.**
> The Lifesaver +$15 bonus is live and now **display-only itemized** (a derived
> `Booking::lifesaver_bonus` accessor + fee lines on the admin booking detail and caregiver
> job detail). This document is the roadmap for the generic, extensible reward-bonus engine.
> **Trigger to build:** when a concrete *second* reward type is specified — its requirements
> (see §5) finalize the schema. Do not build it against the single lifesaver case.

## 1. Why (the problem)

Reward bonuses are currently modeled as fixed columns / inline logic. Each new reward
(referral, holiday, streak, gamification payout, …) would otherwise mean **another migration
+ another edit to `Booking::calculateTotalAmount()` + another itemized UI line + another set
of tests** — repeated in the most delicate money code.

Model bonuses as **data**, not columns: a `booking_adjustments` table. Then a new reward type
is **one inserted row** — no schema change, no billing-math change, no UI change, and it
itemizes itself everywhere fees are shown.

## 2. Design

### Table — `booking_adjustments`
| column | notes |
|---|---|
| `id` | |
| `booking_id` | FK → bookings, `cascadeOnDelete` |
| `type` | e.g. `lifesaver_bonus`, `referral_bonus` |
| `label` | display text, e.g. "Lifesaver bonus" |
| `amount` | `decimal(10,2)` |
| `target` | `client_charge` \| `caregiver_pay` \| `both` |
| `metadata` | json, nullable (source/why/actor) |
| timestamps | |
| unique | `(booking_id, type)` — idempotent per type |

### Supporting code
- **`App\Enums\AdjustmentTarget`** — cases `ClientCharge`, `CaregiverPay`, `Both`, with
  `appliesToClient(): bool` / `appliesToCaregiver(): bool`.
- **`App\Models\BookingAdjustment`** — fillable; casts `amount => decimal:2`,
  `target => AdjustmentTarget`, `metadata => array`; `booking(): BelongsTo`.
- **`Booking::adjustments(): HasMany`**.

### Money math — `Booking::calculateTotalAmount()` (`app/Models/Booking.php:106-160`)
Replace the inline `$lifesaverBonus` (currently `wasLifesaverRescue() ? Settings::get(...)`)
with a generic sum:
- `+= sum(adjustments where target->appliesToClient())` into `total_service_amount`
- `+= sum(adjustments where target->appliesToCaregiver())` into `paid_to_caregiver_total`
- `sitterwise_cut` stays untouched.

Because it iterates rows, **any future reward flows through with zero calc changes**. The two
Stripe paths (`ChargingController.php:~76`, `AdminBookingService.php:~1674`) already derive
from `paid_to_caregiver_total`, so payouts pick it up automatically — no payout-service
change.

### Lifesaver = the first adjustment type (derived-from-state)
Most future rewards will be created explicitly (admin grants / a scheduled job inserts a
row). Lifesaver is special: it's *derived* from booking state, so it needs a syncer.
- In the existing `saved` hook (`Booking.php:271`, which already reacts to
  caregiver/status/date changes for reservations): call `syncLifesaverAdjustment()` —
  `wasLifesaverRescue()` → `updateOrCreate` the `lifesaver_bonus` row (amount from
  `Settings::get('lifesaver.bonus')`, target `Both`), else delete it.
- If it changed, recompute totals via **`saveQuietly()`** (fires no events → no recursion),
  guarded by a "did it actually change" check and **frozen once `status === Paid`**.

### Display
The shipped itemization already exists — `Booking::getLifesaverBonusAttribute()` and the fee
lines in `AdminBookingService::show`, `JobController::show`, `admin/bookings/show.tsx`,
`caregiver/jobs/show.tsx`. When the engine lands, switch these to source from adjustment rows
(iterate `adjustments` → one labeled line each) instead of the single derived accessor.

## 3. Live-billing safety (the critical part)

The bonus is **live in production**, so the migration must not change any existing total.

- **Residual backfill.** Existing bonused bookings have the amount baked into their totals but
  no row. Backfill each with
  `amount = total_service_amount − charge_to_client − reimbursement − bonus`
  (the exact residual already applied — confirmed against `calculateTotalAmount()`). Because
  the row equals what's already in the total, totals are **provably unchanged**, even for
  frozen/paid bookings where `lifesaver.bonus` was later edited. Make the backfill
  **idempotent** and **chunked**.
- **Parity tests.** Assert the new summation yields byte-identical `total_service_amount` /
  `paid_to_caregiver_total` / `sitterwise_cut` to today's code for: rescue, non-rescue,
  override on, override off, and paid-freeze.
- **Deploy order.** The migration (create table **+** backfill) must finish before the
  calc-change code serves traffic — otherwise a booking without its row loses the bonus on the
  next recompute. Standard deploys run migrations first, so a single coordinated release
  works.
  - **Safest = two releases:** (1) ship the table + backfill + the row-syncer as *shadow*
    data with `calculateTotalAmount()` **unchanged**; verify in prod that every row's amount
    matches the applied residual (a reconcile check). (2) Flip the calc to read the rows.
    This removes the risk window entirely.
- **Out of scope:** migrating the legacy `tip` / `reimbursement` / `bonus` columns into the
  table. Leave them until there's a concrete reason — that's a much larger, riskier rewrite
  for no immediate gain.

## 4. Tests (when built)
- Parity (new totals == old) for every lifesaver scenario.
- Backfill correctness: rows created == applied residuals; idempotent re-run; no total change.
- Syncer: new rescue gets a row; override→false removes it; frozen at Paid; **no recursion**
  (saveQuietly), idempotent.
- Generic summing: a `client_charge`-only adjustment hits only the client total; a
  `caregiver_pay`-only hits only the payout; `both` hits both.
- Itemization: each adjustment renders one labeled fee line on both detail pages.

## 5. Open questions the 2nd reward must answer

Answer these from the *real* second reward before finalizing the schema — this is why we
deferred (designing against one case risks the wrong abstraction):
- Per **booking** or per **caregiver** (or per booking-group)?
- Target: client charge, caregiver pay, or both?
- Flat amount, percentage, or capped?
- Who creates it: derived from booking state (like lifesaver), admin-granted, or a scheduled
  system job?
- Absorbed by Sitterwise vs billed to the client (affects `target` + whether `sitterwise_cut`
  should move)?
- Behavior on cancel / refund / reassignment (release, keep, prorate)?

## 6. Touch-points when built (pointers)
- `app/Models/Booking.php` — `calculateTotalAmount()`, `adjustments()`, `saved` hook syncer.
- New: migration + `BookingAdjustment` model + `AdjustmentTarget` enum + backfill command/migration.
- `app/Services/Booking/AdminBookingService.php::show`, `app/Http/Controllers/JobController.php::show`
  — source itemization from rows.
- `resources/js/pages/admin/bookings/show.tsx`, `resources/js/pages/caregiver/jobs/show.tsx`
  — render one line per adjustment.
- Tests as in §4.
