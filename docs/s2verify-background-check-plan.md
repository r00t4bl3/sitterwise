# S2Verify Background Check — Plan

## What exists today

| Item | Status | Details |
|------|--------|---------|
| `CaregiverStatus::BackgroundCheck` enum | ✅ Exists | `app/Enums/CaregiverStatus.php:10` — color `#3B82F6`, not terminal |
| `startBackgroundCheck()` route/controller | ✅ Exists | `routes/web.php:169`, `ApplicationController::startBackgroundCheck()` — **only flips status to `BackgroundCheck`, no API call** |
| `background_check` onboarding checklist item | ✅ Exists | `app/Models/OnboardingChecklistItem.php:31-33` — seeded via `seedForCaregiver()` on hire |
| Migration / Model / Enum / Config | ❌ None | No `background_checks` table, no `BackgroundCheck` model, no `BackgroundCheckStatus` enum, no `s2verify` config section |
| Caregiver `backgroundCheck()` relationship | ❌ None | 30+ relationships on Caregiver; this one missing |
| Compliance tab in admin profile | ❌ None | 7 tabs exist (summary, application, references, reviews, internal_rating, job_history, notes) — no Compliance tab |
| Webhook route/controller | ❌ None | No route for S2Verify callbacks |
| Hire integrity check | ❌ None | `hire()` only checks `BackgroundCheck` status, doesn't verify actual S2Verify result is `clear` |
| Config/services | ❌ None | No `s2verify` section in `config/services.php` |

## S2Verify API — what we know

**Website:** s2verify.com — NAPBS accredited background screening provider.

**API base:** `api.s2verifyconnect.com`

**Known facts:**
- Supports LLP, HTTP/HTTPS, FTP/FTPS/SFTP, SOAP, RESTful, Database, File system, TCP/IP
- Has an "Applicant Portal" (branded front-end for candidates) and "Management Portal" (admin dashboard)
- Integrates with ATS platforms like Workday, Greenhouse, Bullhorn, Fountain, UKG

**Unknowns (requires credentials to access):**
- Exact REST endpoint URLs and request/response formats
- Whether a hosted Connect widget exists (for SSN collection without touching our server)
- Webhook payload structure and delivery mechanism
- Authentication protocol (API key, OAuth, basic auth)

**Mitigation:** The `S2VerifyService` class abstracts all API calls. Phase 1 builds everything with stubbed calls (returning mock success). Phase 2 fills in real wiring once credentials are provisioned.

## Phase 1 — Data model + Admin UI (core)

### Schema — `background_checks` table

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigIncrements | PK |
| `caregiver_id` | foreignId → caregivers | **Unique** — one check record per caregiver |
| `status` | string (enum) | `not_initiated` / `pending` / `clear` / `review_required` / `failed` |
| `initiated_at` | timestamp | When check was submitted |
| `completed_at` | timestamp | nullable |
| `expires_at` | timestamp | nullable |
| `report_url` | string | nullable — link to S2Verify report |
| `external_id` | string | nullable — S2Verify's reference ID |
| `raw_response` | json | nullable — full webhook payload for debugging |
| timestamps | — | created_at, updated_at |

### Files to create (7)

| File | Purpose |
|------|---------|
| `database/migrations/..._create_background_checks_table.php` | Schema above |
| `app/Enums/BackgroundCheckStatus.php` | `not_initiated`, `pending`, `clear`, `review_required`, `failed` |
| `app/Models/BackgroundCheck.php` | BelongsTo caregiver, status casts, $fillable, $casts |
| `app/Http/Controllers/AdminBackgroundCheckController.php` | `initiate()`, `show()` handlers |
| `app/Services/S2VerifyService.php` | API wrapper — stubbed in Phase 1, wired in Phase 2 |
| `app/Jobs/InitiateBackgroundCheck.php` | Queued job calling S2Verify API (stubbed) |
| `app/Mail/AdminBackgroundCheckCompleteMail.php` | Notify admin on status change |
| `tests/Feature/AdminBackgroundCheckTest.php` | ~10 tests |

### Files to modify (5)

| File | Change |
|------|--------|
| `config/services.php` | Add `s2verify` section — `api_key`, `api_secret`, `api_base_url`, `webhook_secret` — all stubbed with env vars |
| `routes/web.php` | `POST /admin/caregivers/{caregiver}/initiate-background-check` (admin middleware), `GET /admin/caregivers/{caregiver}/background-check` |
| `app/Models/Caregiver.php` | Add `HasOne backgroundCheck()` relationship |
| `resources/js/pages/admin/caregivers/show.tsx` | Add "Compliance" tab with S2Verify panel |
| `app/Http/Controllers/ApplicationController.php` | Add guard to `hire()` — verify `BackgroundCheck` is `clear`, not just `BackgroundCheck` status |

### Key behaviors

**Initiate flow:**
1. Admin clicks "Initiate Background Check" on Compliance tab
2. `POST /admin/caregivers/{caregiver}/initiate-background-check`
3. Creates `BackgroundCheck` record with `status=pending`, `initiated_at=now`
4. Dispatches `InitiateBackgroundCheck` job (queued)
5. Button disabled while pending; result shown on completion

**Checklist sync:**
- Webhook or admin action sets status to `clear`
- Auto-complete `background_check` onboarding checklist item

**Admin notification:**
- `AdminBackgroundCheckCompleteMail` queued when status transitions to `clear` / `review_required` / `failed`

**Compliance tab UI:**
- Status badge (color-coded: green=clear, yellow=pending, red=failed, gray=not_initiated)
- Dates row: Initiated, Completed, Expires
- Report link (opens S2Verify report in new tab)
- Initiate button (only when `not_initiated`)
- Loading state while pending

**Hire guard:**
- Current: `hire()` checks `$caregiver->status === BackgroundCheck`
- Should check: `$caregiver->backgroundCheck?->status === 'clear'`

### Tests (Phase 1 — ~10)

- Initiate creates record with correct status
- Initiate dispatches job
- Shows returns check details
- Admin-only authorization (403 for non-admin)
- Invalid transitions rejected
- Hire blocked when background check not clear
- Checklist sync on clear status

### SSN security note

SSN must go directly to S2Verify — never through our server. The `InitiateBackgroundCheck` job sends caregiver PII (name, DOB, address) but NOT SSN. The S2Verify Connect widget (if available) or a redirect flow handles SSN collection on their side.

## Phase 2 — S2Verify API + webhook

| Task | Details |
|------|---------|
| `S2VerifyService::initiate()` | Real API call — sends caregiver PII, receives `external_id` |
| `S2VerifyService::getStatus()` | Poll status by `external_id` |
| `S2VerifyService::handleWebhook()` | Validate signature, parse payload, update record |
| `InitiateBackgroundCheck` job | Real API call with error handling, retries |
| Webhook route | `POST webhooks/s2verify` (excluded from CSRF) — updates `status`, `expires_at`, `report_url` from `raw_response` |
| Tests | Webhook signature validation, status updates, invalid payload handling |

## Phase 3 — Expiration + caregiver flow

| Task | Details |
|------|---------|
| Expiration reminders | Command checking 90 / 60 / 30 / 7 day thresholds (matching spec §13.4) |
| Auto-block | Prevent job offers / caregiver approval when expired or failed |
| Caregiver-initiated | "Start My Background Check" button on dashboard (post-interview) with cost-awareness modal ($30–50) |
| Tests | Expiration logic, auto-block enforcement, caregiver-initiated flow |

## Effort estimate

| Phase | Days | Dependencies |
|-------|------|--------------|
| Phase 1 | ~2 | None — stubbed API calls |
| Phase 2 | ~1–2 | S2Verify credentials + API docs |
| Phase 3 | ~1 | Phase 1 complete |

Total: **~4–5 days** (Phase 1 deliverable and testable independently; 2–3 if API docs are available upfront).
