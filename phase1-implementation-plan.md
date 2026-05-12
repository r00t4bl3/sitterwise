# Phase 1 Implementation Plan: Caregiver Application System

**Status**: Draft - Awaiting Review  
**Last Updated**: April 30, 2026  
**Scope**: Public caregiver application wizard with email verification, 8-step form, draft support, and PDF generation

---

## Table of Contents
1. [Overview](#overview)
2. [Pre-Wizard: Email Verification](#pre-wizard-email-verification)
3. [8-Step Wizard Specification](#8-step-wizard-specification)
4. [Database Changes](#database-changes)
5. [Models & Relationships](#models--relationships)
6. [Routes & Controllers](#routes--controllers)
7. [Frontend Implementation](#frontend-implementation)
8. [PDF Generation](#pdf-generation)
9. [Testing Strategy](#testing-strategy)
10. [Implementation Order](#implementation-order)
11. [Open Questions](#open-questions)

---

## Overview

### Goals
- Build a public (no authentication required) caregiver application system
- Implement email verification before wizard access
- Create 8-step application wizard with per-step draft saving
- Generate generic agreement PDFs on submission
- Store application data as JSON snapshot
- Reuse existing `Caregiver` model, `caregiver_statuses`, and admin views

### Out of Scope (Phases 2-5)
- Reference system (tokenized links, email sending, reminders)
- Client reviews system
- Internal rating breakdown (interview/communication/reliability)
- Background check integration (S2Verify)
- Reference form processing (Screen B in wireframe)
- Admin References/Reviews/Internal Rating tabs

---

## Pre-Wizard: Email Verification

Before accessing the 8-step wizard, applicants must verify their email address.

### Flow
1. **Route**: `/caregiver/apply/verify-email` (GET)
   - Simple form: email input field
   - Check `users` table for email uniqueness
   - Reject if email already exists in `users` table

2. **Send OTP**: `/caregiver/apply/send-otp` (POST)
   - Generate 6-digit OTP
   - Store OTP + expiry (10 minutes) in cache/session
   - Send OTP via Laravel's mail driver (use existing mail config)
   - Email template: "Your Sitterwise verification code is: {OTP}"

3. **Verify OTP**: `/caregiver/apply/verify-otp` (POST)
   - Validate OTP against stored value
   - On success: store verified email + timestamp in session
   - Redirect to wizard at `/caregiver/apply`

### Technical Notes
- Use Laravel's `cache()` for OTP storage (key: `otp_{email}`, ttl: 600 seconds)
- Session key: `verified_email` + `verified_at` (valid for 30 minutes)
- Reuse existing `config/mail.php` configuration
- No authentication created at this stage

---

## 8-Step Wizard Specification

Based on wireframe + spec document. All steps save draft to `sessionStorage`.

### Step 1: Sponsor & Personal Information
**Wireframe Reference**: Lines 761-801

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| **Sponsor Section** | | | Sponsor also serves as reference |
| First Name | text | Yes | |
| Last Name | text | Yes | |
| Email | email | Yes | Cannot match applicant email |
| Phone | tel | No | |
| Relationship | text | No | How sponsor knows applicant |
| **Personal Section** | | | |
| First Name | text | Yes | Applicant's name |
| Last Name | text | Yes | |
| Address | text | Yes | Stored as structured data (use geocoding or split into street/city/state/zip) |
| Phone | tel | Yes | |
| Email | email | Yes | Pre-filled from verification, read-only |
| Date of Birth | date | Yes | Must be 18+ for background check |
| Profile Photo | file | No | Image upload, headshot for admin review |

**Validation Rules**:
- Email unique in `users` table
- DOB validates to 18+ years
- Phone format validation
- Image: max 5MB, jpeg/png/webp

---

### Step 2: Position, Availability & Education
**Wireframe Reference**: Lines 804-850

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| **Position** | | | Check all that apply |
| On-Call Babysitting | checkbox | Yes* | At least one position required |
| On-Call Petsitting | checkbox | Yes* | |
| Group Events | checkbox | Yes* | |
| **Availability** | | | |
| Weekday Mornings | checkbox | No | |
| Weekday Afternoons | checkbox | No | |
| Weekday Evenings | checkbox | No | |
| Weekends | checkbox | No | |
| Overnights | checkbox | No | |
| Availability Notes | textarea | No | Scheduling nuances |
| **Education** | | | |
| Highest Level | select | Yes | High School, Associate, Bachelor's, Master's, PhD |
| College/Institution | text | No | Conditional on degree level |
| Graduation Year | text | No | |
| Degree/Major | text | No | Shown for Associate+ |

**Validation Rules**:
- At least one position selected
- Structured fields stored as JSON (availability JSON, positions JSON)

---

### Step 3: Work Experience
**Wireframe Reference**: Placeholder (spec lines 100-111)

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| **Experience Entry** (repeatable, 1+) | | | |
| Start Month/Year | month | Yes | |
| End Month/Year | month | Yes | Can be "Present" |
| Role/Title | text | Yes | e.g., "Nanny", "After-School Care" |
| Organization | text | Yes | Employer name |
| Description | textarea | No | Responsibilities |
| Ages Served | checkbox-group | No | Babies, Toddlers, Preschool, School Age (JSON) |

**Validation Rules**:
- At least one experience entry
- End date must be after start date (or "Present")
- Ages served stored as JSON array

---

### Step 4: Additional Experience & Certifications
**Wireframe Reference**: Placeholder

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| **Certifications** | | | Select from existing `certification_types` |
| Certification | multi-select | No | CPR, First Aid, Lifeguard, etc. |
| File Upload | file | No | Upload cert files (PDF/image) |
| **Special Skills** | | | |
| Special Needs Care | checkbox | No | |
| Work-From-Home Care | checkbox | No | |
| Swimming Supervision | checkbox | No | |
| Driving (Valid License) | checkbox | No | |
| **Other Qualifications** | textarea | No | Free text for additional info |

**Validation Rules**:
- File uploads: max 10MB, pdf/jpeg/png
- Skills stored as JSON array

---

### Step 5: References (3 Additional)
**Wireframe Reference**: Placeholder (spec lines 125-165 for structure)

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| **Reference Entry** (repeatable, 3+) | | | Sponsor from Step 1 counts as 4th reference |
| Full Name | text | Yes | |
| Email | email | Yes | Cannot match applicant or sponsor email |
| Phone | tel | No | |
| Relationship | text | Yes | Friend, Former Employer, Co-worker, etc. |
| Years Known | select | Yes | <1, 1-3, 3-5, 5-10, 10+ |

**Validation Rules**:
- No duplicate reference emails within application
- Reference emails cannot match applicant's verified email
- Reference emails cannot match sponsor email (from Step 1)
- At least 3 references (4 total including sponsor)

---

### Step 6: Location Preferences & Age Groups
**Wireframe Reference**: Lines 853-879

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| **Location Preferences** | | | |
| North County | checkbox | No | Rancho Santa Fe, Del Mar, Carlsbad, etc. |
| South/East County | checkbox | No | Coronado, Downtown, La Jolla, etc. |
| Flexible (in a pinch) | checkbox | No | Willing to work other areas if needed |
| **Age Groups** (self-attestation) | | | Checking means agreement with statement |
| Babies (0-1) | checkbox | No | Statement about diaper changing, bottles, safe sleep |
| Toddlers (1-3) | checkbox | No | Statement about patience, meltdowns, safety |
| Preschool (3-5) | checkbox | No | Statement about playing, teaching, potty training |
| School Age (5-12) | checkbox | No | Statement about activities, conversation, engagement |

**Validation Rules**:
- Age group selections stored as JSON with attested statements
- Location preferences stored as JSON array

---

### Step 7: Review All Data
**Wireframe Reference**: Placeholder

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| **Review Sections** | | | Display all previous step data |
| Personal Info | readonly | - | Expandable/collapsible sections |
| Position & Availability | readonly | - | |
| Experience | readonly | - | |
| Certifications & Skills | readonly | - | |
| References | readonly | - | Show all 4 references |
| Location & Age Groups | readonly | - | |
| **Edit Option** | | | Link back to each step |
| **Terms Agreement** | checkbox | Yes | "I certify all information is true and complete" |

**Validation Rules**:
- Terms checkbox must be checked to proceed to Step 8

---

### Step 8: Agreements & Submit
**Wireframe Reference**: Lines 882-909

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| **Caregiver Statement of Verification** | | | |
| Full Text | readonly | - | Legal text (penalty of perjury, investigation authorization) |
| Typed Signature | text | Yes | Constitutes electronic signature |
| Today's Date | text | - | Auto-filled, non-editable |
| Agreement Checkbox | checkbox | Yes | "Typing name constitutes signature" |
| **Caregiver Statement of Agreement** | | | |
| Full Text | readonly | - | Independent contractor agreement, non-compete clause |
| Typed Signature | text | Yes | |
| Today's Date | text | - | Auto-filled |
| Agreement Checkbox | checkbox | Yes | |
| **Submit Button** | button | - | Final submission |

**On Submit**:
1. Validate all steps complete
2. Create `User` (role = `caregiver`, email from verified session)
3. Create `Caregiver` (status = `applicant`, link to user)
4. Store full wizard data as JSON in `caregiver_applications`
5. Generate 2 generic PDFs → `caregiver_agreements`
6. Clear session data
7. Redirect to thank-you page

---

## Database Changes

### New Migrations (Phase 1 Only)

#### 1. `create_caregiver_applications_table`
```php
Schema::create('caregiver_applications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('caregiver_id')->nullable()->constrained()->nullOnDelete();
    $table->json('data'); // Full wizard snapshot
    $table->timestamp('submitted_at')->nullable();
    $table->timestamps();
});
```

#### 2. `create_caregiver_agreements_table`
```php
Schema::create('caregiver_agreements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('caregiver_id')->constrained()->cascadeOnDelete();
    $table->string('type'); // 'verification' or 'agreement'
    $table->string('pdf_path');
    $table->timestamp('signed_at')->nullable();
    $table->timestamps();
});
```

#### 3. Verify `caregiver_statuses` seeder
Ensure `applicant` status exists:
```php
DB::table('caregiver_statuses')->insert([
    'name' => 'applicant',
    'color' => '#F48A91',
    'is_active' => true,
    'sort_order' => 1,
]);
```

### No Changes to Existing Tables
- Email remains on `users` table (no duplication on `caregivers`)
- Existing `Caregiver` model unchanged
- No modifications to `caregiver_references` (Phase 2 will use new reference system)

---

## Models & Relationships

### New Models

#### `CaregiverApplication.php`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverApplication extends Model
{
    protected $casts = [
        'data' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }
}
```

#### `CaregiverAgreement.php`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverAgreement extends Model
{
    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }
}
```

### Updated Models

#### `Caregiver.php` (add relationships)
```php
public function applications(): HasMany
{
    return $this->hasMany(CaregiverApplication::class);
}

public function agreements(): HasMany
{
    return $this->hasMany(CaregiverAgreement::class);
}
```

---

## Routes & Controllers

### Public Routes (No Auth Middleware)

```php
// Email Verification
Route::get('/caregiver/apply/verify-email', [CaregiverApplicationController::class, 'showVerifyEmail'])->name('caregiver.apply.verify');
Route::post('/caregiver/apply/send-otp', [CaregiverApplicationController::class, 'sendOtp'])->name('caregiver.apply.send-otp');
Route::post('/caregiver/apply/verify-otp', [CaregiverApplicationController::class, 'verifyOtp'])->name('caregiver.apply.verify-otp');

// Application Wizard
Route::middleware('verified-email')->group(function () {
    Route::get('/caregiver/apply', [CaregiverApplicationController::class, 'showWizard'])->name('caregiver.apply');
    Route::post('/caregiver/apply/draft', [CaregiverApplicationController::class, 'saveDraft'])->name('caregiver.apply.draft');
    Route::post('/caregiver/apply/submit', [CaregiverApplicationController::class, 'submit'])->name('caregiver.apply.submit');
});

// Thank You Page
Route::get('/caregiver/apply/thank-you', [CaregiverApplicationController::class, 'thankYou'])->name('caregiver.apply.thank-you');
```

### Custom Middleware: `VerifyEmail`
Checks session for `verified_email` + `verified_at` (valid within 30 minutes).

### `CaregiverApplicationController.php`

#### Methods
| Method | Purpose |
|--------|---------|
| `showVerifyEmail()` | Display email verification form |
| `sendOtp()` | Generate & send 6-digit OTP |
| `verifyOtp()` | Validate OTP, store in session |
| `showWizard()` | Display 8-step wizard (checks session) |
| `saveDraft()` | Save current step to `sessionStorage` (frontend) |
| `submit()` | Process full application |
| `thankYou()` | Confirmation page |

#### `submit()` Logic Flow
1. Validate all wizard steps complete (use `StoreCaregiverApplicationRequest`)
2. Check email uniqueness in `users` table
3. Create `User`:
   - `email` = verified email from session
   - `role` = `caregiver`
   - `password` = random (force password reset on first login)
4. Create `Caregiver`:
   - `user_id` = new user ID
   - `status_id` = applicant status
   - `first_name`, `last_name`, `phone`, `date_of_birth`, `address` fields from wizard
5. Store `CaregiverApplication`:
   - `caregiver_id` = new caregiver ID
   - `data` = full JSON snapshot of all wizard steps
   - `submitted_at` = now()
6. Generate PDFs:
   - Verification form (generic text)
   - Service agreement (generic text)
   - Store paths in `caregiver_agreements`
7. Clear session verification data
8. Redirect to thank-you page

---

## Frontend Implementation

### Page Structure
```
resources/js/pages/public/caregiver-apply/
├── verify-email.tsx       # Email + OTP verification
├── wizard.tsx             # Main 8-step wizard
├── thank-you.tsx          # Confirmation page
└── components/
    ├── step-1-personal.tsx
    ├── step-2-position.tsx
    ├── step-3-experience.tsx
    ├── step-4-certifications.tsx
    ├── step-5-references.tsx
    ├── step-6-location.tsx
    ├── step-7-review.tsx
    ├── step-8-agreements.tsx
    ├── step-pills.tsx      # Step navigation pills
    └── progress-bar.tsx    # Progress indicator
```

### Key Implementation Details

#### Inertia + React Patterns
- Use `useForm` from `@inertiajs/react` for form state management
- Reuse existing UI components from `resources/js/components/ui/`:
  - `Button.tsx`
  - `Input.tsx`
  - `Checkbox.tsx`
  - `Select.tsx`
  - `Textarea.tsx`

#### Draft Saving
- Use `sessionStorage` to persist step data between page refreshes
- Save on each step transition (not auto-save)
- Key: `caregiver_application_draft`
- Structure: `{ step: 1, data: { ... } }`

#### Tailwind Conversion
Convert wireframe CSS to Tailwind classes:
- Wireframe coral `#F48A91` → `text-coral` / `bg-coral` (define in `tailwind.config.js`)
- Wireframe navy `#1B3A5C` → existing navy classes
- Use existing design system where possible

#### Step Navigation
- Pills component shows all 8 steps
- Active step highlighted
- Completed steps marked with checkmark
- Allow clicking back to previous steps
- Prevent skipping ahead (must complete sequentially)

---

## PDF Generation

### Package
Install `barryvdh/laravel-dompdf`:
```bash
composer require barryvdh/laravel-dompdf
```

### Generic PDFs (Phase 1)
Generate two PDFs with placeholder text:

1. **Caregiver Statement of Verification**
   - Generic legal text (can be refined in Phase 2)
   - Include applicant name, date, signature from Step 8

2. **Caregiver Statement of Agreement**
   - Generic independent contractor agreement text
   - Include applicant name, date, signature from Step 8

### Storage
- Store in `storage/app/agreements/{caregiver_id}/`
- Link to `caregiver_agreements.pdf_path`
- Accessible by admin only (Phase 2 will add admin download)

---

## Testing Strategy

### Feature Tests: `tests/Feature/CaregiverApplicationTest.php`

#### Test Cases
| Test | Description |
|------|-------------|
| `test_email_verification_page_loads` | Verify public access to verify-email page |
| `test_otp_sent_for_new_email` | OTP sent when email not in users table |
| `test_otp_rejected_for_existing_email` | Error when email already registered |
| `test_otp_verification_success` | Valid OTP stores email in session |
| `test_otp_verification_failure` | Invalid OTP shows error |
| `test_wizard_requires_verified_email` | Redirect to verify if no session |
| `test_wizard_page_loads` | Authenticated wizard access |
| `test_draft_saved_to_session` | Draft endpoint accepts data |
| `test_submit_creates_user` | New user created with caregiver role |
| `test_submit_creates_caregiver` | Caregiver record created with applicant status |
| `test_submit_stores_application_snapshot` | JSON data stored in caregiver_applications |
| `test_submit_generates_pdfs` | Two agreements created in caregiver_agreements |
| `test_reference_email_validation` | Reject duplicate/self-reference emails |
| `test_thank_you_page_displays` | Confirmation page after submit |

### Pest Syntax
```php
it('sends OTP for new email', function () {
    Notification::fake();
    
    $response = post(route('caregiver.apply.send-otp'), [
        'email' => 'new-applicant@example.com',
    ]);
    
    $response->assertSessionHas('otp_new-applicant@example.com');
    Notification::assertSentToTimes(NewApplicant::class, 1);
});

it('rejects existing email', function () {
    User::factory()->create(['email' => 'existing@example.com']);
    
    $response = post(route('caregiver.apply.send-otp'), [
        'email' => 'existing@example.com',
    ]);
    
    $response->assertInvalid(['email' => 'This email is already registered.']);
});
```

### Factories
Reuse existing:
- `UserFactory` (override role to `caregiver`)
- `CaregiverFactory`
- Create new `CaregiverApplicationFactory` if needed

---

## Implementation Order

### Step 1: Setup (30 mins)
1. Create migrations for `caregiver_applications` and `caregiver_agreements`
2. Create `CaregiverApplication` and `CaregiverAgreement` models
3. Add relationships to `Caregiver` model
4. Run `php artisan migrate`

### Step 2: Email Verification (1 hour)
1. Create `VerifyEmail` middleware
2. Build `CaregiverApplicationController` with verify methods
3. Create `verify-email.tsx` page (Inertia + React)
4. Implement OTP generation and sending
5. Test email verification flow

### Step 3: Wizard Frontend - Steps 1-2 (2 hours)
1. Create `wizard.tsx` with step pills and progress bar
2. Build `step-1-personal.tsx` (personal + sponsor info)
3. Build `step-2-position.tsx` (position, availability, education)
4. Implement `sessionStorage` draft saving
5. Style with Tailwind (convert from wireframe CSS)

### Step 4: Wizard Frontend - Steps 3-6 (2 hours)
1. Build `step-3-experience.tsx` (repeatable experience entries)
2. Build `step-4-certifications.tsx` (certifications, skills)
3. Build `step-5-references.tsx` (3 additional references)
4. Build `step-6-location.tsx` (location + age groups)

### Step 5: Wizard Frontend - Steps 7-8 (1.5 hours)
1. Build `step-7-review.tsx` (review all data)
2. Build `step-8-agreements.tsx` (signatures + submit)
3. Implement submit handler
4. Create `thank-you.tsx` page

### Step 6: Backend Submit Logic (2 hours)
1. Create `StoreCaregiverApplicationRequest` form request
2. Implement `submit()` method in controller
3. Handle user/caregiver creation
4. Store application snapshot
5. Install dompdf and implement PDF generation
6. Test full submission flow

### Step 7: Admin Integration (1 hour)
1. Extend `admin/caregivers/show.tsx` with Application tab
2. Display `caregiver_applications.data` JSON snapshot
3. Add link to download agreements (PDF paths)

### Step 8: Testing (2 hours)
1. Write Pest feature tests
2. Run `php artisan test --compact`
3. Fix any failures
4. Run `vendor/bin/pint --format agent` for code style

**Total Estimated Time**: ~12 hours

---

## Open Questions

1. **OTP Delivery Method**:
   - Use Laravel's built-in mail driver (as planned)?
   - Or SMS via existing `TwilioService`?
   - *Recommendation*: Email OTP (simpler, matches wireframe)

2. **Profile Photo Upload**:
   - Required or optional in Step 1?
   - *Current plan*: Optional (wireframe shows it as optional)

3. **Existing Email Handling**:
   - If email exists in `users` table, should we:
     - Show error with login link?
     - Auto-login if password exists?
   - *Current plan*: Show error with link to login page

4. **Password for New Users**:
   - Generate random password + force password reset email?
   - Or require password setup in wizard?
   - *Current plan*: Random password + password reset (simpler for Phase 1)

5. **Address Field**:
   - Store as single string (as in wireframe)?
   - Or split into street/city/state/zip (as in existing `Caregiver` model)?
   - *Current plan*: Split to match existing `Caregiver` fields (use geocoding or manual split)

6. **PDF Storage**:
   - Private (`storage/app/`) or public (`public/storage/`)?
   - *Current plan*: Private, accessible by admin only (Phase 2 will implement download)

7. **Wizard Resume**:
   - If session expires, can applicant resume?
   - Or start over with new verification?
   - *Current plan*: Start over (simpler for Phase 1)

---

## Appendix: Form Request Validation Rules

### `StoreCaregiverApplicationRequest.php`

```php
public function rules(): array
{
    return [
        // Step 1: Sponsor & Personal
        'sponsor.first_name' => 'required|string|max:255',
        'sponsor.last_name' => 'required|string|max:255',
        'sponsor.email' => 'required|email|not_in:{applicant_email}',
        'sponsor.phone' => 'nullable|string|max:20',
        'sponsor.relationship' => 'nullable|string|max:255',
        'personal.first_name' => 'required|string|max:255',
        'personal.last_name' => 'required|string|max:255',
        'personal.address' => 'required|string|max:500',
        'personal.phone' => 'required|string|max:20',
        'personal.dob' => 'required|date|before:-18 years',
        'personal.photo' => 'nullable|image|max:5120', // 5MB
        
        // Step 2: Position & Availability
        'position.babysitting' => 'boolean',
        'position.petsitting' => 'boolean',
        'position.group_events' => 'boolean',
        'availability.weekday_mornings' => 'boolean',
        'availability.weekday_afternoons' => 'boolean',
        'availability.weekday_evenings' => 'boolean',
        'availability.weekends' => 'boolean',
        'availability.overnights' => 'boolean',
        'availability.notes' => 'nullable|string|max:1000',
        'education.level' => 'required|in:high_school,associate,bachelor,master,phd',
        'education.college' => 'nullable|string|max:255',
        'education.graduation_year' => 'nullable|digits:4|integer|min:1980|max:2026',
        'education.degree' => 'nullable|string|max:255',
        
        // Step 3: Experience
        'experiences' => 'required|array|min:1',
        'experiences.*.start_month' => 'required|date_format:Y-m',
        'experiences.*.end_month' => 'nullable|date_format:Y-m|after_or_equal:experiences.*.start_month',
        'experiences.*.role' => 'required|string|max:255',
        'experiences.*.organization' => 'required|string|max:255',
        'experiences.*.description' => 'nullable|string|max:2000',
        'experiences.*.ages_served' => 'nullable|array',
        'experiences.*.ages_served.*' => 'in:babies,toddlers,preschool,school_age',
        
        // Step 4: Certifications & Skills
        'certifications' => 'nullable|array',
        'certifications.*' => 'exists:certification_types,id',
        'certification_files.*' => 'nullable|file|mimes:pdf,jpeg,png|max:10240', // 10MB
        'skills.special_needs' => 'boolean',
        'skills.work_from_home' => 'boolean',
        'skills.swimming' => 'boolean',
        'skills.driving' => 'boolean',
        'skills.other' => 'nullable|string|max:1000',
        
        // Step 5: References
        'references' => 'required|array|min:3',
        'references.*.name' => 'required|string|max:255',
        'references.*.email' => 'required|email|distinct',
        'references.*.phone' => 'nullable|string|max:20',
        'references.*.relationship' => 'required|string|max:255',
        'references.*.years_known' => 'required|in:<1,1-3,3-5,5-10,10+',
        
        // Step 6: Location & Age Groups
        'location.north_county' => 'boolean',
        'location.south_east_county' => 'boolean',
        'location.flexible' => 'boolean',
        'age_groups.babies' => 'boolean',
        'age_groups.toddlers' => 'boolean',
        'age_groups.preschool' => 'boolean',
        'age_groups.school_age' => 'boolean',
        
        // Step 7: Review (terms)
        'terms.agree' => 'required|accepted',
        
        // Step 8: Agreements
        'verification.signature' => 'required|string|max:255',
        'verification.agree' => 'required|accepted',
        'agreement.signature' => 'required|string|max:255',
        'agreement.agree' => 'required|accepted',
    ];
}

public function messages(): array
{
    return [
        'sponsor.email.not_in' => 'Sponsor email cannot match your email address.',
        'personal.dob.before' => 'You must be at least 18 years old to apply.',
        'references.min' => 'Please provide at least 3 references (plus your sponsor).',
        'references.*.email.distinct' => 'Reference emails must be unique.',
    ];
}
```

---

**End of Plan**

Review and update this document as needed. Once finalized, implementation can begin following the order outlined in [Implementation Order](#implementation-order).
