# Manual Testing Guide

## Prerequisites

```bash
# Verify config
php artisan tinker --execute="echo config('caregiver.buffer_minutes');"

# Quick DB inspection
php artisan tinker --execute="\App\Models\BookingAvailabilitySlot::with(['booking', 'caregiver'])->get()->toArray()"
```

---

## 1. Availability Reservation (Slot Booking)

### 1.1 Booking assignment (admin)

1. As admin, create a new booking with a caregiver assigned
2. Verify `booking_availability_slots` table has records for each overlapping time slot
3. Check that the caregiver's availability calendar now shows muted/gray icons for the booked slots

```bash
php artisan tinker --execute="\App\Models\BookingAvailabilitySlot::all()"
```

### 1.2 Unassign caregiver

1. Remove the caregiver from the booking (set `caregiver_id` to null)
2. Verify `booking_availability_slots` records are deleted
3. Check recommendation page — caregiver shows `available` icon again for that time slot

### 1.3 Cancel booking

1. Cancel an assigned booking (status → `cancelled`)
2. Verify slots are released
3. Caregiver shows `available` in recommendations for that time

### 1.4 Date change on assigned booking

1. Change `start_datetime` / `end_datetime` on a booking that has a caregiver assigned
2. Old slot records released, new ones created for the new date range

### 1.5 Caregiver self-confirm

1. As caregiver, self-confirm a booking (calls `CaregiverBookingService::confirm()`)
2. Slots are reserved explicitly in the raw DB update path (bypasses Eloquent)

### 1.6 splitGroup

1. Admin splits a booking group via `AdminBookingService::splitGroup()`
2. Extracted bookings have their slots released

---

## 2. Recommendation Scoring

### 2.1 Match icons

| Criteria | Icon | Condition |
|---|---|---|
| Favorited | `favorited` | Client favorited this caregiver |
| Available | `available` | Caregiver has open slots AND respects buffer |
| Specialty | `specialty` | Matches service type or sitter preference |
| Location (preferred) | `location_preferred` | Booking area is caregiver's preferred location |
| Location (willing) | `location_willing` | Booking area is caregiver's non-preferred location |
| Recent work | `recent_work` | Worked for any client within 6 months |
| Previous work | `previous_work` | Worked for this client before |

### 2.2 Verifying score weights

1. Create a caregiver who matches all criteria: available, specialty, preferred location, favorited, recent work
2. Expected score: `100000 + 10000 + 1000 + 100 + 3 = 111103` (favorited + available + specialty + preferred location + recent work 3mo)
3. Open the admin booking sheet recommendation panel — caregivers sorted by score descending

---

## 3. Buffer Time Between Bookings

Default buffer: **60 minutes** (configurable via `CAREGIVER_BUFFER_MINUTES` in `.env`)

### 3.1 Buffer blocks close booking

1. Create caregiver A with availability: morning + afternoon + evening
2. Assign caregiver A to booking: **5–6 PM** on June 15
3. Check recommendations for a **6:30–7 PM** booking on June 15
4. Caregiver A should show **no `available` icon** (buffer: 6 PM + 60 min = 7 PM > 6:30 PM)

### 3.2 Buffer allows distant booking

1. Same setup as 3.1
2. Check recommendations for a **7:01–8 PM** booking on June 15
3. Caregiver A should show **`available` icon** (7:01 PM ≥ 7 PM buffer boundary)

### 3.3 Buffer does not block different date

1. Same caregiver A with booking 5–6 PM on June 15
2. Check recommendations for a 6:30–7 PM booking on **June 16**
3. Caregiver A shows `available` (different date, buffer irrelevant)

### 3.4 Cancelled booking is excluded from buffer

1. Cancel caregiver A's 5–6 PM booking on June 15
2. Check recommendations for a 6:30–7 PM booking on June 15
3. Caregiver A shows `available` (cancelled bookings excluded from buffer check)

### 3.5 Unassign removes buffer

1. Remove caregiver A from the 5–6 PM booking on June 15 (`caregiver_id = null`)
2. Check recommendations for a 6:30–7 PM booking on June 15
3. Caregiver A shows `available` (no caregiver assignment → no buffer)

### 3.6 Multiple bookings additive

1. Caregiver A has bookings: 8–10 AM AND 11 AM–1 PM on June 15
2. Check recommendations for 1:30–2 PM booking on June 15
3. Both existing bookings contribute to buffer separately:
   - 1 PM + 60 min = 2 PM → 1:30 PM < 2 PM → blocked

---

## 4. Calendar Visualization (Frontend)

### 4.1 Three visual states

Open the caregiver availability calendar (admin or dashboard):

| State | Appearance | Meaning |
|---|---|---|
| Available | Colored icons (yellow Sunrise, teal Sun, blue Moon) | Caregiver set this slot, no booking conflict |
| Booked | Same icons, muted/gray (`opacity-30`) | Caregiver set this slot, but a booking occupies it |
| Not set | Blank (—) | Caregiver never set availability for this date |

### 4.2 Fully booked dates not clickable

- When ALL time slots for a date are booked, the date should not be clickable
- No "Add" or "Edit" overlay button appears
- Partially booked dates remain clickable (only free slots editable)

### 4.3 Admin availability table

At `admin/availabilities/index`, each caregiver's row should show:
- `opacity-30 grayscale` icons for booked slots
- Normal colored icons for free slots

---

## 5. Edge Cases

### 5.1 Multi-date sibling bookings

1. Create a booking group with 2+ bookings on different dates
2. Assign a caregiver → each date's overlapping slots are reserved independently

### 5.2 Booking spanning slot boundaries

1. Booking from **11 AM–5 PM** → requires both `morning` and `afternoon` slots
2. Both half-day blocks are reserved
3. Recommendation for a new booking checks both slots are free

### 5.3 Buffer=0 (no buffer)

If `CAREGIVER_BUFFER_MINUTES=0`, back-to-back bookings are allowed. Only the slot-based check applies:
- 8–10 AM and 10–11 AM on same day → allowed (no buffer, same "morning" slot but time-based overlap... still blocked by slot reservation)

---

## Quick DB Inspection Commands

```bash
# All slot records
php artisan tinker --execute="\App\Models\BookingAvailabilitySlot::with(['booking','caregiver'])->get()->toArray()"

# Slots for a specific caregiver
php artisan tinker --execute="\$cg = \App\Models\Caregiver::find(1); \$cg->availabilities()->with('usedSlots')->get()->toArray()"

# Existing bookings for buffer check (for a specific caregiver)
php artisan tinker --execute="\App\Models\Booking::whereIn('status', ['confirmed', 'received'])->where('caregiver_id', 1)->get(['id', 'start_datetime', 'end_datetime', 'status'])->toArray()"

# Verify buffer config
php artisan tinker --execute="echo config('caregiver.buffer_minutes');"
```
