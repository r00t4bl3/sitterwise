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

## High (foundational — fix existing gaps, complete Phase 1-2 core)

### 3. Stalled application nudges (§4.1) — ✅ Completed

#### Implementation

**Tracking:**
- `IncompleteApplication` model (`app/Models/IncompleteApplication.php`) with scopes: `needsNudge` (48h inactive + not nudged recently), `stale` (14d+), `expired` (90d+)
- Migration: `database/migrations/2026_05_24_095326_create_incomplete_applications_table.php`
- `CaregiverApplicationController@saveProgress()` creates/updates tracking on each step save
- `CaregiverApplicationController@submit()` deletes tracking record on successful submission

**Commands:**
- `app:nudge-incomplete-applications` — sends `ResumeApplicationMail` at 48h (nudge_count 0→1), sends `FinalReminderMail` at 7d (nudge_count 1→2)
- `app:archive-stalled-applications` — sets `archived_at` at 14d, hard-deletes at 90d

**Mailables + Templates:**
- `app/Mail/ResumeApplicationMail.php` → `resources/views/emails/resume-application.blade.php`
- `app/Mail/FinalReminderMail.php` → `resources/views/emails/final-reminder.blade.php`
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
| `app/Mail/ResumeApplicationMail.php` | Implemented |
| `app/Mail/FinalReminderMail.php` | Implemented |
| `resources/views/emails/resume-application.blade.php` | Created |
| `resources/views/emails/final-reminder.blade.php` | Created |
| `routes/console.php` | Added scheduling |
| `tests/Feature/CaregiverIncompleteApplicationTest.php` | Created (25 tests) |

**Impact:** New feature — directly affects lead conversion by recovering abandoned applications.

**Wireframe:** Not shown — backend workflow, no frontend screen.

## Medium (complete the pipeline — reference lifecycle, admin workflow)

### 4. Reference nudges (§5.5)
Reminders boost completion rates. Currently no proactive follow-up.

**What to do:**
- Reference-side: Artisan command sends reminders at day 2 and day 5 after creation
- Applicant-side: Artisan command sends prompts at day 3, 7, and auto-moves to "Stalled — references incomplete" at day 14
- Existing `ReferenceRequest` already tracks `created_at` — no schema changes needed

**Wireframe:** Not shown — backend scheduled commands.

### 5. Application lifecycle workflow
Statuses exist as enums but admin cannot move caregivers through the pipeline.

**What to do:**
- Add approve/decline/schedule-interview actions to `ApplicationController`
- Status transitions: Applicant → Under Review → Interview Scheduled → Background Check → Hired
- Inertia page updates to `applications/show.tsx` (action buttons)
- Decline flow: notification to applicant, status → Inactive

**Wireframe:** Implied by interview eval "Save & advance to background check" button. No dedicated screen.

### 6. Needs Attention widget + Applications Ready queue (§15.1, wireframe)
Single dashboard widget surfacing 8 queues: no-shows, apps ready, onboarding stalled, Trustline suspended, compliance expired/expiring, inactive 45d+, stuck references.

**What to do:**
- Backend: aggregate all 8 queue counts in `DashboardController@index`
- Frontend: single card component with rows (icon + label + count + arrow link)
- Each row links to a filtered list view (e.g., `/applications?filter=ready`)
- Applications Ready row: app complete + (≥2 references complete OR 14 days passed) + no auto-disqualifiers

**Wireframe:** Full design in "Admin Dashboard" screen — 8-queue widget with count badges.

### 7. Self-service reference status for applicants
Applicant portal to track reference responses and replace non-responders.

**What to do:**
- Applicant-facing API endpoint returning reference statuses
- Inertia page showing who responded, who hasn't
- Replace reference flow (delete old + re-send with new name/email)

**Wireframe:** Not shown.

### 8. Interview evaluation form (§11.2–11.4, wireframe)
4-heart scale × 9 dimensions (6 soft skills + 3 professionalism), 36-point composite, required notes.

**What to do:**
- Migration: `caregiver_interviews` table (caregiver_id, evaluator_id, scores JSON, notes, composite, evaluated_at)
- Backend: `InterviewController`, `StoreInterviewEvaluationRequest`
- Frontend: Inertia form matching wireframe exactly — heart selectors for each dimension, auto-calculated composite, required notes, Save draft / Decline / Save & advance buttons
- Dashboard integration: needs to hook into Application lifecycle (status → Interview Scheduled)

**Wireframe:** Full design in "Interview Evaluation" screen — candidate context header, legend, 9 dimension rows, composite bar, notes, 3 action buttons.

## Low (future phases — add after core flow is solid)

### 9. Admin caregiver profile tabs (§16.2, wireframe)
Tabbed profile layout: Application, References, Reviews, Internal Rating, Engagement (wireframe calls this Job History), Compliance, Notes.

**What to do:**
- Tab components matching wireframe's 7-tab layout
- Lazy-load tab content with deferred props for performance
- Job History tab shows assignment-based table (wireframe "Assignments View")

**Wireframe:** Full design in "Caregiver Assignments View" screen — profile header, 7 tabs, Job History table with resolution badges.

### 10. Onboarding checklist + Trustline/CPR tracking (§7, wireframe)
Post-hire onboarding flow with 6-item checklist, Trustline 7-day countdown, CPR expiration.

**What to do:**
- Checklist UI matching wireframe: 6 items (OnPay, BG check, CPR, Trustline, Dress code, Training quiz) with done/in-progress/pending states
- Trustline countdown banner: 7-day timer, $140 reimbursement after 10 jobs within 6 months
- Trustline reimbursement tracking (jobs toward 10, days toward 6 months, forfeiture logic)
- CPR expiration with renewal reminders (90/60/30/7 day cadence)
- Onboarding nudges (48h, 7d, 14d, 30d auto-revert to Inactive)

**Wireframe:** Full design in "Onboarding Checklist" screen — countdown banner + 6-item checklist with status indicators and meta pills.

### 11. Self-service hold/resume + inactivity automation (§14, wireframe)
Caregiver self-service pause with optional return date and reason. One-tap resume. Inactivity check-ins and archive.

**What to do:**
- Hold UI matching wireframe: date picker for return date, optional reason textarea, Cancel/Pause buttons
- Resume: single-tap on same screen, no admin required
- Artisan commands for 30/45/60d inactivity check-ins
- 180d archive after 14-day warning

**Wireframe:** Full design in "Pause Account" screen — hold card with form, callout explaining resume.

### 12. Caregiver cancellation flow (§9, wireframe)
Modal with back-out warning, required reason, admin actions (excuse, reassign).

**What to do:**
- Backend: `caregiver_assignments` table with resolution states (Completed, Backed Out, Backed Out (Excused), Reassigned, No-Show), late_arrival_flag
- Modal UI matching wireframe: job summary, warning text, required reason textarea, back/cancel buttons
- Admin actions: Mark Excused (requires note), Log No-Show, Log Late Arrival
- 3 late arrivals in 60 days auto-flags for admin review
- Notification to admin on back-out

**Wireframe:** Full design in "Cancellation Flow" screen — modal with job summary, warning, reason field, two actions. Also "Assignments View" table with resolution badges.

### 13. Internal rating system (§11.5–11.8)
Communication score, reliability score, composite.

**What to do:**
- Communication: rating + notes + last updated
- Reliability: auto-calculated (5.0 start, -0.5/back-out, +0.1/10 jobs, cap 5.0), admin override
- Composite: 20% interview + 30% communication + 50% reliability, configurable weights
- `caregiver_internal_ratings` table, `caregiver_rating_history` table

**Wireframe:** Not shown — backend system feeding profile display.

### 14. Client reviews & ratings (§8)
Post-booking review trigger (2h email, 48h SMS), 14-day link, detailed rating form, admin trend dashboard.

**Wireframe:** Not shown.

### 15. Milestone view (§12, wireframe)
Caregiver-facing stats: total jobs, client rating, reliability % + peer comparison, job streak, Trustline reimbursement progress.

**What to do:**
- Stats grid matching wireframe: big jobs-completed number, rating with stars, reliability %, streak, Trustline progress bar
- Peer comparison (team average for reliability)
- Do not display raw back-out counts

**Wireframe:** Full design in "Milestone View" screen — greeting banner + 5 stat cards.

### 16. Job engagement metrics (§10), S2Verify (§13)
Admin metrics dashboard (acceptance rate, response time, etc.), background check integration with S2Verify webhook.

**Wireframe:** Not shown.
