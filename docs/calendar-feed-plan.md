# Calendar Feed (iCal Subscription)

> **Status: Implemented.** Caregivers can subscribe to their confirmed jobs from any calendar
> app via a private iCal feed URL. No OAuth, no provider-specific code.

Each caregiver gets a unique, secret feed URL:

```
GET https://sitterwise.com/calendar/feed/{token}.ics
```

It returns a standard `.ics` file with the caregiver's **upcoming Confirmed** bookings. The
caregiver pastes the URL into their calendar app → the app subscribes and auto-refreshes on its
own polling schedule (calendars are told to re-check every 15 minutes via `X-PUBLISHED-TTL`).

When a booking is cancelled or completed, it drops off the feed on the next refresh; when a new
booking is confirmed, it appears on the next refresh.

---

## How the `.ics` is generated

The feed is built with the **`spatie/icalendar-generator`** package (added as a dependency), not
a hand-rolled string builder — the package handles CRLF line endings, 75-octet line folding,
text escaping, and `VTIMEZONE` generation correctly.

### Timezone (important)

Booking `start_datetime` / `end_datetime` are stored in **UTC** (`Booking::convertToUtc` mutator;
`config/app.php` timezone is `UTC`). The feed converts each stored UTC instant to the business
timezone before emitting it, so events carry `TZID=America/Los_Angeles` (with an auto-generated
`VTIMEZONE`) — exactly the `->copy()->setTimezone('America/Los_Angeles')` pattern the email/CSV
code already uses (`Booking::toEmailData`). Events are **not** emitted as floating local time.

### Event mapping

| iCal field | Source |
|-----------|--------|
| `UID` | `booking-{ulid}@sitterwise.com` (stable per booking) |
| `DTSTART` / `DTEND` | booking start/end, converted to `America/Los_Angeles` (`TZID`) |
| `SUMMARY` | `{service_type label} - {client name}` |
| `DESCRIPTION` | `Booking #{ulid}` + service type |
| `LOCATION` | hotel name, else `city, state` |
| `STATUS` | `CONFIRMED` |
| calendar `REFRESH-INTERVAL` / `X-PUBLISHED-TTL` | 15 minutes |

### Filtering

Only the caregiver's own bookings where `status = Confirmed` **and** `end_datetime > now()`
(upcoming). Past, Received, Pending, Completed and Cancelled bookings are excluded. No event
limit. An empty result still returns a valid (event-less) `VCALENDAR`.

---

## Files

### Migration — `caregivers.calendar_feed_token`
`database/migrations/2026_07_18_172752_add_calendar_feed_token_to_caregivers_table.php`
```php
$table->string('calendar_feed_token', 64)->nullable()->unique()->after('user_id');
```
Added to `Caregiver::$fillable`. Mirrors the existing `status_token` secret-URL pattern.

### `app/Services/CalendarFeedService.php`
- `ensureToken(Caregiver): string` — returns the existing token or generates one
  (`Str::random(32)`) and persists it (lazy — the settings page always has a URL).
- `regenerateToken(Caregiver): string` — overwrites the token, revoking old subscriptions.
- `buildCalendar(Caregiver): string` — queries upcoming Confirmed bookings (same pattern as the
  caregiver dashboard) and builds the `.ics` via spatie `Calendar`/`Event`.

### `app/Http/Controllers/CalendarFeedController.php` (public, invokable)
Looks up the caregiver by `calendar_feed_token`; on miss logs `Log::warning('Calendar feed:
invalid token', [...])` and `abort(404)`. On hit returns the `.ics` with headers:
`Content-Type: text/calendar; charset=utf-8`, `Content-Disposition: inline;
filename="sitterwise.ics"`, `Cache-Control: public, max-age=900`.

### `app/Http/Controllers/Settings/CalendarSyncController.php`
`show` / `regenerate`, each guarded by `if (! $user->isCaregiver()) redirect()->route('profile.edit')`
(the same in-controller gating the other caregiver settings pages use). `show` ensures a token
and passes `feedUrl` to the page; `regenerate` rotates the token and redirects back.

### Routes
- `routes/web.php` (public area, before the `auth`/`verified` group):
  ```php
  Route::get('/calendar/feed/{token}.ics', CalendarFeedController::class)
      ->where('token', '[A-Za-z0-9]+')
      ->middleware('throttle:calendar-feed')
      ->name('calendar.feed');
  ```
- `routes/settings.php` (in the `['auth','verified']` group):
  `settings.caregiver.calendar-sync` (GET) and `settings.caregiver.calendar-sync.regenerate` (POST).
- `app/Providers/AppServiceProvider.php` — a named `calendar-feed` rate limiter
  (`Limit::perMinute(60)->by(ip)`, `Limit::none()` in the `testing` env).

### Frontend
- `resources/js/pages/settings/calendar-sync.tsx` — read-only feed URL with a copy button
  (reusing the `useClipboard` hook), a confirm-gated "Regenerate Link" action, and subscribe
  instructions for Google / Apple / Outlook.
- `resources/js/layouts/settings/layout.tsx` — a caregiver-only "Calendar Sync" sidebar item.

---

## Caching

Header-only for now: `Cache-Control: public, max-age=900` (15 min). A server-side cached `.ics`
busted on booking change can be added later if load ever warrants it — not needed at current
scale.

---

## Security

- Token is `Str::random(32)` (base62 ≈ 190 bits) in a unique column — infeasible to guess or
  brute-force. The route constraint `[A-Za-z0-9]+` keeps the `.ics` suffix unambiguous.
- If a token leaks, it exposes only booking dates/times, service type, client name, and location
  — never credentials, payment data, or other caregivers' data. The caregiver can **regenerate**
  the token instantly to revoke access.
- The `throttle:calendar-feed` limiter (60/min per IP) blocks token scanning; invalid tokens are
  logged via `Log::warning` for visibility.

---

## Tests — `tests/Feature/CalendarFeedTest.php`

| Test | Covers |
|------|--------|
| valid token → 200 + `text/calendar` | happy path |
| invalid token → 404 | no leak on a bad token |
| upcoming-Confirmed-only | Received / Pending / Completed / Cancelled / past excluded |
| no cross-caregiver leak | a caregiver never sees another's bookings |
| **timezone regression** | events render in Pacific (`America/Los_Angeles`), not raw UTC |
| empty feed | valid event-less `VCALENDAR` |
| regenerate invalidates old token | old URL 404s, new URL works |
| settings page | caregiver sees their URL; non-caregiver is redirected |

---

## Notes / gotchas

- `resources/js/actions` and `resources/js/routes` are **Wayfinder-generated and gitignored**.
  If they need regenerating, use `php artisan wayfinder:generate --with-form` (the `--with-form`
  flag matches `vite.config.ts` `formVariants: true`; the plain CLI command omits the `.form`
  variants that existing pages rely on, which breaks `tsc`). Normally the Vite plugin regenerates
  them on `npm run dev` / build.

## Out of scope (not built)

- Server-side cached `.ics` with change-based busting (headers-only is enough today).
- A dashboard "opt-in / coming soon" banner — the lazy `ensureToken()` on the settings page makes
  the URL always available, so no opt-in step is needed.
