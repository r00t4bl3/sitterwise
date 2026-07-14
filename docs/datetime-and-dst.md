# Datetimes, timezones, and daylight saving time

How Sitterwise stores, displays, and measures booking times — and why it behaves
the way it does across daylight-saving-time (DST) transitions.

**TL;DR:** times are stored as UTC instants, displayed in Pacific, and durations
are computed as *true elapsed time*. This is DST-safe. A booking that spans a DST
change will have billed hours that differ from its displayed wall-clock span by an
hour — that is **correct**, not a bug. Do not "fix" it into a wall-clock subtraction.

## The three rules

### 1. Storage = UTC instants
`Booking::convertToUtc()` (`app/Models/Booking.php`) normalizes every datetime to a
UTC `Y-m-d H:i:s` string before it is stored:

- A string with a `Z` or `±HH:MM` offset is parsed as-is and converted to UTC.
- A **naive** string (no offset) is interpreted as `America/Los_Angeles`, then
  converted to UTC.

So `start_datetime`, `end_datetime`, `confirmed_at`, `cancelled_at`, etc. are all
absolute instants in the database. The frontend picker sends UTC (`Z`) strings, so
the naive branch is only hit by bare Pacific input (imports, tinker, raw SQL).

### 2. Display = business timezone (Pacific)
Read back, the cast gives a UTC `Carbon`; anything user-facing converts with
`->setTimezone('America/Los_Angeles')` (see `Booking::toEmailData()`,
`BookingGroup::toEmailData()`, the email views, and `resources/js/lib/datetime.ts`).

`App\Support\BusinessTime` (TZ `America/Los_Angeles`) exists for "today / this month"
window queries: it computes the boundary **in Pacific**, then `->utc()` before the
query hits a UTC column — otherwise the boundary lands on UTC midnight and is off by
up to a day (e.g. after ~5pm Pacific, `now()->startOfDay()` is already tomorrow).

### 3. Durations = true elapsed time
`Booking::calculateTotalWorkingHours()` uses `$start->diffInMinutes($end) / 60` on
the **UTC instants** — real elapsed time, never a subtraction of wall-clock fields.
Checkout (`JobController::checkout`) and the frontend (`resources/js/lib/datetime.ts`)
do the same on UTC / epoch milliseconds.

> **Do not change this to wall-clock math.** Subtracting displayed hours would
> over- or under-count by an hour whenever a booking spans a DST transition, and
> would silently mis-bill overnight jobs.

## DST edge cases

These are the only places DST is observable. The regression test
`tests/Feature/Booking/BookingDstDurationTest.php` pins the first one with real
numbers.

### A. A booking that spans a transition: billed hours ≠ displayed span
Because duration is true elapsed time, a job whose calendar times straddle a
transition has a duration that differs from the wall-clock span by an hour:

| Night | PT wall clock | UTC instants | `total_working_hour` |
|---|---|---|---|
| Spring-forward (Mar 9 2025, 2→3 AM) | 01:00 → 05:00 (reads 4 h) | 09:00 → 12:00 UTC | **3.0** (an hour is skipped) |
| Fall-back (Nov 2 2025, 2→1 AM) | 00:00 → 04:00 (reads 4 h) | 07:00 → 12:00 UTC | **5.0** (an hour repeats) |
| No transition (control) | 09:00 → 13:00 | 16:00 → 20:00 UTC | **4.0** |

A caregiver who works "11 PM → 5 AM" is physically present for 5 hours on
spring-forward night and 7 hours on fall-back night — so paying/billing the elapsed
value is **correct**, even though it doesn't match the "6 hours" the calendar shows.
If this ever generates a support question, the answer is "you were paid for the real
hours worked," not a code change.

### B. The 4-hour minimum is measured in real elapsed time
`App\Rules\MinimumBookingDuration` and the `Booking::calculateTotalAmount()` billing
floor both operate on true elapsed hours (driven by the `bookings.minimum_hours`
setting, default 4). Consequence: on **spring-forward night only**, an overnight
booking that reads 4 h on the calendar (e.g. 01:00 → 05:00) is really 3 h and would
be rejected by the minimum-duration rule. Billing would floor it to the minimum
anyway. This is extremely narrow (overnight bookings crossing 2 AM that one
weekend) and is left as-is by design.

### C. Non-existent / ambiguous naive local times
A naive Pacific string in the transition window is inherently under-specified:

- **Spring-forward gap** (`02:00`–`02:59` on the transition day): that wall-clock
  time does not exist. PHP rolls it forward past the gap.
- **Fall-back overlap** (`01:00`–`01:59`): that wall-clock time happens twice. PHP
  picks the pre-transition (earlier) offset.

Either way PHP resolves it deterministically — **no crash**, just a possible ±1 h
misinterpretation for those exact clock times. Only reachable via bare Pacific input
(imports / tinker / SQL); the picker always sends UTC, so normal app flows are
immune.

## Why this design is correct

Storing instants (UTC) and deriving everything else from them is the standard,
robust approach: instants are unambiguous, arithmetic on them is always real elapsed
time, and Pacific is applied only at the display edge. The alternative — storing or
computing in wall-clock local time — is what actually breaks at DST. The "surprises"
above are all display-vs-reality nuances, not correctness bugs.
