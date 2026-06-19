# Admin Caregiver Application — Plan

Allow admin to submit a caregiver application on behalf of a caregiver.

## What `submit()` currently does

Creates 10+ records, 2 PDFs, 5 queued emails:

| Records | Details |
|---------|---------|
| User | role=caregiver, random password |
| Caregiver | status=applicant, basic fields |
| CaregiverEducation | 1–2 records (college +/or high school) |
| CaregiverCertification pivot | CPR +/or Trustline |
| Caregiver metadata | screening answers → JSON column |
| SpecialtyType sync | age groups |
| Location sync | service areas |
| Attribute sync | petsitting, driving, non-smoker |
| CaregiverApplication | full data snapshot as JSON |
| CaregiverAgreement × 2 | verification + agreement PDFs |
| ReferenceRequest × 4 | 3 references + 1 sponsor |

Emails: applicant confirmation, admin notification, 4 reference request invites.

## Three approaches

### A — Quick reuse of existing wizard (1–2 days)

Admin opens the same 8-step wizard with a pre-set email session, bypassing `VerifyEmail` middleware.

- **New:** Admin route that sets `verified_email` session + redirects to wizard
- **Modified:** `routes/web.php` — new admin route skipping `VerifyEmail` middleware
- **Modified:** `CaregiverApplicationController::submit()` — accept email from request params when admin-initiated
- **Modified:** `StoreCaregiverApplicationRequest` — skip signature-must-match-name check for admin
- **Pro:** Zero new UI, minimal code, full fidelity
- **Con:** Admin clicks through all 8 steps; wizard UX designed for applicants, not admins; file uploads still single-file inputs

### B — Minimal admin create (2–3 days) ★ Recommended

New admin page with a single form: required basics + optional expandable sections. Skips signatures, PDFs, and non-essential data.

**Files to create:**

| File | Purpose |
|------|---------|
| `app/Http/Controllers/AdminCaregiverApplicationController.php` | `store()` — streamlined create |
| `app/Http/Requests/AdminCaregiverApplicationRequest.php` | Lighter validation — email + name required, references optional |
| `resources/js/pages/admin/applications/create.tsx` | Single-page form |

**Files to modify:**

| File | Change |
|------|--------|
| `routes/web.php` | `POST /admin/applications` with `auth`, `verified`, `admin` middleware |

**Minimum required fields:**
- Personal: first_name, last_name, email, phone, DOB, address
- References: up to 3 names + emails (optional — skip reference creation if omitted)
- Photo upload (optional)

**Auto-generated:**
- Random password (emailed via `ApplicantConfirmationMail`)
- Status: `applicant`
- Signatures: auto-filled as "Admin-initiated"
- Agreements: skipped

**Skipped (optional, add later):**
- Education history
- Experience entries
- Screening questions
- Qualification flags
- Certifications (CPR/Trustline upload)
- PDF agreement generation

**Pro:** Admin-friendly, fast to fill out, covers the core need
**Con:** Doesn't capture rich screening data; not a complete application

### C — Full admin application form (4–5 days)

New admin page mirroring all 8 wizard sections as collapsible panels on one page. Handles files, signatures, everything.

- **New:** Full multi-section admin page under `admin/applications/create.tsx`
- **New:** Admin-specific form request reusing most of the existing validation
- **Modified:** `submit()` or new method — skips session checks when admin-initiated
- **Pro:** Full fidelity, admin can submit a complete application
- **Con:** Most effort, most UI to build, many of these fields are rarely filled by an admin

## Recommendation: Approach B

Best effort-to-value ratio. Create the User + Caregiver + Application + send reference requests. The screening/qualification data can be handled separately or added as optional sections later.
