# Caregiver Availability Reservation Plan

## Problem

When a caregiver is assigned to a booking (`caregiver_id` set on `Booking`), their `Availability` records are never modified. The recommendation system sees them as available and may recommend them for other bookings on the same day and time — creating double-booking risk.

There is no existing mechanism for this. The `Availability` model is simple (`caregiver_id`, `date`, `time_slots` as JSON array) with no blocking, usage, or reservation tracking.

## Solution: Option A — `booking_availability_slots` Table

A new table tracks which time slots of a caregiver's availability are consumed by which booking. The recommendation service subtracts used slots from available slots. No existing data is mutated — clean, restorable, auditable.

---

## Files to Create

### 1. Migration

`database/migrations/YYYY_MM_DD_HHMMSS_create_booking_availability_slots_table.php`

```php
Schema::create('booking_availability_slots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
    $table->foreignId('caregiver_id')->constrained();
    $table->foreignId('availability_id')->constrained();
    $table->date('date');
    $table->string('time_slot'); // 'morning', 'afternoon', or 'evening'
    $table->timestamps();

    $table->unique(['booking_id', 'date', 'time_slot'], 'booking_slot_unique');
    $table->index(['availability_id', 'date']);
});
```

- `cascadeOnDelete` on `booking_id` — if a booking is force-deleted, slot records are cleaned up automatically.
- Unique constraint per `(booking_id, date, time_slot)` prevents duplicate reservations.
- `availability_id` index for the recommendation service query.

### 2. Model

`app/Models/BookingAvailabilitySlot.php`

```php
class BookingAvailabilitySlot extends Model
{
    protected $fillable = [
        'booking_id',
        'caregiver_id',
        'availability_id',
        'date',
        'time_slot',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function availability(): BelongsTo
    {
        return $this->belongsTo(Availability::class);
    }
}
```

### 3. Service

`app/Services/CaregiverRecommendation/AvailabilityReservationService.php`

```php
class AvailabilityReservationService
{
    /**
     * Reserve time slots for a booking on the caregiver's availability.
     *
     * For each date in the booking's date range, determines which time slots
     * overlap (morning/afternoon/evening) and creates a BookingAvailabilitySlot
     * record for each.
     */
    public function reserve(Booking $booking): void
    {
        if (! $booking->caregiver_id) {
            return;
        }

        $dateSlots = TimeSlotHelper::getRequiredTimeSlots(
            $booking->start_datetime,
            $booking->end_datetime,
        );

        foreach ($dateSlots as $date => $slots) {
            $availability = Availability::where('caregiver_id', $booking->caregiver_id)
                ->where('date', $date)
                ->first();

            if (! $availability) {
                continue;
            }

            foreach ($slots as $slot) {
                BookingAvailabilitySlot::firstOrCreate([
                    'booking_id' => $booking->id,
                    'caregiver_id' => $booking->caregiver_id,
                    'availability_id' => $availability->id,
                    'date' => $date,
                    'time_slot' => $slot,
                ]);
            }
        }
    }

    /**
     * Release all reserved time slots for a booking.
     */
    public function release(Booking $booking): void
    {
        BookingAvailabilitySlot::where('booking_id', $booking->id)->delete();
    }
}
```

### 4. Utility — Extracted Time Slot Logic

`app/Services/CaregiverRecommendation/TimeSlotHelper.php`

Extracts the `getRequiredTimeSlots()` method from the recommendation service into a shared static utility:

```php
class TimeSlotHelper
{
    /**
     * Determine required time slots for a date range.
     *
     * Each date maps to morning (06:00-12:00), afternoon (12:00-18:00),
     * and/or evening (18:00-23:00) based on the booking's start/end times.
     *
     * @return array<string, string[]> e.g. ['2026-06-10' => ['morning', 'afternoon']]
     */
    public static function getRequiredTimeSlots(
        string|DateTimeInterface $startDate,
        string|DateTimeInterface $endDate,
    ): array {
        // Same logic as current CaregiverRecommendationService::getRequiredTimeSlots()
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        $requiredSlots = [];

        $current = clone $start;
        while ($current <= $end) {
            $dateKey = $current->format('Y-m-d');
            $slots = [];

            $dayStart = ($current->format('Y-m-d') === $start->format('Y-m-d'))
                ? $start
                : new \DateTime($dateKey.' 00:00:00');

            $dayEnd = ($current->format('Y-m-d') === $end->format('Y-m-d'))
                ? $end
                : new \DateTime($dateKey.' 23:59:59');

            $morningStart   = new \DateTime($dateKey.' 06:00:00');
            $morningEnd     = new \DateTime($dateKey.' 12:00:00');
            $afternoonStart = new \DateTime($dateKey.' 12:00:00');
            $afternoonEnd   = new \DateTime($dateKey.' 18:00:00');
            $eveningStart   = new \DateTime($dateKey.' 18:00:00');
            $eveningEnd     = new \DateTime($dateKey.' 23:00:00');

            if ($dayStart < $morningEnd && $dayEnd > $morningStart) {
                $slots[] = 'morning';
            }
            if ($dayStart < $afternoonEnd && $dayEnd > $afternoonStart) {
                $slots[] = 'afternoon';
            }
            if ($dayStart < $eveningEnd && $dayEnd > $eveningStart) {
                $slots[] = 'evening';
            }

            $requiredSlots[$dateKey] = $slots;
            $current->modify('+1 day');
        }

        return $requiredSlots;
    }
}
```

The recommendation service replaces its protected `getRequiredTimeSlots()` with `TimeSlotHelper::getRequiredTimeSlots()`.

---

## Files to Modify

### 5. `app/Models/Availability.php`

Add relationship for eager-loading used slots in the recommendation service:

```php
public function usedSlots(): HasMany
{
    return $this->hasMany(BookingAvailabilitySlot::class);
}
```

### 6. `app/Models/Booking.php` — `booted()` `saved` Hook

Extend the existing saved hook to handle reservation/release alongside the existing `CaregiverAssignment` tracking:

```php
static::saved(function (Booking $booking) {
    $reservationService = app(\App\Services\CaregiverRecommendation\AvailabilityReservationService::class);

    // Handle caregiver_id changes (admin assign, unassign, reassign)
    if ($booking->wasChanged('caregiver_id')) {
        // Release old caregiver's slots
        if ($oldCaregiverId = $booking->getOriginal('caregiver_id')) {
            $reservationService->release($booking);
        }

        // Reserve new caregiver's slots
        if ($booking->caregiver_id) {
            $reservationService->reserve($booking);
        }
    }

    // Handle cancellation — release slots if a caregiver was assigned
    if ($booking->wasChanged('status') && $booking->status === 'cancelled' && $booking->caregiver_id) {
        $reservationService->release($booking);
    }

    // Existing CaregiverAssignment tracking (unchanged)
    if ($booking->wasChanged('caregiver_id') && $booking->caregiver_id) {
        $booking->assignments()->firstOrCreate(
            ['caregiver_id' => $booking->caregiver_id],
            ['assigned_at' => now()],
        );
    }
});
```

**Covers these admin paths:**
| Action | Event | Effect |
|---|---|---|
| Create booking with caregiver | `Booking::create()` → saved | `reserve()` |
| Assign caregiver to existing booking | `$booking->update()` → saved | `release()` old, `reserve()` new |
| Remove caregiver from booking | `caregiver_id = null` → saved | `release()` |
| Cancel booking (status = cancelled) | `$booking->update()` → saved | `release()` (if caregiver_id set) |
| Reassign from one caregiver to another | `caregiver_id = 5 → 8` → saved | `release()` old, `reserve()` new |

### 7. `app/Services/Booking/CaregiverBookingService.php` — `confirm()`

Caregiver self-confirm uses `DB::table()->update()` which bypasses Eloquent events. After the raw update and after clearing the notification status, load the fresh sibling bookings and reserve slots explicitly:

```php
// After the DB::table()->update() call and notification update...

// Reserve availability slots for confirmed assignments
// (raw DB update bypasses Eloquent saved hook)
$freshBookings = Booking::whereIn('id', $siblingIds)->get();
$reservationService = app(AvailabilityReservationService::class);
foreach ($freshBookings as $freshBooking) {
    $reservationService->reserve($freshBooking);
}
```

### 8. `app/Services/Booking/AdminBookingService.php` — `splitGroup()`

After the raw `Booking::whereIn()->update()` that sets `caregiver_id` to null, load the extracted bookings and release their slots:

```php
// After the raw update...

// Release availability slots for split-off bookings
// (raw DB update bypasses Eloquent saved hook)
$extractedBookings = Booking::whereIn('id', $extractedIds)->get();
$reservationService = app(AvailabilityReservationService::class);
foreach ($extractedBookings as $extractedBooking) {
    $reservationService->release($extractedBooking);
}
```

### 9. `app/Services/CaregiverRecommendation/CaregiverRecommendationService.php` — `hasAvailabilityForBooking()`

Subtract used slots from available slots when checking coverage:

```php
// Add eager loading for used slots
$availabilities = $caregiver->availabilities()
    ->with('usedSlots')
    ->where(function ($query) use ($bookingDates) {
        foreach ($bookingDates as $date) {
            $query->orWhereDate('date', $date);
        }
    })
    ->get();
```

Then in the per-date check loop, replace the raw `$availableSlots` with effective slots:

```php
// Previously:
// $coveredSlots = array_intersect($requiredSlots, $availableSlots);

// New: Subtract used slots
$usedSlotNames = $availability->usedSlots
    ->where('date', $date)
    ->pluck('time_slot')
    ->toArray();

$effectiveSlots = array_diff($availableSlots, $usedSlotNames);

if (empty($effectiveSlots) && ! empty($requiredSlots)) {
    return false;
}

$coveredSlots = array_intersect($requiredSlots, $effectiveSlots);
```

Also replace the call to `$this->getRequiredTimeSlots()` with `TimeSlotHelper::getRequiredTimeSlots()`.

The `TimeSlotHelper::getRequiredTimeSlots()` should also accept `DateTimeInterface` in addition to `string`, since `Booking.start_datetime`/`end_datetime` are Carbon instances (cast as `datetime`). The `new \DateTime(...)` constructor accepts DateTimeInterface objects.

---

## Calendar Visualization

### Three Visual States

The availability calendar currently shows only two states: "available" (icons rendered) or "not set" (blank/—). With booking reservation, we need three states:

| State | Looks like | Meaning |
|---|---|---|
| **Available** | Colored icons (yellow Sunrise, teal Sun, blue Moon) | Caregiver set this slot, no booking conflict |
| **Booked** | Same icons, but muted/gray with `opacity-30` | Caregiver set this slot, but a booking occupies it |
| **Not set** | Blank (—) | Caregiver never set availability here |

This ensures the caregiver can distinguish "I set morning but it's booked" from "I never set morning." They won't mistakenly re-add a booked slot.

### Booked Dates Are Not Clickable

When ALL time slots for a date are booked (i.e., `booked_slots` covers all of `time_slots`), the date should **not** be clickable — no "Add" or "Edit" overlay button appears. The caregiver/admin cannot edit availability for a fully booked date.

If only SOME slots are booked (e.g., `morning` is booked but `afternoon` is free), the date remains clickable for editing the free slots.

### Backend Changes

**In `AdminAvailabilityService::show()`, `AdminAvailabilityService::index()`, and `DashboardController`:**

Each availability query must eager-load `usedSlots` and map `booked_slots` into the response shape:

```php
$availabilities = $caregiver->availabilities()
    ->with('usedSlots')
    ->inTheFuture()
    ->orderBy('date')
    ->limit(32)
    ->get()
    ->map(function ($availability) {
        return [
            'id' => $availability->id,
            'date' => $availability->date->format('Y-m-d'),
            'time_slots' => $availability->time_slots,
            'booked_slots' => $availability->usedSlots
                ->pluck('time_slot')
                ->unique()
                ->values()
                ->toArray(),
            'specific_time' => $availability->specific_time,
        ];
    });
```

**Modified files:**

| File | Change |
|---|---|
| `app/Http/Controllers/DashboardController.php` | Add `->with('usedSlots')`, include `booked_slots` in map |
| `app/Services/Availability/AdminAvailabilityService.php` | Same in `show()`, plus in `index()` for the table view |

The `AdminAvailabilityService::index()` loads caregivers with their availabilities. Since `usedSlots` is on the `Availability` model, the eager load needs to be nested:

```php
'availabilities' => function ($q) {
    $q->with('usedSlots')->inTheFuture()->orderBy('date');
},
```

### Frontend Changes

**1. `Availability` interface — add `booked_slots` in all four files:**

| File | Change |
|---|---|
| `resources/js/components/availability-calendar.tsx` | Add `booked_slots?: string[]` |
| `resources/js/pages/admin/availabilities/index.tsx` | Add `booked_slots?: string[]` |
| `resources/js/pages/admin/availabilities/show.tsx` | Add `booked_slots?: string[]` |
| `resources/js/pages/dashboard/caregiver.tsx` | Add `booked_slots?: string[]` |

**2. `availability-calendar.tsx` — icon rendering and clickability:**

```typescript
const isBooked = (slot: string) => availability?.booked_slots?.includes(slot);
const allBooked = availability && availability.time_slots.length > 0
    && availability.time_slots.every(slot => isBooked(slot));
```

- Slot icons: if `isBooked(slot)`, render the same icon with `className="opacity-30"` (or a gray color) and a tooltip like "Booked".
- Date clickability: if `allBooked`, do not render the "Add"/"Edit" overlay button and set `cursor-default` instead of `cursor-pointer`.

```tsx
// In the day cell rendering:
const availability = availabilityMap[dateStr];
const hasAvailability = availability && availability.time_slots.length > 0;
const hasAnyFreeSlot = availability && availability.time_slots.some(
    slot => !availability.booked_slots?.includes(slot)
);

// Render icons — booked slots muted
{getSortedTimeSlots(availability.time_slots).map((slot) => (
    <span key={slot} className="flex items-center">
        {React.cloneElement(getIcon(slot), {
            className: isBooked(slot) ? 'opacity-30' : '',
        })}
    </span>
))}

// Only show clickable overlay if there's at least one free slot
{!isPast && !isToday && hasAnyFreeSlot && (
    <button onClick={() => onDateClick(dateStr)} ...>
        {hasAvailability ? 'Edit' : 'Add'}
    </button>
)}
```

**3. `admin/availabilities/index.tsx` — table icon rendering:**

```tsx
// In the date cell, after checking time_slots:
{av.time_slots.map((slot) => (
    <span key={slot} className="flex items-center">
        {React.cloneElement(getIcon(slot), {
            className: av.booked_slots?.includes(slot) ? 'opacity-30' : '',
        })}
    </span>
))}
```

---

## Edge Cases Covered

| Scenario | Handled By |
|---|---|
| Admin creates booking with caregiver | `Booking::create()` → saved hook → `reserve()` |
| Admin assigns caregiver to existing booking | `$booking->update()` → saved hook → release old, reserve new |
| Admin removes caregiver | `caregiver_id = null` → saved hook → `release()` |
| Admin cancels booking | `status = 'cancelled'` → saved hook → `release()` |
| Caregiver self-confirms | Raw DB update → explicit `reserve()` in `confirm()` |
| Admin splits booking group | Raw DB update → explicit `release()` in `splitGroup()` |
| Reservation expires (status = 'reserved') | No `caregiver_id` set → no slot records exist → no action needed |
| CleanupExpiredReservations command | Only clears `reserved_by`, doesn't touch `caregiver_id` → no action needed |
| Caregiver backs out | Only resolves `CaregiverAssignment`, doesn't change `caregiver_id` → booking still assigned → no action needed. See `docs/caregiver-backout-gaps.md` for gaps in the admin UI around backout visibility. |
| Soft-delete booking | FK `cascadeOnDelete` only fires on force-delete; soft-delete leaves slot records intact (status-based filtering covers this) |
| Admin changes booking dates | `start_datetime`/`end_datetime` change: saved hook watches `wasChanged('start_datetime')`/`wasChanged('end_datetime')` and calls `release()` then `reserve()`. **Resolved.** |
| Multi-date sibling bookings | Each sibling `Booking` fires its own saved hook → each reserves its own date's slots |
| Two bookings on same date, different slots | Each booking reserves only its overlapping slots. `Booking B` (afternoon) leaves `Booking A`'s `morning` slot intact. |

### Gap: Date Changes (Resolved)

The saved hook now watches `wasChanged('start_datetime')` / `wasChanged('end_datetime')` and calls `release()` then `reserve()` when dates change (see `Booking.php:250-255`). The code is already in place:

```php
if ($booking->caregiver_id && (
    $booking->wasChanged('start_datetime') || $booking->wasChanged('end_datetime')
)) {
    $reservationService->release($booking);
    $reservationService->reserve($booking);
}
```

---

## Tests

### New test file or extend `RecommendationServiceTest.php`

| # | Test | Assertion |
|---|---|---|
| 1 | Assigning a caregiver creates slot records | `BookingAvailabilitySlot::count()` matches expected |
| 2 | Removing caregiver deletes slot records | Count = 0 after removal |
| 3 | Cancelling a booking deletes slot records | Count = 0 after status change to cancelled |
| 4 | Recommendation excludes used slots | Caregiver available on date with `morning`+`afternoon`, assigned to booking using only `morning`. Recommendation for `morning` booking shows no `available` icon. Recommendation for `afternoon` booking shows `available` icon. |
| 5 | Multi-date sibling bookings reserve each date | 2 sibling bookings each with different dates → 2 slot records |
| 6 | Self-service confirm path | Simulate full reserve → confirm flow, verify slot records exist |
| 7 | Date change on assigned booking | Change start/end dates, verify old slots released and new slots reserved |
| 8 | splitGroup releases slots | Move booking to new group, verify slots released |

---

## Summary of All Changes

| File | Action |
|---|---|
| `database/migrations/*_create_booking_availability_slots_table.php` | Create |
| `app/Models/BookingAvailabilitySlot.php` | Create |
| `app/Services/CaregiverRecommendation/AvailabilityReservationService.php` | Create |
| `app/Services/CaregiverRecommendation/TimeSlotHelper.php` | Create |
| `app/Models/Availability.php` | Add `usedSlots()` HasMany |
| `app/Models/Booking.php` | Extend `booted()` saved hook |
| `app/Services/Booking/CaregiverBookingService.php` | Add `reserve()` calls in `confirm()` |
| `app/Services/Booking/AdminBookingService.php` | Add `release()` calls in `splitGroup()` |
| `app/Services/CaregiverRecommendation/CaregiverRecommendationService.php` | Use `TimeSlotHelper`, subtract used slots, update `getRequiredTimeSlots()` call |
| `app/Http/Controllers/DashboardController.php` | Add `usedSlots` eager load, include `booked_slots` |
| `app/Services/Availability/AdminAvailabilityService.php` | Add `usedSlots` eager load, include `booked_slots` |
| `resources/js/components/availability-calendar.tsx` | Add `booked_slots` to interface, muted icons, disable click on fully booked |
| `resources/js/pages/admin/availabilities/index.tsx` | Add `booked_slots` to interface, muted icons in table |
| `resources/js/pages/admin/availabilities/show.tsx` | Add `booked_slots` to interface |
| `resources/js/pages/dashboard/caregiver.tsx` | Add `booked_slots` to interface |
| `tests/Feature/Caregiver/AvailabilityReservationTest.php` | Create |
