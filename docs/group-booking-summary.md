## Goal
- Normalize ~30 shared fields from `bookings` to `booking_groups` to support multi-date guest group bookings

## Progress
### Phase 1: Schema Changes — DONE
- [x] Edited migration files — 30 shared columns added to `booking_groups`, removed from `bookings`; `is_split` dropped entirely

### Phase 2: Backend Code Changes — DONE
- [x] Created `HasGroupFields` trait — proxy accessors for 20+ delegated fields with `isDirty()` docblock warning
- [x] Updated `BookingGroup` model — 30 fillable fields, casts, relationships, `calculateSpecialConsiderations()`, `toEmailData()`
- [x] Updated `Booking` model — removed 30 casts, removed boot hooks for shared fields, added `HasGroupFields` trait, `client()` proxy, `searchGroupFields` scope, `calculateHourlyRate()` accepts optional group param
- [x] Updated `Client` model — added `bookingGroups()` hasMany, `bookings()` via hasManyThrough
- [x] Updated `BookingGroupFactory` — 30 field defaults, `comped()`/`hotel()` states
- [x] Updated `BookingFactory` — removed 30 shared fields & `client_id`, added `withBookingGroup()` helper
- [x] Updated `GuestBookingService` — shared fields on BookingGroup, dates[] support, BookingGroupCreated event for multi-date
- [x] Updated `ClientBookingService` — shared fields on BookingGroup, dates[] support, BookingGroupCreated event for multi-date
- [x] Updated `AdminBookingService` — shared fields on BookingGroup, dates[] support, BookingGroupCreated event for multi-date
- [x] Created `BookingGroupObserver` — auto-reprices child bookings when service_type/children/pets change
- [x] Created `BookingGroupCreated` event + `SendBookingGroupCreatedNotifications` listener
- [x] Created `ClientGroupBookingCreatedMail` + `AdminGroupBookingCreatedMail`
- [x] Registered event-listener mapping in `AppServiceProvider`
- [x] Updated `ImportBubbleDatabase::importJob()` — per-job groups, shared fields on group
- [x] Updated `StoreBookingRequest` — dates[] validation for admin + client rules
- [x] Updated `SearchController` — all 3 methods use `whereHas('bookingGroup')` + `scopeSearchGroupFields`
- [x] Updated `CaregiverRecommendationService` — `whereHas('bookingGroup')` for client_id
- [x] Updated eager loads in `CaregiverBookingService`, `JobController`

### Phase 3: Frontend Code Changes — DONE
- [x] Updated `admin/bookings/types.ts` — BookingGroup interface, 30 fields removed from Booking, added booking_group ref
- [x] Updated `admin/bookings/index.tsx` — all booking.* refs → booking.booking_group.*
- [x] Updated `admin/bookings/show.tsx` — group context section with sibling links
- [x] Updated `admin/bookings/personal-info-section.tsx` — reads children/pets/client from booking_group
- [x] Updated `admin/bookings/use-booking-sheet.ts` — reads client_id/hotel_id/address from booking_group
- [x] Updated `guest/bookings/confirmation.tsx` — shows sibling dates for group bookings
- [x] Updated `guest/bookings/create.tsx` — sends dates[] array in form data
- [x] 0 booking-related TypeScript errors (29 pre-existing in unrelated files)

### Next Steps
1. Update ~120 affected test files
2. Run `php artisan migrate:fresh` + re-import from Bubble
3. Manual QA of multi-date booking flow

## Relevant Files
- `docs/group-booking-plan.md`: Full architecture + schema + phases + testing strategy
- `docs/group-booking-plan-review.md`: Architectural review (3 rounds)
- `database/migrations/2026_04_01_041855_create_booking_groups_table.php`
- `database/migrations/2026_04_01_041859_create_bookings_table.php`
- `app/Models/Traits/HasGroupFields.php`
- `app/Models/BookingGroup.php`
- `app/Models/Booking.php`
- `app/Models/Client.php`
- `app/Observers/BookingGroupObserver.php`
- `app/Events/BookingGroupCreated.php`
- `app/Listeners/SendBookingGroupCreatedNotifications.php`
- `app/Mail/ClientGroupBookingCreatedMail.php`
- `app/Mail/AdminGroupBookingCreatedMail.php`
- `app/Providers/AppServiceProvider.php`
- `database/factories/BookingGroupFactory.php`
- `database/factories/BookingFactory.php`
- `app/Services/Booking/GuestBookingService.php`
- `app/Services/Booking/ClientBookingService.php`
- `app/Services/Booking/AdminBookingService.php`
- `app/Http/Requests/StoreBookingRequest.php`
- `app/Http/Controllers/SearchController.php`
- `app/Http/Controllers/GuestBookingController.php`
- `app/Http/Controllers/JobController.php`
- `app/Console/Commands/ImportBubbleDatabase.php`
- `resources/js/pages/admin/bookings/types.ts`
- `resources/js/pages/admin/bookings/index.tsx`
- `resources/js/pages/admin/bookings/show.tsx`
- `resources/js/pages/admin/bookings/personal-info-section.tsx`
- `resources/js/pages/admin/bookings/use-booking-sheet.ts`
- `resources/js/pages/guest/bookings/create.tsx`
- `resources/js/pages/guest/bookings/confirmation.tsx`
