# Timezone Overhaul Plan

## Problem

The application operates in **America/Los_Angeles** (PDT/PST) but `config/app.php` has `'timezone' => 'UTC'`.

When a user submits a booking at **9:00 AM PT** (e.g., via the guest or client booking form):

1. The frontend sends a naive datetime string: `"2026-05-28T09:00"` (no timezone indicator)
2. Laravel interprets it as UTC (because `app.timezone = 'UTC'`)
3. The database stores `2026-05-28 09:00:00` in a MySQL `timestamp` column (which stores UTC internally)
4. The stored value is **7 hours off** from the user's intended time

**Consequences:**
- Frontend display: `new Date("2026-05-28T09:00:00.000000Z")` → browser in PT shows **2:00 AM** (7 hours earlier)
- Export/interop: external systems read `2026-05-28 09:00:00 UTC` as the correct absolute time, which is wrong
- Any timezone-aware computation (e.g., "is this booking active now?") returns wrong results

**The same bug exists in `ImportBubbleDatabase.php`:** `timestampToDateTime()` converts Bubble's Unix ms timestamps to PT strings, then MySQL stores those PT strings as-is in `timestamp` columns (treating them as UTC).

### Bubble Data Timezone Handling

Bubble.io stores **no timezone information** for date-related fields. All timestamps in Bubble's database are epoch milliseconds (integers) — absolute instants in time with no embedded timezone.

**Data flow:**

```
Bubble Elasticsearch API response
  │
  │  hit['_source'] = {
  │    "Modified Date": 1718092800000,          ← epoch ms (integer)
  │    "start_date_date": 1718114400000,         ← epoch ms (integer)
  │    "date_of_birth": 946684800000             ← epoch ms (integer)
  │  }
  │
  ▼
staged_records (SQLite)
  │  raw_json stores timestamps as-is in JSON:  ← no timezone metadata saved
  │  '{"Modified Date": 1718092800000, ...}'
  │
  ▼
ImportStagedData / ImportUserService
  │  Interprets epoch ms as America/Los_Angeles:
  │    timestampToDate($t)     → Carbon::createFromTimestampMs($t, 'America/Los_Angeles')
  │    timestampToDateTime($t) → Carbon::createFromTimestampMs($t, 'America/Los_Angeles')
  │                                → setTimezone('UTC')
  │
  ▼
App database (MySQL timestamp columns, stored as UTC)
```

**Assumption:** All Bubble timestamps represent `America/Los_Angeles` wall-clock time. This is a hardcoded assumption — there is no runtime configuration for this.

**Two conversion methods** (duplicated in both `ImportBubbleDatabase.php` and `ImportUserService.php`):

| Method | Interpretation | Output | Used for |
|--------|--------------|--------|----------|
| `timestampToDate(?int $t): ?string` | `America/Los_Angeles` | `Y-m-d` date string (LA local date) | `date_of_birth`, experience start/end, CPR/background check expirations |
| `timestampToDateTime(?int $t): ?string` | `America/Los_Angeles` → UTC | `Y-m-d H:i:s` UTC string | `start_datetime`, `end_datetime`, `confirmed_at`, `cancelled_at`, rating/transaction dates |

**Storage in staging DB:** The `staged_records` table has no timezone column. The `raw_json` field preserves the original epoch ms integers. Timezone interpretation happens at import time (when moving from staging to app DB), not at scrape time.

## Goal

Make the application timezone-correct while keeping `app.timezone = 'UTC'`:

1. **Backend**: Convert user input from America/Los_Angeles → UTC before storing
2. **Import command**: Change `timestampToDateTime` to return UTC strings; bypass model mutators via raw attribute setting
3. **Frontend**: Convert UTC → America/Los_Angeles for display
4. **Data migration**: Fix existing records stored with wrong UTC values

## Why Not Change `app.timezone`?

`timestamp` columns (`created_at`, `updated_at`, etc.) rely on `app.timezone` for correct conversion between app timezone and UTC on read/write. Changing it would shift all system timestamps. Only `start_datetime`, `end_datetime`, `confirmed_at`, and `cancelled_at` need PT interpretation — not `created_at`.

## Architecture

```
User input (PT) ──→ Backend converts PT→UTC ──→ MySQL timestamp (UTC)
                                                    ↓
Frontend display ←── PHP serializes ISO (UTC) ←──────┘
       ↓
  toLocaleString(timeZone: 'America/Los_Angeles')
       ↓
  Displays "9:00 AM"
```

## Implementation Steps

### Step 1: Model Mutators (`app/Models/Booking.php`)

Add mutators that convert PT→UTC on set for `start_datetime`, `end_datetime`, `confirmed_at`, and `cancelled_at`. This covers ALL write paths (guest, admin, client) automatically.

Note: mutators only fire on explicit `->setAttribute()` calls (create, update). Eloquent hydration from the database bypasses mutators, so existing records are read correctly.

**Code to add:**

```php
use Carbon\Carbon;

// In the Booking model:

public function setStartDatetimeAttribute($value): void
{
    $this->attributes['start_datetime'] = $this->convertToUtc($value);
}

public function setEndDatetimeAttribute($value): void
{
    $this->attributes['end_datetime'] = $this->convertToUtc($value);
}

public function setConfirmedAtAttribute($value): void
{
    $this->attributes['confirmed_at'] = $this->convertToUtc($value);
}

public function setCancelledAtAttribute($value): void
{
    $this->attributes['cancelled_at'] = $this->convertToUtc($value);
}

private function convertToUtc(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    if ($value instanceof Carbon) {
        return $value->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
    }

    return Carbon::parse($value, 'America/Los_Angeles')
        ->setTimezone('UTC')
        ->format('Y-m-d H:i:s');
}
```

**Why this works:**
- When creating/updating: `Booking::create(['start_datetime' => '2026-05-28T09:00'])` → mutator sees `'2026-05-28T09:00'` → parses as 09:00 America/Los_Angeles → converts to 16:00 UTC → stores `'2026-05-28 16:00:00'`
- When hydrating from DB: Eloquent sets `$this->attributes['start_datetime'] = '2026-05-28 16:00:00'` directly (bypasses mutator) → `datetime` cast wraps as `Carbon('2026-05-28 16:00:00', 'UTC')` → `jsonSerialize()` → `"2026-05-28T16:00:00.000000Z"`
- Frontend receives the correct UTC value with Z suffix

**Important for the import command:** `importJob` bypasses these mutators via raw `$booking->attributes[]` assignment to prevent double-conversion (since `timestampToDateTime` already returns UTC strings — see Step 1b).

### Step 1b: Fix ImportBubbleDatabase Command (`app/Console/Commands/ImportBubbleDatabase.php`)

The import command has the same PT-as-UTC bug. Bubble stores all date fields as Unix millisecond timestamps (timezone-independent). Two changes needed:

#### A) `timestampToDateTime` (line 1657)

Change to return UTC strings instead of PT strings:

```php
protected function timestampToDateTime(?int $t): ?string
{
    if (! $t) {
        return null;
    }
    try {
        return Carbon::createFromTimestampMs($t, 'America/Los_Angeles')
            ->setTimezone('UTC')
            ->toDateTimeString();
    } catch (\Exception $e) {
        return null;
    }
}
```

This single change fixes ALL downstream paths:

| Call site | Column | Type | Before (PT string → wrong) | After (UTC string → correct) |
|-----------|--------|------|----------------------------|------------------------------|
| `importJob` line 1385 | `start_datetime` | `timestamp` | `"2022-09-16 06:00"` stored as 06:00 UTC | `"2022-09-16 13:00"` stored as 13:00 UTC |
| `importJob` line 1386 | `end_datetime` | `timestamp` | `"2022-09-16 14:00"` stored as 14:00 UTC | `"2022-09-16 21:00"` stored as 21:00 UTC |
| `importJob` line 1384 | `confirmed_at` | `timestamp` | PT string stored as UTC | UTC string stored as UTC |
| `importJob` line 1418 | `cancelled_at` | `timestamp` | PT string stored as UTC | UTC string stored as UTC |
| `importRating` line 1673 | `$date` (lookup) | query value | `"2024-09-21 08:30"` PT, misses UTC match | `"2024-09-21 15:30"` UTC, matches |
| `importRating` line 1754 | `created_at` | `timestamp` | PT string stored as UTC | UTC string stored as UTC |
| `importTransaction` line 1766 | `$date` (lookup) | query value | PT string, misses UTC match | UTC string, matches |
| `importTransaction` line 1825 | `paid_at` | `timestamp` | PT string stored as UTC | UTC string stored as UTC |
| `importTransaction` line 1850 | `payout_date` | `datetime` | PT literal `"08:27"` | UTC literal `"15:27"` |

#### B) `importJob` (lines 1477–1489)

Bypass Booking model mutators via raw attribute assignment. Since `timestampToDateTime` now returns UTC strings (e.g., `"2022-09-16 13:00:00"`), model mutators would interpret them as PT and double-convert to `"2022-09-16 20:00:00"` — wrong.

Replace `Booking::updateOrCreate(...)` with direct `$attributes` assignment inside the existing `withoutEvents` closure:

```php
Booking::withoutEvents(function () use ($externalId, $bookingData, $caregiver, $status) {
    $existing = Booking::where('bubble_id', $externalId)->first();
    if ($existing) {
        $booking = $existing;
    } else {
        $booking = new Booking;
        $bookingData['ulid'] = (string) Str::ulid();
    }

    foreach ($bookingData as $key => $value) {
        $booking->attributes[$key] = $value;
    }
    $booking->save();

    if ($caregiver && $booking->caregiver_id) {
        $this->createCaregiverAssignment($booking, $status);
    }
});
```

**Why this is safe:** The existing code already wraps in `withoutEvents`, so boot methods (`creating`/`updating` handlers) and `booted` saved events don't run. Raw attribute setting is consistent with this pattern. Setting `$attributes` directly bypasses mutator methods while preserving the `datetime` cast on read.

#### Note on `payout_date`

`caregiver_payouts.payout_date` is a `datetime` column (not `timestamp`). `datetime` columns store literal strings with no timezone conversion. After this fix it stores `"2022-09-16 21:00:00"` (UTC literal) instead of `"2022-09-16 14:00:00"` (PT literal). When displayed in a PT timezone context, the frontend should convert using `toLocaleString` with `timeZone: 'America/Los_Angeles'` if needed.

### Step 2: Data Migration

Create a migration to fix existing records. Existing records have values like `"2026-05-28 09:00:00"` stored in `timestamp` columns — MySQL treats them as UTC (which is the connection timezone). But the actual intended time was 09:00 PT = 16:00 UTC, so we need to add the DST offset.

Fix all four Booking timestamp columns:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE bookings
            SET
                start_datetime = CONVERT_TZ(start_datetime, '+00:00', 'America/Los_Angeles'),
                end_datetime   = CONVERT_TZ(end_datetime,   '+00:00', 'America/Los_Angeles'),
                confirmed_at   = CONVERT_TZ(confirmed_at,   '+00:00', 'America/Los_Angeles'),
                cancelled_at   = CONVERT_TZ(cancelled_at,   '+00:00', 'America/Los_Angeles')
        ");
    }

    public function down(): void
    {
        // Reverse: convert back from PT to the wrong UTC
        DB::statement("
            UPDATE bookings
            SET
                start_datetime = CONVERT_TZ(start_datetime, 'America/Los_Angeles', '+00:00'),
                end_datetime   = CONVERT_TZ(end_datetime,   'America/Los_Angeles', '+00:00'),
                confirmed_at   = CONVERT_TZ(confirmed_at,   'America/Los_Angeles', '+00:00'),
                cancelled_at   = CONVERT_TZ(cancelled_at,   'America/Los_Angeles', '+00:00')
        ");
    }
};
```

**Warning:** `CONVERT_TZ` requires MySQL timezone tables to be loaded. Run this on the MySQL server if needed:
```sql
mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -p mysql
```

If `CONVERT_TZ` is unavailable, use a Laravel command:

```php
// Alternative in a command (slower but no DB dependency)
Booking::withoutEvents(function () {
    Booking::each(function (Booking $booking) {
        $ptStart = Carbon::parse($booking->getRawOriginal('start_datetime'), 'America/Los_Angeles');
        $ptEnd = Carbon::parse($booking->getRawOriginal('end_datetime'), 'America/Los_Angeles');

        DB::table('bookings')
            ->where('id', $booking->id)
            ->update([
                'start_datetime' => $ptStart->setTimezone('UTC'),
                'end_datetime' => $ptEnd->setTimezone('UTC'),
            ]);
    });
});
```

### Step 3: New Frontend Display Functions (`resources/js/lib/datetime.ts`)

Add three new timezone-aware functions alongside the existing `formatDisplay*` functions. Do **not** modify `parseAsLocal` or the existing `formatDisplay*` — they're still used by DatePicker/DateTimePicker for input value handling.

```typescript
/**
 * Formats a UTC ISO datetime string to a date in America/Los_Angeles.
 * Output example: "Monday, October 27, 2023"
 */
export const formatDisplayDateInPT = (
    dateStr: string | null | undefined,
): string => {
    if (!dateStr) return '';

    const date = new Date(dateStr);

    if (isNaN(date.getTime())) return '';

    return date.toLocaleDateString('en-US', {
        timeZone: 'America/Los_Angeles',
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

/**
 * Formats a UTC ISO datetime string to a time in America/Los_Angeles.
 * Output example: "9:15 AM"
 */
export const formatDisplayTimeInPT = (
    dateStr: string | null | undefined,
): string => {
    if (!dateStr) return '';

    const date = new Date(dateStr);

    if (isNaN(date.getTime())) return '';

    return date.toLocaleTimeString('en-US', {
        timeZone: 'America/Los_Angeles',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
};

/**
 * Formats a UTC ISO datetime string to a short date/time in America/Los_Angeles.
 * Output example: "Oct 27, 2023, 9:15 AM"
 */
export const formatDisplayDateTimeInPT = (
    dateStr: string | null | undefined,
): string => {
    if (!dateStr) return '';

    const date = new Date(dateStr);

    if (isNaN(date.getTime())) return '';

    return date.toLocaleString('en-US', {
        timeZone: 'America/Los_Angeles',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
};
```

### Step 4: Update Frontend Display Files (22 files)

Replace `formatDisplayDate`/`formatDisplayTime`/`formatDisplayDateTime` imports and usage with the new `formatDisplayDateInPT`/`formatDisplayTimeInPT`/`formatDisplayDateTimeInPT` in display-only pages.

**Mapping:**

| # | File | Current function | Replace with |
|---|------|-----------------|--------------|
| 1 | `guest/bookings/confirmation.tsx` | `formatDisplayDate`, `formatDisplayTime` | `formatDisplayDateInPT`, `formatDisplayTimeInPT` |
| 2 | `guest/bookings/payment.tsx` | `formatDisplayDate`, `formatDisplayTime` | `formatDisplayDateInPT`, `formatDisplayTimeInPT` |
| 3 | `guest/bookings/review.tsx` | `formatDisplayDate`, `formatDisplayTime` | `formatDisplayDateInPT`, `formatDisplayTimeInPT` |
| 4 | `client/bookings/index.tsx` | `formatDisplayDateTime` | `formatDisplayDateTimeInPT` |
| 5 | `client/bookings/show.tsx` | `formatDisplayDate`, `formatDisplayTime` | `formatDisplayDateInPT`, `formatDisplayTimeInPT` |
| 6 | `client/reviews/create.tsx` | `formatDisplayDate`, `formatDisplayTime` | `formatDisplayDateInPT`, `formatDisplayTimeInPT` |
| 7 | `dashboard/client.tsx` | `formatDisplayDateTime`, `formatDisplayTime` | `formatDisplayDateTimeInPT`, `formatDisplayTimeInPT` |
| 8 | `dashboard/caregiver.tsx` | `formatDisplayDate`, `formatDisplayDateTime` | `formatDisplayDateInPT`, `formatDisplayDateTimeInPT` |
| 9 | `dashboard/admin.tsx` | `formatDisplayDateTime`, `formatDisplayTime` | `formatDisplayDateTimeInPT`, `formatDisplayTimeInPT` |
| 10 | `dashboard/superadmin.tsx` | `formatDisplayDateTime`, `formatDisplayTime` | `formatDisplayDateTimeInPT`, `formatDisplayTimeInPT` |
| 11 | `caregiver/jobs/index.tsx` | `formatDisplayDate`, `formatDisplayDateTime`, `formatDisplayTime` | `formatDisplayDateInPT`, `formatDisplayDateTimeInPT`, `formatDisplayTimeInPT` |
| 12 | `caregiver/jobs/show.tsx` | `formatDisplayDate`, `formatDisplayTime` | `formatDisplayDateInPT`, `formatDisplayTimeInPT` |
| 13 | `caregiver/bookings/index.tsx` | Inline `new Date()` + `toLocaleString` | Use `formatDisplayDateTimeInPT` or inline with `timeZone: 'America/Los_Angeles'` |
| 14 | `caregiver/bookings/show.tsx` | `formatDisplayDate`, `formatDisplayTime` | `formatDisplayDateInPT`, `formatDisplayTimeInPT` |
| 15 | `admin/bookings/index.tsx` | `formatDisplayTime`, `parseAsLocal` | `formatDisplayTimeInPT`, keep `parseAsLocal` for calendar/sort |
| 16 | `admin/bookings/show.tsx` | `formatDisplayDate`, `formatDisplayTime` | `formatDisplayDateInPT`, `formatDisplayTimeInPT` |
| 17 | `admin/bookings/personal-info-section.tsx` | Inline `new Date()` + `toLocaleString` | Inline with `timeZone: 'America/Los_Angeles'` |
| 18 | `admin/caregivers/job-history.tsx` | `formatDisplayDateTime` | `formatDisplayDateTimeInPT` |
| 19 | `admin/clients/booking-history.tsx` | `formatDisplayDateTime` | `formatDisplayDateTimeInPT` |
| 20 | `admin/transactions/index.tsx` | Inline `parseAsLocal` + `toLocaleString` | Inline with `timeZone: 'America/Los_Angeles'` |

**Files that do NOT change** (input/calculation context — keep using existing wall-clock functions):
- `date-picker.tsx`, `datetime-picker.tsx` — input components need local wall-clock behavior
- `admin/bookings/booking-details-section.tsx` — input form
- `admin/bookings/use-booking-sheet.ts` — input/calculation logic
- `resources/js/lib/datetime.ts` — keep all existing functions
- `resources/js/lib/age.ts` — age calculation uses wall-clock dates
- PHP notification files — use `->format()` which outputs in `app.timezone` (UTC); these will show UTC times which is correct for absolute display

### Step 5: Build & Test

1. Run `npm run build` to verify no TypeScript errors
2. Run `php artisan test --compact` to verify all backend tests pass
3. Verify import command: `php artisan import:bubble --dry-run --staged-only --type=jobs`
4. Update any failing test assertions

## Existing Data Structure

- **Database engine**: MySQL (production)
- **Column type**: `timestamp` for both `start_datetime` and `end_datetime`
- **Model cast**: `'datetime:Y-m-d\TH:i:s'`
- **App timezone**: `'UTC'`
- **Connection timezone**: MySQL `time_zone = '+00:00'` (set by Laravel automatically)

## Risk Assessment

| Risk | Mitigation |
|------|-----------|
| Mutator double-converts on Eloquent hydrate | Eloquent hydration bypasses mutators — only explicit `setAttribute` triggers them |
| Not all input paths covered by mutator | Mutator covers ALL `Booking::create()` and `$booking->update()` calls — the only paths data enters |
| Import command model mutators double-convert | `importJob` uses raw `$attributes` to bypass mutators (Step 1b) |
| `CONVERT_TZ` unavailable in MySQL | Use Laravel command fallback (iterate with Carbon) |
| Frontend pages missed in the 22-file update | Build will catch TypeScript errors from missing imports; manual QA on booking display |
| Browser users outside PT see wrong times | All users are in California — `America/Los_Angeles` is the correct display timezone |
| Import command `payout_date` is `datetime` column | Stored as UTC literal; no timezone conversion. Display logic should convert if needed |

## Future Considerations

- If the business expands to multiple timezones, store `user.timezone` preference and use it in `toLocaleString` instead of hardcoding `America/Los_Angeles`
- Consider migrating `timestamp` → `datetime` for booking times to decouple from MySQL's timezone handling
