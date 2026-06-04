# Delete Booking Feature — Assessment

> A comprehensive analysis of effort, side effects, and edge cases for implementing a "delete booking" feature.

---

## Current State

**Already exists (70% done):**

| Component | Status | File |
|---|---|---|
| DELETE route `bookings.destroy` | ✅ Exists | `routes/web.php` |
| Controller `BookingController::destroy()` | ✅ Exists | `app/Http/Controllers/BookingController.php:47-50` |
| `AdminBookingService::destroy()` — soft delete + redirect | ✅ Exists | `app/Services/Booking/AdminBookingService.php:841-846` |
| Soft deletes on `Booking` model | ✅ Exists | `app/Models/Booking.php:14,20` |
| Delete dialog in admin **edit sheet** | ✅ Exists | `booking-sheet.tsx:346-367`, `booking-details-section.tsx:416-425`, `use-booking-sheet.ts:942-961` |
| Tests: guest redirect, caregiver 403, admin success + soft delete | ✅ Exists | `tests/Feature/Admin/BookingTest.php:68-73,128-136,349-361` |

**Missing:**

| Component | Status | File(s) |
|---|---|---|
| Delete button + confirmation with text input on admin **show page** | ❌ Missing | `resources/js/pages/admin/bookings/show.tsx` |
| Pre-delete validation (status checks, payment checks) | ❌ Missing | `AdminBookingService@destroy` |
| `BookingGroup` cleanup when last booking deleted | ❌ Missing | — |
| `BookingDeleted` event / notifications | ❌ Missing | — |
| Refund logic for paid bookings | ❌ Missing | — |
| Restore / "trash" UI | ❌ Missing | — |

---

## Backend Architecture

### Auth / Authorization

There is **no `BookingPolicy`**. Authorization is handled via a **role-based service factory**:

```
BookingController → BookingServiceFactory → AdminBookingService | CaregiverBookingService | ClientBookingService
```

- `AdminBookingService::destroy()` — works (soft delete)
- `CaregiverBookingService::destroy()` — returns **403**
- `ClientBookingService::destroy()` — returns **403**

**Route model binding** (`Booking::resolveRouteBinding`) does **not** use `withTrashed()`, so soft-deleted bookings return **404** on any subsequent request.

### Current `destroy()` Implementation

```php
// AdminBookingService.php:841-846
public function destroy(Booking $booking)
{
    $booking->delete();                           // soft delete
    return redirect()->back()
        ->with('success', 'Booking deleted successfully.');
}
```

**No pre-checks, no event dispatch, no related cleanup.**

### Related Models (Soft Delete Status)

| Model | Soft Deletes? | Notes |
|---|---|---|
| Booking | ✅ | `deleted_at` column |
| BookingGroup | ✅ | Orphaned if last booking deleted |
| BookingRating | ✅ | Orphaned (ratings still reference booking_id) |
| BookingCaregiverNotification | ❌ Not checked | Stays in DB |
| ClientPayment | ❌ Not checked | Stays in DB |
| CaregiverAssignment | ❌ Not checked | Stays in DB |

---

## Effort Breakdown

### 1. Frontend: Delete Button + Confirmation on Show Page (~2-3 hours)

- Add a "Delete Booking" button on `admin/bookings/show.tsx`
- On click, show a confirmation dialog with:
  - **Warning**: "This action is permanent and cannot be undone."
  - **Data impact**: "All related data — reviews, ratings, transactions — will also be deleted."
  - A **text input** field requiring the user to type **`DELETE`** (case-insensitive) before the destructive action is enabled
- Disable the confirm button until the correct text is entered
- Use the existing `Input` component with `useState` for text matching
- Show a "Deleting..." state while the Inertia DELETE request is in flight
- On success: redirect back to `/bookings` (the index page)
- On error: show error via existing `ToasterMessage` / flash message
- **Risk**: Low — pattern is well-understood; text input safeguard is new but simple
- **Heads-up**: `Dialog`, `DialogTrigger`, `DialogContent`, `DialogHeader`, `DialogTitle`, `DialogDescription`, `DialogFooter`, `Button`, `Input`, and `Label` are ALL already imported in `show.tsx` (the Split Group dialog uses them). No new imports needed — just the `useState` for the text input and the `useForm().delete()` handler.

**UI mock:**

```
┌───────────────────────────────────┐
│  ⚠️  Delete Booking              │
│                                   │
│  Are you sure you want to delete  │
│  this booking?                    │
│                                   │
│  • This action is permanent and   │
│    cannot be undone.              │
│  • All related data — reviews,    │
│    ratings, transactions — will   │
│    also be deleted.               │
│                                   │
│  Type DELETE to confirm:          │
│  ┌───────────────────────────┐    │
│  │                           │    │
│  └───────────────────────────┘    │
│                                   │
│  Type "DELETE" to proceed.        │  ← hint text (grey)
│                                   │
│          [Cancel]  [Delete]       │  ← Delete disabled until "DELETE" typed
└───────────────────────────────────┘
```

### 2. Backend: Pre-delete Validation (~2-3 hours)

> **Note**: For MVP, skip this and rely on soft delete + admin trust. Validation guards can be added in a follow-up.

Add checks in `AdminBookingService@destroy`:

| Check | Reason | Action |
|---|---|---|
| `payment_status === 'paid'` | Would delete a financial record | Block or require refund first |
| `status === 'completed'` | Job is done, ratings may exist | Block or warn |
| `status === 'confirmed'` | Caregiver is committed | Block or notify caregiver |
| `cancelled_at !== null` | Already cancelled, safe to delete | Allow |
| Existing ratings | Data integrity concern | Allow but warn |

**Implementation option**: Add a `canDelete()` method on the Booking model or service.

### 3. Backend: BookingGroup Cleanup (~1-2 hours)

When deleting a booking:

```
if ($booking->bookingGroup?->bookings()->whereNull('deleted_at')->count() === 1) {
    // This is the only remaining booking in the group → soft delete the group too
    $booking->bookingGroup->delete();
}
```

**Edge case**: Group may have `client()` relationship that also needs cleanup if group is deleted.

### 4. Events & Notifications (~1 hour)

- Dispatch a `BookingDeleted` event (or reuse `BookingCancelled` if semantically appropriate)
- Consider: notify caregiver if they were confirmed/reserved
- Consider: notify client

### 5. Refund Logic (~2-4 hours)

| Scenario | Suggested behavior |
|---|---|
| Booking is `paid` and admin deletes it | Auto-refund via Stripe (using `JobBillingService`) |
| Booking is `pending` payment | Just soft delete |
| Booking has `payment_status = paid` but admin wants to delete without refund | Block or add separate "cancel with refund" flow |

This is the **biggest business decision** in the feature. **For MVP, skip refund logic entirely — just block deletion of paid bookings.**

---

## Risks & Side Effects

| Risk | Impact | Likelihood | Mitigation |
|---|---|---|---|
| BookingGroup has 0 bookings after delete | Low (soft delete, but empty group is confusing) | High | Auto-cleanup groups |
| No refund for paid bookings | High (financial/compliance) | High | Add payment status guard (block delete of paid bookings) |
| Caregiver/client sees 404 on deleted booking URL | Medium (confusing but expected) | Certain | Show "this booking was deleted" message instead |
| No notification on delete | Medium (stakeholders unaware) | Current behavior | Add `BookingDeleted` event |
| Double-click / race condition | Low (soft delete is idempotent) | Low | Already handled by Inertia |
| Ratings become orphaned | Low (still reference booking_id) | Certain | Acceptable for soft delete |
| Stripe payment intent orphaned | Medium (no refund issued) | High | Block delete of paid bookings |
| **Accidental delete by admin** | **High** (data loss, no undo UI) | **Low** (admin trust) | **Text input confirmation requires typing "DELETE"** |
| Admin deletes wrong booking (sibling in group) | Medium (group loses sibling) | Low | Dialog shows booking datetime info |

---

## Edge Cases

1. **Deleting the last booking in a group** — BookingGroup becomes empty; should it also be soft-deleted?
2. **Deleting a paid booking** — Should refund? Should it be blocked?
3. **Deleting a booking with ratings** — Ratings become orphaned (soft delete preserves FK, but booking is hidden)
4. **Deleting a confirmed booking** — Caregiver has committed; notification required
5. **Deleting a reserved booking** — Should release the reservation first
6. **Stripe payment intent still exists** — Delete without refund = potential compliance issue
7. **Undo / Restore** — Soft delete allows restore but no UI exists
8. **Bulk delete** — Not currently supported, may be expected for admin workflows
9. **Simultaneous delete** — Two admins deleting same booking; soft delete handles gracefully
10. **Deleted booking in exports** — Should exports include or exclude deleted bookings?

---

## Decision Points (Updated)

| # | Question | Decision | Details |
|---|---|---|---|
| 1 | Who can delete? | **Admin only** ✅ | Blocked for caregiver/client via service factory (no policy changes needed) |
| 2 | Where does the delete button live? | **Show page only** ✅ | `admin/bookings/show.tsx` — NOT the index page or edit sheet |
| 3 | Confirmation safeguard? | **Text input requiring `DELETE`** ✅ | User must type "DELETE" to enable the confirm button — prevents accidental clicks |
| 4 | What booking statuses can be deleted? | TBD | Suggestions above (Section 2). For MVP: allow any status, skip validation |
| 5 | Refund on delete? | TBD | **For MVP: block deletion of paid bookings** rather than implementing refund logic |
| 6 | Separate "cancel" vs "delete"? | TBD | Not currently needed. Delete = soft delete (admin action only) |
| 7 | Restore UI? | TBD | Not needed for MVP. Soft delete data is preserved for manual DB restore |
| 8 | Notifications? | TBD | Not needed for MVP. Admin-only action, no stakeholder notification |
| 9 | BookingGroup cleanup? | TBD | Needs assessment. For MVP: allow orphaned groups |
| 10 | Show page redirect? | **Back to index** ✅ | `redirect()->back()` on the show page returns to index |

---

## Test Coverage Needed

| Test | Priority | Notes |
|---|---|---|
| Delete a booking as admin → success + soft delete | ✅ Exists | `BookingTest.php:349-361` |
| Guest cannot delete → redirect to login | ✅ Exists | `BookingTest.php:68-73` |
| Caregiver cannot delete → 403 | ✅ Exists | `BookingTest.php:128-136` |
| Client cannot delete → 403 | ❌ Missing | Low priority (test only) |
| Delete a booking that is part of a group | ❌ Missing | Medium priority |
| Delete the last booking in a group (group cleanup) | ❌ Missing | Medium priority (if cleanup added) |
| Delete a booking with `payment_status = paid` → blocked | ❌ Missing | High priority (if guard added) |
| Delete a `confirmed` booking → notification sent | ❌ Missing | Low priority (if notification added) |
| Delete a booking with ratings → ratings preserved | ❌ Missing | Medium priority |
| Cancel $booking = delete + refund | ❌ Missing | Low priority (if refund added) |
| Multiple deletes of same booking → idempotent | ❌ Missing | Low priority |
| Soft-deleted booking not visible in index/show | ❌ Missing | Route binding test |
| **Confirmation text input: rejects wrong input** | ❌ Missing | Frontend test — verify button stays disabled until "DELETE" typed |

---

## Effort Summary

| Scope | Complexity | Estimated Effort |
|---|---|---|
| **Frontend: Show page + text input dialog** | **Easy** | **~2-3 hours** |
| + Backend: Pre-delete validation | Medium | ~4-6 hours |
| + Backend: BookingGroup cleanup | Medium | ~5-7 hours |
| + Events & notifications | Medium | ~6-8 hours |
| + Refund logic | High | ~8-12 hours |
| + Full test coverage | Medium | ~3-5 hours |
| **MVP (show page + text input dialog only)** | **Easy** | **~2-3 hours** |
| **Full feature (admin, no refund)** | **Medium** | **~6-10 hours** |
| **Full feature (all roles)** | **High** | **~12-18 hours** |
