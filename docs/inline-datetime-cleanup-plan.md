# Inline Datetime Cleanup Plan

> **Goal:** Eliminate all raw `toLocaleDateString`/`toLocaleTimeString`/`toLocaleString` calls and duplicated date-formatting functions, replacing them with centralized helpers from `@/lib/datetime`.

## Available Helpers (`@/lib/datetime`)

| Function | Output |
|---|---|
| `formatDisplayDateInPT(str)` | `"Monday, October 27, 2023"` |
| `formatDisplayDateShortInPT(str)` | `"Oct 27, 2023"` |
| `formatDisplayTimeInPT(str)` | `"9:15 AM"` |
| `formatDisplayDateTimeInPT(str)` | `"Oct 27, 2023, 9:15 AM"` |
| `formatUtcStringFromPt(ptDate)` | `"2026-05-31T22:15"` |
| `autoSetEndDateTime(startStr)` | `"2026-05-31T22:15"` |
| `validateMinimumDuration(startStr, endStr)` | `null` or error string |

## Test-Confirmed Reference

The following UTC→PT mapping is verified by tests:

| UTC string (from API) | PT display | Source |
|---|---|---|
| `"2026-05-28T16:00:00.000000Z"` | `"9:00 AM"` | `BookingTest.php:1686`, `GuestBookingEndToEndTest.php:283` |
| Same | `"Thursday, May 28, 2026"` (inferred from `formatDisplayDateInPT`) | Same tests |
| Same | `"May 28, 2026"` (inferred from `formatDisplayDateShortInPT`) | Same tests |

May 28 = **PDT** (UTC-7), so `16:00 UTC - 7h = 09:00 PT`. The backend confirms it stores `16:00 UTC` for a `9:00 AM PT` user input.

**What the bugs look like** for the same UTC value `"2026-05-28T16:00:00.000000Z"`:

| File | Current code | Output (browser in EDT) | Correct PT |
|---|---|---|---|
| `personal-info-section.tsx` | `new Date(s).toLocaleDateString('en-US')` no `timeZone` | `"May 28, 2026 12:00 PM"` | `"May 28, 2026 9:00 AM"` |
| `caregiver/bookings/index.tsx` | Already has `timeZone` — consistent format | `"5/28/2026"` (locale-default) | `"May 28, 2026"` |
| `settings/pause.tsx` | `new Date(iso).toLocaleDateString` no `timeZone` | `"May 28, 2026"` (same day, no time shown) | `"Thursday, May 28, 2026"` (consistent format) |
| `admin/clients/show.tsx` | `new Date(tc.changed_at).toLocaleDateString()` no locale/tz | `"5/28/2026"` | `"May 27, 2026"` when UTC is `2026-05-28T05:00:00Z` |

## Priority 1 — User-Facing Timezone Bugs

These render datetimes without PT timezone, showing the wrong time to users.

| # | File | Line(s) | Current | Fix |
|---|---|---|---|---|
| 1a | `admin/bookings/personal-info-section.tsx` | 247–274 | `new Date(form.data.start_datetime)`, `.toLocaleDateString('en-US', dateOptions)` — no `timeZone` option | Import `formatDisplayDateShortInPT`, `formatDisplayTimeInPT`; replace the IIFE |
| 1b | `admin/bookings/personal-info-section.tsx` | 32–46 | Local `MONTH_ABBR = ['', 'Jan', 'Feb', ...]` constant | Replace with programmatic `Intl.DateTimeFormat` version |
| 2 | `caregiver/bookings/index.tsx` | 253–257 | `new Date(booking.notified_at).toLocaleDateString('en-US', { timeZone: 'America/Los_Angeles' })` | Replace with `formatDisplayDateShortInPT(booking.notified_at)` |
| 3 | `settings/pause.tsx` | 54–62 | Local `formatPausedDate` using `toLocaleDateString('en-US', {...})` — no PT timezone | Replace with `formatDisplayDateInPT` |
| 4 | `admin/clients/show.tsx` | 1181–1183 | `new Date(tc.changed_at).toLocaleDateString()` — no locale, no timezone | Replace with `formatDisplayDateShortInPT(tc.changed_at)` |

## Priority 2 — Duplicated `formatDateTimeLocal`

Four identical copies of a function that converts a Date to `YYYY-MM-DDTHH:mm`. This should live in `@/lib/datetime` once.

| # | File | Lines | Signature |
|---|---|---|---|
| 5a | `admin/bookings/use-booking-sheet.ts` | 103–111 | `function formatDateTimeLocal(date: Date): string` |
| 5b | `caregiver/jobs/index.tsx` | 141–149 | Same — file already imports from `@/lib/datetime` |
| 5c | `client/bookings/create.tsx` | 93–101 | Same — file already imports from `@/lib/datetime` |
| 5d | `guest/bookings/create.tsx` | 51–59 | Same — file already imports from `@/lib/datetime` |

**Suggested fix:** Add `formatDateTimeLocal` (or reuse `formatUtcStringFromPt`) to `@/lib/datetime`, then update all 4 callers.

## Priority 3 — Duplicated Same-Day Date Range Display

Two nearly identical IIFEs that detect same-day and format either `"Date Time - Time"` or `"Date Time - Date Time"`.

| # | File | Lines | Detail |
|---|---|---|---|
| 6a | `admin/bookings/personal-info-section.tsx` | 247–274 | Admin booking sheet header (also Priority 1a) |
| 6b | `caregiver/jobs/index.tsx` | 749–782 | Checkout sheet summary — already has `timeZone: 'America/Los_Angeles'` but still inline |

**Suggested fix:** Extract into a helper `formatDisplayDateTimeRangeInPT(startStr, endStr)` in `@/lib/datetime`.

## Priority 4 — Local Formatting Functions in Components (No PT Concern)

These exist in shared UI components and don't display booking datetimes, so they don't cause timezone bugs. They'd still benefit from centralizing.

| # | File | Lines | Pattern |
|---|---|---|---|
| 7 | `admin/availabilities/index.tsx` | 91–97 | Local `formatDateHeader` — `toLocaleDateString('en-US', { weekday: 'short' })` |
| 8 | `components/availability-calendar.tsx` | 45–49 | Local `getMonthName` — `toLocaleDateString('en-US', { month: 'long' })` |
| 9 | `components/ui/calendar.tsx` | 42 | Inline `date.toLocaleString("default", { month: "short" })` |
| 10 | `components/ui/calendar.tsx` | 198 | Inline `day.date.toLocaleDateString()` (data attribute) |
| 11 | `admin/clients/edit.tsx` | 842–850 | Inline `new Date(2000, month - 1).toLocaleString('default', { month: 'short' })` |
| 12 | `public/caregiver-apply/wizard.tsx` | 122–126 | Inline `new Date().toLocaleDateString('en-US', {...})` for today's date |

## Summary

| Priority | Count | Files |
|---|---|---|
| P1 (timezone bugs) | 4 files, 5 locations | `personal-info-section.tsx`, `caregiver/bookings/index.tsx`, `settings/pause.tsx`, `admin/clients/show.tsx` |
| P2 (duplicated function) | 4 files | `use-booking-sheet.ts`, `caregiver/jobs/index.tsx`, `client/bookings/create.tsx`, `guest/bookings/create.tsx` |
| P3 (duplicated display) | 2 files (overlap with P1/P2) | `personal-info-section.tsx`, `caregiver/jobs/index.tsx` |
| P4 (UI components) | 6 files | Various shared components |
