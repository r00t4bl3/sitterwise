# Booking Sheet Refactor Plan

## Goal
Extract the reusable `BookingSheet` component and `useBookingSheet` hook from the monolithic `resources/js/pages/admin/bookings/index.tsx` (1900+ lines) to enable reuse in the admin dashboard and improve maintainability.

---

## Architecture Overview

```
resources/js/pages/admin/bookings/
├── index.tsx                    (refactored: ~1100 lines)
├── use-booking-sheet.ts         (NEW: ~650 lines - state & handlers)
├── booking-sheet.tsx            (NEW: ~120 lines - Sheet UI)
├── booking-details-section.tsx  (existing)
├── personal-info-section.tsx    (existing)
└── types.ts                    (existing)
```

---

## Files to Create

### 1. `resources/js/pages/admin/bookings/use-booking-sheet.ts`

**Purpose:** Custom hook encapsulating all Sheet state and logic.

**State Variables (extracted from index.tsx lines ~182-295):**
- `isSheetOpen`, `editingBooking`, `sheetMode` ('create' | 'edit' | 'duplicate')
- `form` (useForm with all booking fields)
- `clientSuggestions`, `hotelSuggestions`, `caregiverSuggestions`
- `clientAddresses`, `clientChildren`, `clientPets`
- `clientMode`, `selectedClientType`, `loadingSuggestions`
- `selectedClientName`, `selectedHotelName`, `selectedCaregiverName`
- `isAddressLocked`, `showManualAddressInput`, `addressValue`
- `deletedChildIds`, `deletedPetIds`, `newChildren`, `newPets`
- `saveChildrenPetsToProfile`, `showDeleteDialog`

**Handler Functions (extracted from index.tsx lines ~505-1164):**
- `openCreateSheet(date?)`, `openEditSheet(booking)`, `openDuplicateSheet(booking)`
- `handleSubmit`, `handleDelete`, `handleConfirmDelete`, `handleCancelDelete`
- `handleClientSearch`, `handleHotelSearch`, `handleCaregiverSearch`
- `handleClientChange`, `handleSpecialConsiderationChange`
- `handleAddChild`, `handleRemoveChild`, `handleUpdateChild`
- `handleAddPet`, `handleRemovePet`, `handleUpdatePet`
- `fetchClientDataOnly`, `fetchRecommendedCaregivers`, `populateCaregiverSuggestions`

**Hook Signature:**
```typescript
interface UseBookingSheetProps {
    clients: Array<{ id: number; name: string; [key: string]: unknown }>;
    hotels: Array<{ id: number; name: string; [key: string]: unknown }>;
    caregivers: Array<{ id: number; name: string; [key: string]: unknown }>;
    service_types: Array<{ value: string; label: string }>;
    location_types: Array<{ value: string; label: string }>;
    booking_statuses: Array<{ value: string; label: string; colors: { bg: string; text: string; border: string } }>;
    payment_statuses: Array<{ value: string; label: string }>;
    special_consideration_options: Array<{ value: string; label: string }>;
    booking_attributes: Array<{ id: number; name: string; slug: string; type: string; options: string[] }>;
    sitter_preference_options: Array<{ value: string; label: string }>;
    client_type_options?: Array<{ value: string; label: string }>;
}

interface UseBookingSheetReturn {
    isSheetOpen: boolean;
    setIsSheetOpen: (open: boolean) => void;
    editingBooking: Booking | null;
    sheetMode: 'create' | 'edit' | 'duplicate';
    form: ReturnType<typeof useForm>;
    // ... all state variables
    // ... all handler functions
    openCreateSheet: (date?: string) => void;
    openEditSheet: (booking: Booking) => Promise<void>;
    openDuplicateSheet: (booking: Booking) => Promise<void>;
    handleSubmit: () => void;
    // ... etc
}
```

---

### 2. `resources/js/pages/admin/bookings/booking-sheet.tsx`

**Purpose:** Sheet UI component that renders the booking form.

**Renders:**
- `<Sheet>` with `PersonalInfoSection` and `BookingDetailsSection`
- `<Dialog>` for delete confirmation
- Uses state/handlers from `useBookingSheet`

**Props:**
```typescript
interface BookingSheetProps extends UseBookingSheetReturn {
    // Inherits all from UseBookingSheetReturn
}
```

---

## Files to Modify

### 3. `resources/js/pages/admin/bookings/index.tsx`

**Remove (~800 lines):**
- Lines ~182-394: State declarations
- Lines ~505-1164: Handler functions
- Lines ~1799-1927: Sheet JSX

**Add (~30 lines):**
- Import `useBookingSheet` and `BookingSheet`
- Call hook: `const sheet = useBookingSheet({ data from usePage() })`
- Replace Sheet JSX with: `<BookingSheet {...sheet} />`

**Keep (~1100 lines):**
- Calendar rendering logic (lines ~1396-1795)
- Table rendering logic (lines ~1566-1795)
- Filters, search, month navigation
- `calculateAge`, `formatDateTimeLocal`, `getDaysInMonth` helpers

---

### 4. `resources/js/pages/dashboard/admin.tsx`

**Add (~60 lines):**

**Imports:**
```typescript
import { useBookingSheet } from '@/pages/admin/bookings/use-booking-sheet';
import { BookingSheet } from '@/pages/admin/bookings/booking-sheet';
import { useForm } from '@inertiajs/react';
```

**State & Handlers:**
- Use `useBookingSheet` hook with dashboard data
- Change `<Link>` rows to `<button>` with `onClick={() => sheet.openEditSheet(booking)}`

**Sections to Update:**
1. "Bookings Requiring Attention" rows (lines ~183-221)
2. "Recent Activity" > "New Bookings" rows (lines ~265-301)
3. "Today's Schedule" rows (lines ~375-426)

**Render:**
- Add `<BookingSheet {...sheet} />` before closing `AppLayout`

---

## Backend Changes Required

### 5. Update Dashboard Controller

**File:** `app/Http/Controllers/DashboardController.php` (or similar)

The dashboard endpoint needs to pass additional data:

```php
return Inertia::render('dashboard/admin', [
    'stats' => [...],
    'admin' => [...],
    // Add for BookingSheet:
    'clients' => $clients,
    'hotels' => $hotels,
    'caregivers' => $caregivers,
    'service_types' => [...],
    'location_types' => [...],
    'booking_statuses' => [...],
    'payment_statuses' => [...],
    'special_consideration_options' => [...],
    'booking_attributes' => [...],
    'sitter_preference_options' => [...],
]);
```

---

## Implementation Order

1. **Create `use-booking-sheet.ts` hook**
   - Extract all state and handlers from index.tsx
   - Define proper TypeScript interfaces
   - Test in isolation

2. **Create `booking-sheet.tsx` component**
   - Sheet UI with PersonalInfoSection and BookingDetailsSection
   - Delete confirmation dialog
   - Pass through all props from hook

3. **Refactor `bookings/index.tsx`**
   - Remove extracted code
   - Integrate useBookingSheet hook
   - Test: calendar/table/sheet all work

4. **Update backend dashboard endpoint**
   - Add required data for BookingSheet
   - Ensure proper authorization

5. **Update `dashboard/admin.tsx`**
   - Integrate useBookingSheet hook
   - Change Link rows to button triggers
   - Test: sheet opens from all three sections

6. **Run Quality Checks**
   - `vendor/bin/pint --dirty --format agent` for PHP files
   - `php artisan test --compact` to verify

---

## Benefits

- **Maintainability**: Sheet logic isolated in reusable hook
- **Consistency**: Both pages use identical booking edit experience
- **Testability**: Hook can be unit tested independently
- **Size**: `bookings/index.tsx` reduces from ~1900 → ~1100 lines
- **Reusability**: Other pages can easily add booking sheet functionality

---

## Testing Strategy

1. **Booking Index Page:**
   - Calendar view: click booking → sheet opens
   - Table view: click Edit → sheet opens
   - Create button → sheet opens (create mode)
   - Duplicate button → sheet opens (duplicate mode)
   - Form submission works (create/edit)
   - Delete confirmation works

2. **Dashboard Page:**
   - "Bookings Requiring Attention" row click → sheet opens
   - "Recent Activity" booking click → sheet opens
   - "Today's Schedule" row click → sheet opens
   - Form submission works (edit mode only)

---

## Potential Challenges

1. **Large hook size**: The hook will still be ~650 lines. Consider further splitting if needed.
2. **Form data shape**: Ensure the form data structure matches between pages.
3. **Route differences**: Dashboard may need different redirect behavior after submit.
4. **Type safety**: Ensure proper TypeScript types are shared between components.

---

## Rollback Plan

If issues arise:
1. Keep backup of original `index.tsx`
2. Git commit after each step
3. Can easily revert to monolithic approach if needed
