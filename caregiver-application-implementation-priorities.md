# Implementation Priorities

> Reference wireframe: `sitterwise_wireframe_v2.html` — 7 screens covering onboarding, cancellation, milestones, pause, admin dashboard, interview eval, assignments view.

## ✅ Completed

### 1. Fix relational records from wizard data
All wizard-to-relational-record mappings implemented in `CaregiverApplicationController@submit()`:

- `age_groups` → `specialty_types` sync (babies→id:1, toddlers→id:2, preschool→id:3, school_age→id:4)
- `location` → `locations` sync with flexible logic (north_county→North County, south_east_county→South County, flexible→metadata)
- `education.*` → `CaregiverEducation` records with degree + expanded education_type
- Attribute matches: `petsitting`→`pet_sitting`, `driving`→`has_vehicle`, `smokes=no`→`non_smoker`
- Caregiver columns set: `biography`, `languages`, `education_level`, `metadata`
- All syncs defensive (query existing IDs first) — works with or without seed data

Admin specialty/location/education/attribute sections now populate immediately on submission.

### 2. Expand reference form to spec

- Migration: added 6 rating columns + strengths/concerns/additional_comments, dropped old rating/feedback
- Frontend: `submit.tsx` rewritten with 6 per-category `StarRating` controls + 3 text fields
- Backend: `SubmitReferenceRequest` validation + `ReferenceController` store updated
- Admin caregiver profile: composite average badge with expandable 6-category detail + strengths/concerns/comments
- Application show page: same composite avg + expandable detail
- Tests rewritten for new field names

### Specialty type colors
`color_bg`/`color_text` columns added to `specialty_types`. "Special Needs" record removed (collected as qualification, not age-group). Colors seeded on 4 records. Frontend `SpecialtyTag` reads from DB props instead of hardcoded map.

### Wizard Steps 4 cleanup
Removed `skills.*` fields (special_needs, swimming, driving, bilingual, other) from wizard — they overlapped with Step 7 qualifications. Validation rules and `defaultFormData` updated accordingly.

### Low #1 — Admin caregiver profile tabs — ✅ Completed

Tabbed profile layout in `resources/js/pages/admin/caregivers/show.tsx`:

- 8 tabs: Summary, Application, References, Reviews, Internal Rating, Job History, Compliance, Notes
- Summary is the default/leftmost tab (wireframe match)
- Deferred props for Reviews and Job History (lazy-loaded)
- Job History table with resolution badges, late-arrival flags, client links

**Files:** `resources/js/pages/admin/caregivers/show.tsx`

**Wireframe:** "Caregiver Assignments View" screen — profile header, 7 tabs, Job History table.

### Low #2 — Self-service hold/resume + inactivity automation — ✅ Completed

**Self-service pause (caregiver-facing):**
- Pause form at `/settings/caregiver/pause` with optional `resume_by` date and `pause_reason`
- One-tap resume on same screen, no admin required
- Status transitions: Active ↔ On Hold (pause/resume)
- `CaregiverPause` model + factory, `paused_at`/`resume_by`/`pause_reason`/`resumed_at` columns

**Admin resume:**
- `POST /admin/caregivers/{caregiver}/resume` route (admin middleware)
- `CaregiverController@resumeCaregiver()` — sets status to Active, marks `resumed_at`
- Pause info card in admin sidebar with Resume button
- 3 tests: admin resume, no active pause error, non-admin 403

**Inactivity automation (Artisan commands):**
- `app:check-in-on-hold-caregivers` — 3-tier mail at 30/45/60d
- `app:archive-long-term-inactive` — 166d warning, 180d archive to Inactive
- `AdminCaregiverArchivedMail` queued to all admin + super_admin users on archive
- `CaregiverOnHoldCheckinMail` (3 tiers: checkin, reminder, final)
- `CaregiverArchiveWarningMail` at 166d

**Tests:** 12 tests in `CaregiverPauseTest.php`

**Wireframe:** Full design in "Pause Account" screen — hold card with form, callout explaining resume.

### Low #3 — Caregiver cancellation flow — ✅ Completed

**Backend — `AssignmentController` with 4 actions:**
- `backOut()` — caregiver back-out with required reason
- `excuse()` — marks excused with optional note
- `logNoShow()` — no-show resolution
- `logLateArrival()` — late arrival flag (always available, even after final resolution)
- Booking status stays `Confirmed` — resolution on assignment, not booking
- `app:check-late-arrivals` daily at 11:00 flags 3+ late arrivals in 60 days

**Admin notification:** `AdminCaregiverBackedOutMail` (queueable Blade view) sent to all admins.

**Frontend — caregiver jobs page:** Cancel Job button + cancellation Dialog (wireframe match).

**Frontend — admin job history:** Resolution badges + Excuse/No-Show/Late dialogs.

**Tests:** 11 tests in `CaregiverCancellationTest.php`

**Wireframe:** "Cancellation Flow" screen — modal with job summary, warning, reason field.

### Email footer standardization — ✅ Completed

Standardized footer across all 17 email templates:
- `This is an automated notification from Sitterwise.`
- `Sitterwise — San Diego's most trusted childcare agency.`

Each template had existing content preserved (reference-specific, booking-specific text) with missing lines appended. `reference-completed` had old copy replaced with standardized version.

## Caregiver Lifecycle Flow

```mermaid
flowchart LR
    A[Applicant] -->|approve| B[Under Review]
    B -->|scheduleInterview| C[Interview Scheduled]
    C -->|startBackgroundCheck| D[Background Check]
    D -->|hire| E[Hired / Onboarding]
    E -->|completeOnboarding| F[Active]
    A -->|decline| I[Inactive]
    B -->|decline| I
    C -->|decline| I
    D -->|decline| I
    E -->|decline| I

    F -.->|inactivity 45d+| I
    I -.->|admin re-activate| F
    F -->|admin action| G[Fired]
    F -->|admin action| H[On Hold]
    H -->|self-service resume| F

    style A fill:#F48A91,color:#fff
    style B fill:#F59E0B,color:#fff
    style C fill:#8B5CF6,color:#fff
    style D fill:#3B82F6,color:#fff
    style E fill:#0EA5E9,color:#fff
    style F fill:#22C55E,color:#fff
    style G fill:#DC2626,color:#fff
    style H fill:#8B5CF6,color:#fff
    style I fill:#6B7280,color:#fff
```

**Onboarding barrier** (blocks `Hired/Onboarding → Active`):

```
┌──────────────────────────────────────────────┐
│          Onboarding Checklist                 │
├──────────────────────────────────────────────┤
│ ☐ OnPay setup complete (admin emails self)   │
│ ☐ Background check clear                     │
│ ☐ CPR uploaded and valid                     │
│ ☐ Trustline submitted                        │
│ ☐ Dress code acknowledged                    │
│ ☐ Training quiz passed                       │
├──────────────────────────────────────────────┤
│ ➜ All 6 done → completeOnboarding → Active   │
│ ➜ >30d stalled → auto-revert to Inactive     │
└──────────────────────────────────────────────┘
```

## High (foundational — fix existing gaps, complete Phase 1-2 core)

### High #1 — Stalled application nudges (§4.1) — ✅ Completed

#### Implementation

**Tracking:**
- `IncompleteApplication` model (`app/Models/IncompleteApplication.php`) with scopes: `needsNudge` (48h inactive + not nudged recently), `stale` (14d+), `expired` (90d+)
- Migration: `database/migrations/2026_05_24_095326_create_incomplete_applications_table.php`
- `CaregiverApplicationController@saveProgress()` creates/updates tracking on each step save
- `CaregiverApplicationController@submit()` deletes tracking record on successful submission

**Commands:**
- `app:nudge-incomplete-applications` — sends `ApplicantResumeApplicationMail` at 48h (nudge_count 0→1), sends `ApplicantFinalReminderMail` at 7d (nudge_count 1→2)
- `app:archive-stalled-applications` — sets `archived_at` at 14d, hard-deletes at 90d

**Mailables + Templates:**
- `app/Mail/ApplicantResumeApplicationMail.php` → `resources/views/emails/resume-application.blade.php`
- `app/Mail/ApplicantFinalReminderMail.php` → `resources/views/emails/final-reminder.blade.php`
- Both queued, following existing project email patterns

**Scheduling:**
- `app:nudge-incomplete-applications` → every 6 hours via `routes/console.php`
- `app:archive-stalled-applications` → daily via `routes/console.php`

**Tests:** 25 tests covering model scopes, both nudge tiers, archive/deletion, idempotency, and integration with saveProgress/submit endpoint.

**Files created/modified:**
| File | Action |
|------|--------|
| `app/Models/IncompleteApplication.php` | Created |
| `database/migrations/..._create_incomplete_applications_table.php` | Created |
| `app/Console/Commands/NudgeIncompleteApplications.php` | Implemented handle() |
| `app/Console/Commands/ArchiveStalledApplications.php` | Implemented handle() |
| `app/Mail/ApplicantResumeApplicationMail.php` | Implemented |
| `app/Mail/ApplicantFinalReminderMail.php` | Implemented |
| `resources/views/emails/resume-application.blade.php` | Created |
| `resources/views/emails/final-reminder.blade.php` | Created |
| `routes/console.php` | Added scheduling |
| `tests/Feature/CaregiverIncompleteApplicationTest.php` | Created (25 tests) |

**Impact:** New feature — directly affects lead conversion by recovering abandoned applications.

**Wireframe:** Not shown — backend workflow, no frontend screen.

## Medium (complete the pipeline — reference lifecycle, admin workflow)

### Medium #1 — Reference nudges (§5.5) — ✅ Completed

#### Implementation

**Command:** `app:nudge-pending-references {--reference-side} {--applicant-side}`
- No flag = runs both sides

**Reference-side nudges:**
- Day 2–4: sends `ReferenceReminderMail` to pending references
- Day 5+: sends `ReferenceFinalReminderMail` to pending references
- Uses `created_at` with date windows — no schema changes needed

**Applicant-side nudges:**
- Day 3–6: sends `ApplicantPendingReferencesMail` (similar tone to first reminder)
- Day 7–13: sends same mail with stronger wording ("over a week")
- Day 14+: sets caregiver status to `Inactive` (stalled)

**Files created:**

| File | Action |
|------|--------|
| `app/Console/Commands/NudgePendingReferences.php` | Created |
| `app/Mail/ReferenceReminderMail.php` | Created |
| `app/Mail/ReferenceFinalReminderMail.php` | Created |
| `app/Mail/ApplicantPendingReferencesMail.php` | Created |
| `resources/views/emails/reference-reminder.blade.php` | Created |
| `resources/views/emails/reference-final-reminder.blade.php` | Created |
| `resources/views/emails/applicant-pending-references.blade.php` | Created |
| `routes/console.php` | Added scheduling (daily at 9am) |
| `tests/Feature/ReferenceNudgeTest.php` | Created (12 tests) |

**Tests:** 12 tests covering: first/final reminder delivery, skip completed/too-recent/archived, applicant prompts at day 3/7/14, cross-flag isolation, combined mode.

**Wireframe:** Not shown — backend scheduled commands.

### High #2 — Application lifecycle workflow — ✅ Completed

#### Current Implementation

**Enum status transitions (as implemented):**
- `Applicant` → approve() → `UnderReview`
- `UnderReview` → scheduleInterview() → `InterviewScheduled`
- `InterviewScheduled` → startBackgroundCheck() → `BackgroundCheck`
- `BackgroundCheck` → hire() → `HiredOnboarding`
- `HiredOnboarding` → completeOnboarding() → `Active`

**New enum values added to `CaregiverStatus`:**
- `UnderReview` ("Under Review", color `#F59E0B`)
- `InterviewScheduled` ("Interview Scheduled", color `#8B5CF6`)
- `BackgroundCheck` ("Background Check", color `#3B82F6`)
- New `terminal()` helper returning [Active, Inactive, NonStarter, Fired, Ineligible, OnHold]
- **Gap:** `Hired/Onboarding` status not yet added to enum. `hire()` currently jumps to `Active` — must transition to `Hired/Onboarding` instead. Onboarding checklist (#10) gates the `Hired/Onboarding → Active` transition.

**Backend:**
- `ApplicationController` gained 5 action methods: `approve()`, `scheduleInterview()`, `startBackgroundCheck()`, `hire()`, `decline()`
- Each validates current status and rejects invalid transitions with 422
- `ApplicationActionRequest` validates admin authorization and optional decline note
- `ApplicantDeclinedMail` + template sends decline notification to applicant with optional reason
- All routes added under admin middleware

**Frontend — `applications/show.tsx`:**
- New "Status" sidebar card with colored badge and pipeline action buttons
- Buttons conditionally rendered per current status (only valid forward transitions shown)
- "Decline" always available on non-terminal states
- Optional "Add reason" textarea for decline (toggleable)
- Confirmation dialog before each action
- Loading/disabled state during requests

**Frontend — `applications/index.tsx`:**
- New "Status" column with colored badge per application
- Status filter dropdown (`?status=` query param)
- Controller reads filter and scopes query by caregiver status

**Files created/modified:**

| File | Action |
|------|--------|
| `app/Enums/CaregiverStatus.php` | Added UnderReview, InterviewScheduled, BackgroundCheck + terminal() |
| `app/Http/Controllers/ApplicationController.php` | Added 5 action methods + status filter in index() |
| `app/Http/Requests/ApplicationActionRequest.php` | Created |
| `app/Mail/ApplicantDeclinedMail.php` | Created |
| `resources/views/emails/application-declined.blade.php` | Created |
| `routes/web.php` | Added 5 admin routes |
| `resources/js/pages/applications/show.tsx` | Added pipeline action card + status badge |
| `resources/js/pages/applications/index.tsx` | Added status column + filter |
| `tests/Unit/Models/CaregiverStatusTest.php` | Updated for new cases + terminal() test |
| `tests/Feature/ApplicationLifecycleTest.php` | Created (15 tests) |

**Tests:** 15 tests covering all forward transitions, invalid transition rejections, decline from multiple statuses, decline email with note, terminal status rejection, admin authorization, and status filter.

**Wireframe:** Implied by interview eval "Save & advance to background check" button. No dedicated screen.

#### Refactor: Drive status badge metadata from backend enum

Removed duplicated status data (badge colors, labels, terminal list) from frontend components by sharing `CaregiverStatus::toArray()` globally via Inertia.

**Changes:**
- `app/Enums/CaregiverStatus.php` — Added `toArray()` returning all 12 cases with `value`, `label`, `color`, `is_terminal`
- `app/Http/Middleware/HandleInertiaRequests.php` — Shared `caregiverStatuses` globally
- `resources/js/types/global.d.ts` — Added `CaregiverStatusOption` type
- `resources/js/pages/applications/index.tsx` — Removed hardcoded `statusBadgeColors` and `statusOptions`; filter dropdown and badge colors now driven from shared props with inline styles
- `resources/js/pages/applications/show.tsx` — Removed hardcoded `TERMINAL_STATUSES` and `statusBadgeColors`; `getActions()` receives shared array and checks `is_terminal` instead; badges use inline styles from shared color
- `tests/Unit/Models/CaregiverStatusTest.php` — Added test for `toArray()` structure (all 12 entries, terminal subset verified)

**Impact:** Single source of truth for all status metadata on both backend and frontend.

### High #3 — Onboarding checklist + Trustline/CPR tracking (§7, wireframe) — ✅ Completed (core pipeline)

**Pipeline fix:** `hire()` now transitions to `Hired/Onboarding` (not `Active`). Checklist gates `Hired/Onboarding → Active`.

**Implementation:**

**Enum:**
- ✅ `CaregiverStatus::HiredOnboarding = 'hired_onboarding'` with label "Hired / Onboarding", color `#0EA5E9`
- ✅ NOT added to `terminal()` (non-terminal — can still be declined)

**Controller:**
- ✅ `ApplicationController@hire()` transitions to `HiredOnboarding` instead of `Active`, seeds 6 checklist items
- ✅ `ApplicationController@completeOnboarding()` validates all 6 items completed, transitions to `Active`
- ✅ `ApplicationController@toggleChecklistItem()` — toggles individual item's `completed_at`
- ✅ Routes: `POST /applications/{application}/complete-onboarding`, `POST /applications/{application}/checklist/{checklistItem}/toggle`

**Database:**
- ✅ `onboarding_checklist_items` table with `caregiver_id`, `item_key`, `label`, `description`, `completed_at`
- ✅ 6 items seeded on hire via `OnboardingChecklistItem::seedForCaregiver()`

**Frontend — Admin:**
- ✅ Checklist panel in sidebar (hired_onboarding status only): 6 toggleable items with checkmark/line-through
- ✅ "Complete Onboarding" action button
- ❌ Trustline countdown banner — deferred (separate item)
- ❌ Trustline reimbursement tracking table — deferred
- ❌ CPR expiration renewal reminders — deferred

**Email (deferred — separate items):**
- ❌ Onboarding nudges: 48h, 7d, 14d
- ❌ 30d auto-revert to Inactive
- ❌ Admin alert when onboarding stalls

**OnPay handoff:**
- ✅ Checklist item #1: "OnPay Setup" — admin ticks after sending invite

**Edge cases:**
- ❌ Rehire edge case — deferred (low volume, handle when needed)
- ❌ Certification expirations during onboarding: show warning — deferred

### Medium #2 — Needs Attention widget + Applications Ready queue (§15.1, wireframe) — ✅ Completed

#### Implementation

**Backend — `DashboardController@index`:**
- Added `needsAttention` array to `$adminData` with all 8 queue counts

| Queue | Logic | Status |
|-------|-------|--------|
| No-shows today | Placeholder (needs cancellation flow) | 0 |
| Applications ready | Caregiver `Applicant` + submitted app + (≥2 refs OR 14d) | Live |
| Onboarding stalled >7d | Hired/Onboarding caregivers, created 7d+ ago | Live |
| Trustline suspended | Placeholder (needs Trustline feature) | 0 |
| Compliance expired | Certifications past expiration (expires_required) | Live |
| Compliance expiring | Certifications expiring this month | Live |
| Inactive 45+ days | Inactive status + updated_at > 45d ago | Live |
| Stuck references | Pending refs created 7d+ ago | Live |

**Frontend — `dashboard/admin.tsx`:**
- New "Needs Attention" card placed between summary panels and two-column layout
- Each row: emoji icon + label + color-coded count badge (urgent/warning/default) + chevron
- Zero-count queues shown muted as placeholders for future features
- Total count pill in card header

**Files modified:** `app/Http/Controllers/DashboardController.php`, `resources/js/pages/dashboard/admin.tsx`

**Wireframe:** Full design in "Admin Dashboard" screen — 8-queue widget with count badges.

### Medium #3 — Self-service reference status for applicants — ✅ Completed

#### Implementation

**Token-based access:**
- Migration: added `status_token` (nullable unique string) to `caregivers` table
- `CaregiverApplicationController@submit()` generates a 32-char `status_token` on caregiver creation
- Public route: `GET /caregiver/apply/status/{token}` — no auth required, token-based
- Replace route: `POST /caregiver/apply/status/{token}/replace-reference/{referenceRequest}`

**Frontend — `public/caregiver-apply/application-status.tsx`:**
- Shows application status badge (color-coded from shared `caregiverStatuses`)
- Lists all references with completed/pending badges
- "Replace" button on pending references opens inline form (name, email, relationship)
- "Save & Re-send" deletes old response data, resets token, and re-queues `ReferenceRequestMail`

**Email integration:**
- `ApplicantConfirmationMail` now accepts `$statusToken` and renders a "Track Your Application Status" button in the email body

**Files created/modified:**
| File | Action |
|------|--------|
| `database/migrations/2026_05_24_112710_add_status_token_to_caregivers.php` | Created |
| `app/Models/Caregiver.php` | Added `status_token` to fillable |
| `app/Http/Controllers/CaregiverApplicationController.php` | Added `showStatus()`, `replaceReference()`, token generation in `submit()` |
| `app/Mail/ApplicantConfirmationMail.php` | Added `$statusToken` param, `$statusUrl` to view |
| `resources/views/emails/applicant-confirmation.blade.php` | Added status tracking button + link |
| `resources/js/pages/public/caregiver-apply/application-status.tsx` | Created |
| `routes/web.php` | Added 2 public routes |

**Wireframe:** Not shown — designed from spec requirements.

### Medium #4 — Interview evaluation form (§11.2–11.4, wireframe) — ✅ Completed

#### Implementation

| Component | Details |
|-----------|---------|
| **Migration** | `caregiver_interviews` table — caregiver_id, evaluator_id, application_id, scores (JSON), composite (0-36), notes (text), status (draft/declined/completed), evaluated_at |
| **Model** | `CaregiverInterview` with casts for scores/array and composite/int, relationships to caregiver/evaluator/application |
| **Controller** | `InterviewController` with `create()` (shows form) and `store()` (saves evaluation) |
| **Validation** | `StoreInterviewEvaluationRequest` — 9 dimension scores (1-4 each), required notes, status enforcement |
| **Lifecycle hook** | `store()` transitions caregiver status: `completed` → `BackgroundCheck`, `declined` → `Inactive` |
| **Frontend** | `admin/interviews/evaluate.tsx` — matches wireframe exactly: candidate header, 4-heart scale legend, 6 soft skills + 3 professionalism rows with heart selectors, auto-calculated composite (X/36 + %), required notes textarea, 3 action buttons (Save draft, Decline candidate, Save & advance) |
| **Application show** | "Evaluate Interview" link appears in sidebar when status is `interview_scheduled` |
| **Routes** | `GET/POST /applications/{application}/interview` (admin middleware) |

**Files created:**
- `database/migrations/2026_05_24_113004_create_caregiver_interviews_table.php`
- `app/Models/CaregiverInterview.php`
- `app/Http/Controllers/InterviewController.php`
- `app/Http/Requests/StoreInterviewEvaluationRequest.php`
- `resources/js/pages/admin/interviews/evaluate.tsx`

**Wireframe:** Full design in "Interview Evaluation" screen — candidate context header, legend, 9 dimension rows, composite bar, notes, 3 action buttons.

## Low (future phases — add after core flow is solid)

### Low #1 — Admin caregiver profile tabs (§16.2, wireframe) — ✅ Completed
See completed section above. 8 tabs with deferred props for reviews/job history.

### Low #2 — Self-service hold/resume + inactivity automation (§14, wireframe) — ✅ Completed
See completed section above. Self-service pause/resume + admin resume + 3-tier check-in + 180d archive.

### Low #3 — Caregiver cancellation flow (§9, wireframe) — ✅ Completed
See completed section above. Back-out/excuse/no-show/late-arrival actions + admin notification + 11 tests.

### Low #4 — Internal rating system (§11.5–11.8)

**Status:** Planned — approved design, awaiting implementation.

#### Current state

| What | Detail |
|------|--------|
| `caregivers.admin_rating` | `decimal(3,2)`, 112 imported caregivers at 5.00 |
| `caregiver_interviews.composite` | `tinyint(0-36)` — sum of 9 interview dimensions (1-4 each) |
| `caregiver_assignments.resolution` | 14,387 `completed`, 710 `cancelled_by_sitterwise` — no back_out/no_show data yet |

#### Planned implementation

**One new table** — no redundant events/audit table. `caregiver_assignments` already stores the source data for reliability calculation.

**Migration: `create_caregiver_internal_ratings_table`**

One row per caregiver (unique on `caregiver_id`):

| Column | Type | Notes |
|--------|------|-------|
| `id` | `bigint unsigned` PK | |
| `caregiver_id` | `bigint unsigned` FK → caregivers | **unique** |
| `communication_score` | `decimal(3,2)` nullable | Manual admin rating 1-5 |
| `communication_notes` | `text` nullable | Admin notes on communication |
| `communication_updated_at` | `timestamp` nullable | Tracks last edit |
| `reliability_score` | `decimal(3,2)` nullable | **Cached** — auto-calculated from assignments |
| `reliability_override` | `decimal(3,2)` nullable | Admin override — when set, used instead of auto |
| `reliability_cached_at` | `timestamp` nullable | When reliability was last recalculated |
| `composite_score` | `decimal(3,2)` nullable | Weighted formula result |
| `created_at` / `updated_at` | timestamps | |

**Reliability formula:**
```php
$backs = $assignments->whereIn('resolution', ['backed_out', 'no_show'])->count();
$completed = $assignments->where('resolution', 'completed')->count();
$score = 5.0 - ($backs * 0.5) + (floor($completed / 10) * 0.1);
$reliability = round(max(0, min(5.0, $score)), 2);
```

- `cancelled_by_sitterwise` does NOT count as a penalty
- `reliability_override IS NOT NULL` → use override instead of auto

**Recalculation triggers:**
- Artisan command `app:recalculate-reliability {--caregiver=}`
- Hook in `AssignmentController` — after `backOut()`, `excuse()`, `logNoShow()`
- Scheduled daily via `routes/console.php`

**Composite formula (0–100):**

| Component | Source | Weight | Calculation |
|-----------|--------|--------|-------------|
| Interview | `caregiver_interviews.composite` (0-36) | 20% | `(composite / 36) * 20` |
| Communication | `communication_score` (1-5) | 30% | `(score / 5) * 30` |
| Reliability | `reliability_score` or override (0-5) | 50% | `(score / 5) * 50` |

If a component is null, its weight is excluded and remaining weights are re-proportioned.

**Seed existing data:**
```sql
INSERT INTO caregiver_internal_ratings (caregiver_id, communication_score, communication_updated_at)
SELECT id, admin_rating, updated_at FROM caregivers WHERE admin_rating IS NOT NULL;
```

The `admin_rating` column on `caregivers` stays for backward compatibility and will be removed once the frontend fully migrates.

**Wireframe:** Not shown — backend system feeding profile display.

### Low #5 — Client reviews & ratings (§8)
Post-booking review trigger (2h email, 48h SMS), 14-day link, detailed rating form, admin trend dashboard.

**Wireframe:** Not shown.

### Low #6 — Milestone view (§12, wireframe)
Caregiver-facing stats: total jobs, client rating, reliability % + peer comparison, job streak, Trustline reimbursement progress.

**What to do:**
- Stats grid matching wireframe: big jobs-completed number, rating with stars, reliability %, streak, Trustline progress bar
- Peer comparison (team average for reliability)
- Do not display raw back-out counts

**Wireframe:** Full design in "Milestone View" screen — greeting banner + 5 stat cards.

### Low #7 — Job engagement metrics (§10), S2Verify (§13)
Admin metrics dashboard (acceptance rate, response time, etc.), background check integration with S2Verify webhook.

**Wireframe:** Not shown.
