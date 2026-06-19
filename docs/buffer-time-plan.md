# Caregiver Booking Buffer Time Plan

## Problem

When a caregiver has multiple bookings on the same day, there's no gap enforced between them. A caregiver could be assigned to a booking ending at 10:00 AM and another starting at 10:05 AM — with no time to travel between locations.

The current slot-based conflict system (half-day blocks: morning/afternoon/evening) is too coarse to handle this. It also produces false positives: two bookings like 8-10 AM and 10:30-12 PM both need the "morning" slot, so they're blocked — even though they don't overlap in clock time.

## Solution: Time-Based Conflict Check With Buffer

Add a globally configurable buffer via environment variable (`CAREGIVER_BUFFER_MINUTES`, default 60) and a time-based overlap check that runs alongside the existing slot check.

### How it works

When `hasAvailabilityForBooking()` evaluates a new booking for a caregiver:

1. **Slot check** (existing) — coarse pre-filter: does the caregiver have the required half-day slots?
2. **Time + buffer check** (new) — precise filter: do any of the caregiver's existing bookings on the same date conflict with the new booking's time range, accounting for buffer?

A conflict exists when:

```
newStart < existingEnd + buffer AND newEnd > existingStart - buffer
```

| Existing booking | New booking | Buffer | Result |
|---|---|---|---|
| 8:00–10:00 | 10:30–12:00 | 60 min | ❌ Conflict (10:00 + 1:00 = 11:00 > 10:30) |
| 8:00–10:00 | 11:00–12:00 | 60 min | ✅ No conflict (10:00 + 1:00 = 11:00 = 11:00) |
| 8:00–10:00 | 10:15–12:00 | 30 min | ❌ Conflict (10:00 + 0:30 = 10:30 > 10:15) |
| 8:00–10:00 | 10:30–12:00 | 30 min | ✅ No conflict (10:00 + 0:30 = 10:30 ≤ 10:30) |
| 8:00–10:00 | 9:30–11:00 | any | ❌ Conflict (direct overlap) |

## Files to Create / Modify

### 1. `config/caregiver.php` — Create

```php
return [
    'buffer_minutes' => env('CAREGIVER_BUFFER_MINUTES', 60),
];
```

### 2. `.env.example` — Add

```
CAREGIVER_BUFFER_MINUTES=60
```

### 3. `app/Services/CaregiverRecommendation/CaregiverRecommendationService.php`

#### 3a. Load existing bookings in a single batched query

In `getRecommendedCaregivers()`, before the `map()` loop, load all existing confirmed/received bookings for all relevant caregivers on the same dates:

```php
$bufferMinutes = (int) config('caregiver.buffer_minutes');

$caregiverIds = $allCaregivers->pluck('id');

$existingBookingsByCaregiver = Booking::whereIn('caregiver_id', $caregiverIds)
    ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Received])
    ->where(function ($q) use ($bookingDates) {
        foreach ($bookingDates as $date) {
            $q->orWhere(fn ($sub) => $sub
                ->whereDate('start_datetime', '<=', $date)
                ->whereDate('end_datetime', '>=', $date)
            );
        }
    })
    ->orderBy('start_datetime')
    ->get()
    ->groupBy('caregiver_id');
```

Pass `$existingBookingsByCaregiver` and `$bufferMinutes` into `computeAttributes()`.

#### 3b. Add buffer to the time check

In `hasAvailabilityForBooking()`, accept the existing bookings collection and buffer minutes:

```php
protected function hasAvailabilityForBooking(
    Caregiver $caregiver,
    array $dateRanges,
    Collection $existingBookings,
    int $bufferMinutes,
): bool {
    // ... existing slot check (unchanged) ...

    // New: time-based buffer check
    $caregiverBookings = $existingBookings[$caregiver->id] ?? collect();

    foreach ($dateRanges as $range) {
        $newStart = new \DateTime($range['start']);
        $newEnd = new \DateTime($range['end']);

        foreach ($caregiverBookings as $existing) {
            $existingStart = new \DateTime($existing->start_datetime);
            $existingEnd = new \DateTime($existing->end_datetime);

            $bufferedStart = (clone $existingStart)->modify("-{$bufferMinutes} minutes");
            $bufferedEnd = (clone $existingEnd)->modify("+{$bufferMinutes} minutes");

            if ($newStart < $bufferedEnd && $newEnd > $bufferedStart) {
                return false;
            }
        }
    }

    return true;
}
```

### 4. DB index (optional but recommended)

Add a composite index for the batched query:

```php
Schema::table('bookings', function (Blueprint $table) {
    $table->index(['caregiver_id', 'status', 'start_datetime', 'end_datetime'], 'bookings_cg_status_time_idx');
});
```

Performance impact: the batched query filters by `caregiver_id IN (...)` + `status IN (...)` + date overlap across multiple `bookingDates`. Without this index, the DB scans the bookings table. With the index, it's an index seek on caregiver → status → date range.

## Query Cost Analysis

| Step | Queries | Notes |
|---|---|---|
| Load caregivers + relationships | 1 | Exists, unchanged |
| Load previous work IDs | 1–2 | Exists, unchanged |
| **Load existing bookings** | **+1** | **New — single batched query** |
| Slot + usedSlots check | Per-caregiver | Exists, unchanged |
| **Time + buffer check** | **0** | **In-memory on pre-loaded collection** |

**1 additional query** regardless of caregiver count.

### Memory cost
~200 bytes per booking record × ~5 bookings × 100 caregivers = ~100 KB. Negligible.

## Frontend

Minimal changes — `buffer_minutes` is a backend-only concept that the recommendation service uses. However, the admin booking sheet/edit UI could surface it:

- Add a "Travel buffer" number input in the booking group form (default 30)
- Tooltip: "Minutes between bookings for caregiver travel"

## Edge Cases

| Scenario | Behavior |
|---|---|
| **Buffer = 0** | Pure time-overlap check — back-to-back allowed |
| **Buffer = 30, 2-min gap** | ❌ Blocked (2 < 30) |
| **Buffer = 30, 31-min gap** | ✅ Allowed (31 ≥ 30) |
| **Same date, different half-day slots** | Buffer still applies — e.g., 10 AM booking and 2 PM booking with 30 min buffer → no conflict (gap is 4 hours) |
| **Multi-day bookings** | Buffer applies per date — checked against any existing booking overlapping each date |
| **No existing bookings** | Time check passes trivially |
| **Caregiver unassigned (caregiver_id = null)** | No bookings to check → passes |
| **Booking status not confirmed/received** | Ignored — cancelled/completed bookings don't consume buffer |
| **booking_groups without buffer_minutes** | Falls back to migration default (30) — handled by column default |
| **Buffer across midnight** | Rare but handled: a booking ending at 23:30 with 30-min buffer checks against next day's 00:00 bookings. The time check naturally accounts for cross-midnight. |

## Test Coverage to Add

| # | Test | Assertion |
|---|---|---|
| 1 | Buffer blocks too-close booking | New booking within buffer of existing → not available |
| 2 | Buffer allows sufficiently distant booking | New booking outside buffer → available |
| 3 | Buffer=0 allows back-to-back | Zero buffer, adjacent bookings → available |
| 4 | Only confirmed/received bookings count | Cancelled booking does not block |
| 5 | Buffer applies across same client bookings | Two bookings for same client still respect buffer |
| 6 | Existing booking's caregiver_id changed | After caregiver change, old time slot freed; new one reserved w/ buffer |
| 7 | Multi-date buffer check | Existing booking on date 1 blocks new booking on date 1 but not date 2 |
