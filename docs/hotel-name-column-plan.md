# Hotel Name Column Plan

## Goal
Add a `hotel_name` column to `bookings` to cover both listed hotels (name snapshot at booking time) and unlisted hotels (manual entry). Display priority: `hotel_name` → `hotel?->name` → fallback.

## Design Decision
**Always populate `hotel_name`**, even when the user selects a listed hotel. This gives us an audit trail — if a hotel is later renamed or deleted in the `hotels` table, bookings still show the original name. No duplication concern since it's a read-only snapshot.

Display fallback chain:
```
$booking->hotel_name ?? $booking->hotel?->name ?? 'N/A'
```

---

## File-by-File Changes

### 1. Migration

**File:** `database/migrations/xxxx_xx_xx_xxxxxx_add_hotel_name_to_bookings_table.php`

```php
Schema::table('bookings', function (Blueprint $table) {
    $table->string('hotel_name', 255)->nullable()->after('hotel_id');
});
```

---

### 2. Backend PHP — Model

#### `app/Models/Booking.php`

**`toEmailData()` — update 5 lines to use fallback chain:**

| Line | Current | New |
|------|---------|-----|
| 407 | `$this->hotel?->name ?? $this->address_line1` | `$this->hotel_name ?? $this->hotel?->name ?? $this->address_line1` |
| 409 | `$this->hotel?->name ?? 'N/A'` | `$this->hotel_name ?? $this->hotel?->name ?? 'N/A'` |
| 410 | `$this->hotel?->name ?? 'N/A'` | `$this->hotel_name ?? $this->hotel?->name ?? 'N/A'` |
| 411 | `$this->hotel?->name` | `$this->hotel_name ?? $this->hotel?->name` |
| 412 | `$this->hotel?->name.' Booking'` | `($this->hotel_name ?? $this->hotel?->name).' Booking'` |

No `$fillable` changes needed — `$guarded = ['id']` means `hotel_name` is mass-assignable by default.

---

### 3. Backend PHP — GuestBookingService

#### `app/Services/Booking/GuestBookingService.php`

**Validation (`validateOnly()`) — line 112:** Add `hotel_name` rule:
```php
'hotel_name' => 'nullable|string|max:255',
```

**`getPaymentData()` — line 205 (BUG FIX):** Currently passes `$pendingData['hotel_id']` as the name. Fix:
```php
'hotel_name' => $pendingData['hotel_name'] ?? null,
```

**`createBookingWithPayment()` — line 234:** Add `hotel_name` to `Booking::create()` call:
```php
'hotel_name' => $pendingData['hotel_name'] ?? null,
```

---

### 4. Backend PHP — Other Services (resolve fallback)

These lines currently do `$booking->hotel?->name`. Change to `$booking->hotel_name ?? $booking->hotel?->name`:

| File | Line |
|------|------|
| `app/Services/Booking/AdminBookingService.php` | 432 |
| `app/Services/Booking/ClientBookingService.php` | 309 |
| `app/Services/Booking/CaregiverBookingService.php` | 131 |
| `app/Http/Controllers/JobController.php` | 114 |

---

### 5. Backend PHP — GuestBookingController

#### `app/Http/Controllers/GuestBookingController.php`

**`confirmation()` — line 222:** Add `hotel_name` to the booking data passed to the frontend:
```php
'hotel_name' => $booking->hotel_name ?? $booking->hotel?->name,
```

---

### 6. Frontend — Guest Booking Create

#### `resources/js/pages/guest/bookings/create.tsx`

**Form initial data (line ~217):** Add `hotel_name: ''`

**Hotel section (lines 793-837):** Replace the current hotel Autocomplete with:
- Autocomplete to search listed hotels (existing)
- "My hotel is not listed" link below the Autocomplete
- When clicked: hide Autocomplete, show a text `<Input>` for manual `hotel_name`
- "Back to hotel list" link to toggle back
- When a hotel is selected from Autocomplete, also set `form.data.hotel_name` to `selectedHotelName` (snapshot)

**`validateForm()`:** Add check — if `location_type === 'hotel'`, require either `hotel_id` or `hotel_name`:

```typescript
if (formData.location_type === 'hotel') {
    if (!formData.hotel_id && !formData.hotel_name?.trim()) {
        errors.hotel_name = 'Please select or enter a hotel.';
    }
}
```

**`selectedHotelName` (lines 270-271):** Also set `form.data.hotel_name` when a hotel is selected.

**Contact the form on hotel select:**
```typescript
form.setData('hotel_name', hotel.name); // snapshot
```

---

### 7. Frontend — Guest Booking Confirmation

#### `resources/js/pages/guest/bookings/confirmation.tsx`

**`BookingData` interface (line 6-19):** Add `hotel_name?: string`

**Location display (lines 93-103):** Show hotel name when present (alongside or instead of address):

```tsx
<div className="flex justify-between">
    <span className="text-muted-foreground">Location</span>
    <span className="text-right font-medium">
        {booking.hotel_name ? (
            booking.hotel_name
        ) : (
            <>
                {booking.address_line1}<br />
                {booking.address_city}, {booking.address_state} {booking.address_zip}
            </>
        )}
    </span>
</div>
```

---

### 8. Frontend — 4 Display Pages

All 4 files currently show hotel name only when `booking.hotel_id !== null`. Change condition to `booking.hotel_name` (truthy check) so it also shows for unlisted hotels:

| File | Line |
|------|------|
| `resources/js/pages/admin/bookings/show.tsx` | 263 |
| `resources/js/pages/client/bookings/show.tsx` | 248 |
| `resources/js/pages/caregiver/bookings/show.tsx` | 357 |
| `resources/js/pages/caregiver/jobs/show.tsx` | 194 |

Before:
```tsx
{booking.hotel_id !== null && (
    <div className="flex items-center gap-2">
        <Building className="h-4 w-4 text-muted-foreground" />
        <span className="text-sm text-muted-foreground">
            {booking.hotel_name}
        </span>
    </div>
)}
```

After:
```tsx
{booking.hotel_name && (
    <div className="flex items-center gap-2">
        <Building className="h-4 w-4 text-muted-foreground" />
        <span className="text-sm text-muted-foreground">
            {booking.hotel_name}
        </span>
    </div>
)}
```

---

### 9. Frontend — Admin Booking Personal Info Section

#### `resources/js/pages/admin/bookings/personal-info-section.tsx`

The admin personal info section also has a hotel Autocomplete (line 891-950). Add the same "My hotel is not listed" toggle there, with a `hotel_name` text input that saves to `form.data.hotel_name`.

Also pass `hotel_name` through when creating/editing booking via the admin form.

---

### 10. Run Pint

```bash
vendor/bin/pint --dirty --format agent
```

---

## Summary of Changes

| Category | Files | Changes |
|----------|-------|---------|
| Migration | 1 new | Add `hotel_name` column |
| Model | 1 | `toEmailData()` fallback chain (5 lines) |
| Services | 4 | Resolve `hotel_name ?? hotel?->name` |
| Controller | 1 | Pass `hotel_name` to confirmation |
| Guest form | 1 | "Not listed" toggle + validation |
| Confirmation page | 1 | Show hotel name |
| Display pages | 4 | `hotel_name` truthy check |
| Admin form | 1 | "Not listed" toggle |
| **Total** | **~14 files** | |
