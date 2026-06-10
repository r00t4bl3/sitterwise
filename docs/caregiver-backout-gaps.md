# Caregiver Backout — Current Gaps

## Overview

The caregiver backout flow is partially implemented. A caregiver can back out via `POST /assignments/{id}/back-out`, which resolves their `CaregiverAssignment` to `BackedOut`, sends an email to admins, and recalculates reliability. However, several gaps exist in the admin-facing UI, automatic handling, and data consistency.

---

## Gap 1: Booking Detail Page Shows No Assignment Status

**File:** `resources/js/pages/admin/bookings/show.tsx` (lines 395–405)

The booking detail page displays the caregiver name as a clickable link, but it never shows the assignment resolution status (e.g., backed out, cancelled, reassigned). If a caregiver has backed out, an admin viewing the booking detail page has no indication of this — the caregiver name still appears as if they are actively assigned.

**Impact:** Admin cannot tell from the booking detail page whether the caregiver is still actively assigned or has backed out.

**Possible fix:** Load the assignment resolution for the booking's caregiver and display a `<StatusBadge>` or color-coded indicator next to the caregiver name. E.g., "Jane Doe (Backed Out)" or a red badge.

---

## Gap 2: No Auto-Resolve Old Assignment on Caregiver Change

**Files:**
- `app/Models/Booking.php` (lines 210–244)
- `resources/js/pages/admin/bookings/booking-details-section.tsx`

When an admin changes the caregiver via the booking edit sheet, `Booking::booted()` creates a new `CaregiverAssignment` for the new caregiver:

```php
if ($caregiverChanged && $booking->caregiver_id) {
    $booking->assignments()->firstOrCreate(
        ['caregiver_id' => $booking->caregiver_id],
        ['assigned_at' => now()],
    );
}
```

But the old assignment's `resolution` is **never automatically set** to `AssignmentResolution::Reassigned`. The admin must manually resolve it via the caregiver's job history page.

**Impact:** Old assignments accumulate as "unresolved," making it unclear which caregiver actually worked the job. The `Reassigned` resolution exists in the enum but has no automated trigger.

**Possible fix:** In the `booted()` saved hook, when `caregiver_id` changes, find the previous assignment and auto-resolve it to `Reassigned` with a note.

---

## Gap 3: No "Replace Caregiver" Flow from Booking Detail Page

**Files:**
- `resources/js/pages/admin/bookings/show.tsx`
- `resources/js/pages/admin/bookings/booking-details-section.tsx`
- `resources/js/pages/admin/caregivers/job-history.tsx`

To replace a backed-out caregiver, an admin currently must:
1. Navigate to the booking edit sheet
2. Change the caregiver (which creates a new assignment)
3. Separately navigate to the backed-out caregiver's job history page (`/caregivers/{id}/jobs`)
4. Manually excuse or resolve the old assignment

There is no dedicated "Replace Caregiver" action on the booking detail page that handles all steps atomically.

**Impact:** High friction for the common admin task of filling a booking after a backout. Steps are spread across two pages with no automation.

**Possible fix:** Add a "Replace Caregiver" action on the booking detail page that lets the admin select a new caregiver, auto-resolves the old assignment to `Reassigned`, and creates the new assignment — all in one flow.

---

## Gap 4: Booking `caregiver_id` Not Cleared on Backout

**File:** `app/Http/Controllers/AssignmentController.php` (lines 16–48)

The `backOut()` method only resolves the `CaregiverAssignment`. It does not:
- Clear `booking.caregiver_id`
- Change `booking.status`
- Update `booking.cancelled_at` or similar timestamp

The booking remains `confirmed` with `caregiver_id` still pointing to the caregiver who backed out.

**Impact:**
- The booking detail page continues to display the caregiver name as if they are assigned
- The booking risks falling through the cracks — no status change signals that action is needed
- Reports and counts may be inaccurate (confirmed bookings with no active caregiver)

**Possible fix:** On backout, set `booking.caregiver_id = null` and potentially change `booking.status` to `received` or add a new status like `needs_reassignment`. This is a design decision that must consider the caregiver-facing flow (the caregiver sees "Cancel Job" only on confirmed bookings).

---

## Gap 5: Admin Cancel Overwrites Backout Resolution

**File:** `app/Services/Booking/AdminBookingService.php` (lines 824–846)

When an admin cancels a booking via the dedicated cancel endpoint, the `cancel()` method resolves *any* unresolved assignment to `CancelledBySitterwise`:

```php
$assignment = $booking->assignments()->unresolved()->first();
if ($assignment) {
    $assignment->resolve(AssignmentResolution::CancelledBySitterwise);
}
```

If a caregiver had previously backed out (assignment resolution = `BackedOut`), the assignment is already resolved and this code won't touch it — so this gap is partially mitigated. However, if the booking has a **second** unresolved assignment (e.g., from a replacement caregiver), that assignment gets resolved to `CancelledBySitterwise` rather than tracking the reason it ended.

**Impact:** Minor — the `unresolved()` scope only targets unresolved assignments, so backed-out history is preserved. But any subsequent unresolved assignment gets attributed to "Cancelled by Sitterwise" even if the cancellation was triggered by the caregiver's backout.

**Possible fix:** Accept a reason in the cancel endpoint and use it to set the resolution context. Or, when cancelling a booking that has a backed-out caregiver, set the new assignment's resolution to `CancelledBySitterwise` with a note referencing the original backout.

---

## Gap 6: Email Template Typo

**File:** `resources/views/emails/admin-caregiver-backed-out.blade.php` (line 27)

The email template says:

> "From Job History you can **excise** the back-out..."

This should be **"excuse"** not "excise."

---

## Gap 7: Booking Date Change Doesn't Re-reserve Slots (Pre-existing)

**File:** `app/Models/Booking.php` (booted saved hook)

Already documented in `docs/availability-reservation-plan.md` (line 470). When an admin changes a booking's `start_datetime` or `end_datetime` without changing the caregiver, the `BookingAvailabilitySlot` records still reference the old dates. The saved hook doesn't watch for date changes.

**Note:** This gap is in the availability reservation system, not the backout flow, but is related since both involve caregiver assignment lifecycle.
