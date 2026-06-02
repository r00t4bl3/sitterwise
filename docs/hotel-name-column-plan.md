# Hotel Name Column Plan

## Goal
Allow guests to enter an unlisted hotel name when their hotel is not in our database. Store it as `hotel_name` on `booking_groups` (which already has the column). Display priority: `bookingGroup->hotel_name` → `hotel?->name` → fallback.

## Design Decision
- `hotel_name` was already added to `booking_groups` migration from the start
- `BookingGroup` model already has it in `$fillable`
- Guest booking flow already implemented (form, controller, service)
- The remaining work was fixing backend services to resolve `hotel_name` via `bookingGroup->hotel_name` instead of non-existent `$booking->hotel_name`

---

## Changes Made

### Already Existed
- `booking_groups` table — has `hotel_name` column (varchar(255), nullable)
- `BookingGroup` model — `hotel_name` in `$fillable`
- `GuestBookingService::validateOnly()` — `hotel_name` validation rule
- `GuestBookingService::getPaymentData()` — passes `hotel_name`
- `GuestBookingService::createBookingWithPayment()` — stores `hotel_name` on `BookingGroup::create()`
- `GuestBookingController::confirmation()` — passes `hotel_name` to Inertia
- `Booking::toEmailData()` — uses `$group->hotel_name ?? $group->hotel?->name` fallback
- `BookingGroup::toEmailData()` — uses `$this->hotel_name ?? $this->hotel?->name` fallback
- `resources/js/pages/guest/bookings/create.tsx` — "My hotel is not listed" toggle, text input, `hotel_name` in form data, validation
- `resources/js/pages/guest/bookings/confirmation.tsx` — conditional hotel name display
- `resources/js/pages/admin/bookings/show.tsx` — `{booking.hotel_name &&` truthy check
- `resources/js/pages/client/bookings/show.tsx` — same
- `resources/js/pages/caregiver/bookings/show.tsx` — same
- `resources/js/pages/caregiver/jobs/show.tsx` — same
- `resources/js/pages/admin/bookings/personal-info-section.tsx` — "My hotel is not listed" toggle + text input

### Fixed (this session)

| File | What Changed |
|------|-------------|
| `app/Services/Booking/AdminBookingService.php:336` | Added `'hotel_name' => $validated['hotel_name'] ?? null` to `BookingGroup::create()` call |
| `app/Services/Booking/AdminBookingService.php:463` | `$booking->hotel_name` → `$booking->bookingGroup->hotel_name` |
| `app/Services/Booking/ClientBookingService.php:283` | Added `'bookingGroup'` to `$booking->load()` |
| `app/Services/Booking/ClientBookingService.php:324` | `$booking->hotel_name` → `$booking->bookingGroup->hotel_name` |
| `app/Services/Booking/CaregiverBookingService.php:131` | `$booking->hotel_name` → `$booking->bookingGroup->hotel_name` |
| `app/Http/Controllers/JobController.php:114` | `$booking->hotel_name` → `$booking->bookingGroup->hotel_name` |
| `database/migrations/2026_06_02_020025_add_hotel_name_to_bookings_table.php` | **Deleted** — incorrectly targeted `bookings` table; the column already exists on `booking_groups` |

### Why `$booking->bookingGroup->hotel_name` Instead of `$booking->hotel_name`

The `bookings` table does NOT have `hotel_name`. All client/address/hotel data lives on `booking_groups`. The `Booking` model accesses hotel through a `HasOneThrough` relationship:

```
Booking → (booking_group_id) → BookingGroup → (hotel_id) → Hotel
```

The `hotel_name` column is on `booking_groups`, so:
- `$booking->hotel_name` — always null (column doesn't exist on `bookings`)
- `$booking->bookingGroup->hotel_name` — correct value (from `booking_groups` table)
- `$booking->hotel?->name` — works for listed hotels via HasOneThrough, null for unlisted

---

## Verification

```bash
# No pending migrations (the incorrect one was deleted)
php artisan migrate:status

# Pint formatting
vendor/bin/pint --dirty --format agent

# Tests
php artisan test --compact --filter=hotel
```
