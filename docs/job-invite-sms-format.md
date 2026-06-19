# Job Invite SMS Format

**Purpose:** Standardize the SMS sent to caregivers when a new job is posted. The first 40–50 characters must fit in the lock-screen preview so caregivers can decide whether to open it.

---

## Format

```
New job – [DAY] [DATE], [START]–[END]
[LOCATION] · [KID COUNT] ([AGES])
View & claim: [LINK]
```

Keep the whole message under ~160 characters (single SMS).

---

## Dynamic Rules

### 1. Line 1 – Day, date & time window (always first)

This is the only line visible in the lock-screen preview.

### 2. Location

| Venue type | Display |
|---|---|
| Private residence | Neighborhood name (e.g. "Coronado") |
| Hotel | Hotel name (e.g. "Hotel del Coronado") |

### 3. Kid count & ages

| Count | Format |
|---|---|
| 1 child | `1 child (3)` |
| 2+ children | `2 children (4 & 7)` |

### 4. Link

Last line, no trailing punctuation — keeps the URL cleanly tappable. Use short URLs if possible (`sitterwise.io/j/XXXX`).

---

## Single-Date Examples

**Private home:**
```
New job – Sat 6/20, 5–10pm
Coronado · 2 children (4 & 7)
View & claim: sitterwise.io/j/4821
```

**Hotel:**
```
New job – Fri 6/26, 6–11pm
Hotel del Coronado · 1 child (3)
View & claim: sitterwise.io/j/4837
```

---

## Multi-Day Jobs

### 6. Date range on Line 1

| Span | Format |
|---|---|
| Same month | `Mon 6/22–Wed 6/24` |
| Crossing months | `Mon 6/29–Wed 7/1` |

Lead with the start day so the preview still shows when it begins.

### 7. Hours across days

| Scenario | Display |
|---|---|
| Same window each day | Show once with "daily" (e.g. `9am–5pm daily`) |
| Overnight / around-the-clock | `overnight` or `24h` instead of a clock window |
| Hours differ by day | `hours vary – tap for details` |

### 8. Character budget fallback

Date ranges + "daily/overnight" wording eats into the 160-character limit. If the message would run over, use this compact format:

```
New job – [START]–[END] (multi-day) · [LOCATION] · [CHILD COUNT] · tap for hours/ages: [LINK]
```

### Multi-Day Examples

**Weekend, same hours each day:**
```
New job – Fri 6/26–Sun 6/28, 9am–5pm daily
Coronado · 2 children (4 & 7)
View & claim: sitterwise.io/j/4901
```

**Hours vary by day:**
```
New job – Mon 8/3–Wed 8/5, hours vary – tap for details
Coronado · 3 children (2, 5 & 8)
View & claim: sitterwise.io/j/4933
```

---

## Current SMS Implementation

**Notification:** `BookingInvitationNotification` → `via` includes `'sms'`
**SMS Channel:** Twilio via custom `sms` channel
**Key method:** `toSms()` builds the message string
**Relevant files:**
- `app/Notifications/BookingInvitationNotification.php`
- `app/Models/User.php` — `routeNotificationForSms()`
- `app/Services/TwilioService.php`

> Note: The current `toSms()` in `BookingInvitationNotification` may still use an older format. This document serves as the target format specification.

---

## Current State

> Assessment of the existing infrastructure and what's needed to adopt this format.

**Already exists:**

| Component | Status | File |
|---|---|---|
| SMS notification via Twilio | ✅ Done | `app/Notifications/BookingInvitationNotification.php`, `app/Services/TwilioService.php` |
| Hook to build message text (`toSms()`) | ✅ Exists | `BookingInvitationNotification::toSms()` |
| Short job URL structure | ✅ Exists | Route pattern `bookings.show` |
| Kid count + ages on booking model | ✅ Exists | `Booking` model relationships |
| Location data (neighborhood, hotel) | ✅ Exists | Booking address / hotel fields |
| Multi-day booking group support | ✅ Exists | `BookingGroup`, `Booking::bookingGroup` |

**Needs work:**

| Component | Status | Details |
|---|---|---|
| `toSms()` format output | 🔄 Needs rewrite | Must produce new format (see above) |
| Location → neighborhood / hotel name | 🔄 Logic change | Extract display name from address/hotel fields |
| Singular vs. plural kid count | 🔄 Logic change | "1 child" vs "2 children" |
| Multi-day date range formatting | ❌ Missing | Day range, "daily"/"overnight"/"hours vary" logic |
| Character budget enforcement | ❌ Missing | Truncate/fallback if >160 chars |
| Short link generation | 📌 Verify | Confirm `sitterwise.io/j/{id}` is accessible |

---

## Effort Breakdown

### 1. `toSms()` Format Rewrite (~1–2 hours)

| Task | Details |
|---|---|
| Build Line 1: day/date/time window string | Single-date vs. multi-date logic |
| Build Line 2: location · kid count (ages) | Extract neighborhood/hotel name, pluralize kid count, format ages |
| Build Line 3: short link | Generate `sitterwise.io/j/{booking.id}` or `{ulid}` |
| Assemble full message | Join lines with newlines |
| Tests: single-date, hotel, private home | 3–4 test cases in `BookingInvitationNotificationTest.php` or `BookingSmsTest.php` |

### 2. Multi-Day Support (~1–2 hours)

| Task | Details |
|---|---|
| Date range string | "Fri 6/26–Sun 6/28", handle month cross-over |
| Hours display | Same window → "9am–5pm daily", overnight → "overnight", varies → "hours vary – tap for details" |
| Fallback format | Compact one-liner if >160 chars |
| Tests: multi-date same hours, varying hours, month cross-over | 3–4 test cases |

### 3. Location Display Logic (~0.5–1 hour)

| Task | Details |
|---|---|
| Determine venue type | Check if hotel → use hotel name, else → use neighborhood |
| Extract neighborhood name | From booking address data |
| Tests: private home, hotel | 2 test cases |

### 4. Character Budget Enforcement (~0.5 hour)

| Task | Details |
|---|---|
| Count characters, fall back to compact format if >160 | Single conditional |
| Tests: message under budget, message over budget | 2 test cases |

### 5. Short Link (if needed, ~0.5 hour)

| Task | Details |
|---|---|
| Verify `sitterwise.io/j/{id}` route exists or add it | Route + controller or URL shortener |
| Tests: link resolves to correct booking | 1 test case |

---

## Effort Summary

| Scope | Complexity | Estimated Effort |
|---|---|---|
| **Core format rewrite** (single-date) | **Easy** | **~1–2 hours** |
| + Multi-day support | Medium | ~2–4 hours |
| + Location display logic | Easy | ~2.5–5 hours |
| + Character budget enforcement | Easy | ~3–5.5 hours |
| + Short link (if needed) | Easy | ~3.5–6 hours |
| **Full implementation** (all of the above) | **Medium** | **~3.5–6 hours** |
