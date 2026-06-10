# Frontend Browser Test Coverage Plan

**Tool**: Pest 4 Browser Plugin + Playwright (headless Chromium)
**Language**: PHP (Pest) with real browser interactions via Playwright
**Location**: `tests/Browser/`
**Status**: In Progress — 55 of ~280 tests complete (20%)

---

## Scope overview

| Metric | Count |
|--------|-------|
| Unique page components | 73 |
| Distinct forms | 38 |
| Testable interactions (page loads + form submissions + modals + navigation) | ~200+ |
| Estimated total tests | ~250–325 |
| Estimated dev effort | ~20–24 days |

---

## Progress Overview

| Tier | Plan | Done | % | Priority |
|------|------|------|---|----------|
| **1. Core Auth** | ~30 | **17** | 57% | Critical |
| **2. Guest Booking Flow** | ~35 | **16** | 46% | Critical |
| **3. Authenticated CRUD** | ~70 | **12** | 17% | High |
| **4. Admin Back Office** | ~70 | **6** | 9% | Medium |
| **5. Caregiver Application** | ~25 | **0** | 0% | High |
| **6. Misc & Reference** | ~20 | **0** | 0% | Low |
| **7. Edge Cases & Smoke** | ~30 | **4** | 13% | High |
| **Total** | **~280** | **55** | **20%** | |

---

## Tier 1: Core Authentication (≈2 days, ~30 tests) — 17 done

**Goal**: Every auth flow works end-to-end with real browser interaction — form fill, submit, redirect, session persistence, error states.

### Completed Tests

| # | Test | File |
|---|------|------|
| 1.1 | Guest visits login page | `Auth/LoginTest.php` |
| 1.2 | Guest logs in with valid credentials | `Auth/LoginTest.php` |
| 1.3 | Guest logs in with incorrect password | `Auth/LoginTest.php` |
| 1.4 | Guest can navigate to forgot password | `Auth/LoginTest.php` |
| 1.5 | Guest can navigate to register | `Auth/LoginTest.php` |
| 1.6 | Registration page can be viewed | `Auth/RegisterTest.php` |
| 1.7 | New user can register with valid data | `Auth/RegisterTest.php` |
| 1.8 | Forgot password page can be viewed | `Auth/PasswordResetTest.php` |
| 1.9 | Forgot password sends reset link for valid email | `Auth/PasswordResetTest.php` |
| 1.10 | Forgot password shows error for non-existent email | `Auth/PasswordResetTest.php` |
| 1.11 | User can navigate back to login from forgot password | `Auth/PasswordResetTest.php` |
| 1.12 | Confirm password page can be viewed | `Auth/ConfirmPasswordTest.php` |
| 1.13 | Two-factor challenge page renders after login with 2FA enabled | `Auth/TwoFactorTest.php` |
| 1.14 | Two-factor challenge can toggle to recovery code mode | `Auth/TwoFactorTest.php` |
| 1.15 | Unverified user is prompted to verify email | `Auth/EmailVerificationTest.php` |
| 1.16 | Verified user can access dashboard | `Auth/EmailVerificationTest.php` |
| 1.17 | Login + forgot password pages load without JS errors (smoke) | `Auth/SmokeTest.php` |

### Planned Tests (remaining gaps)

| # | Test | Key assertions |
|---|------|----------------|
| 1.18 | Guest logs in with unverified email | Assert stays on login or shows email verification prompt |
| 1.19 | Guest registers with missing required fields | Assert validation errors shown per field |
| 1.20 | Guest registers with mismatched passwords | Assert password confirmation error |
| 1.21 | Guest registers with duplicate email | Assert unique validation error |
| 1.22 | Guest submits forgot-password with unknown email | No error shown (security best practice) |
| 1.23 | Guest visits reset-password page with valid token | Assert form rendered |
| 1.24 | Guest resets password with valid token | Submit new password, assert redirected to login |
| 1.25 | Guest resets password with invalid/expired token | Assert error |
| 1.26 | User confirms password correctly | Redirected to intended page |
| 1.27 | User confirms password incorrectly | Assert validation error |
| 1.28 | User submits incorrect 2FA code | Assert error message |
| 1.29 | Authenticated user enables 2FA | Click enable, assert QR shown, confirm with OTP, assert recovery codes shown |
| 1.30 | Authenticated user disables 2FA | Submit disable, assert 2FA no longer required |
| 1.31 | User resends verification email | Click resend, assert success |
| 1.32 | Unverified user is redirected to verify page | Access dashboard, assert redirected to `/email/verify` |
| 1.33 | Authenticated user logs out | Click logout, assert redirected to `/login` |

---

## Tier 2: Guest Booking Flow (≈3 days, ~35 tests) — 16 done

**Goal**: Full end-to-end journey of a guest creating a booking through payment to confirmation. This is the highest business-value flow.

### Completed Tests

| # | Test | File |
|---|------|------|
| 2.1 | Page renders with all sections | `Guest/BookingCreateTest.php` |
| 2.2 | Can fill about you section | `Guest/BookingCreateTest.php` |
| 2.3 | Can select service type and location type | `Guest/BookingCreateTest.php` |
| 2.4 | Can toggle hotel not listed | `Guest/BookingCreateTest.php` |
| 2.5 | Can add and remove children | `Guest/BookingCreateTest.php` |
| 2.6 | Can add and remove pets | `Guest/BookingCreateTest.php` |
| 2.7 | Shows validation errors on incomplete submit | `Guest/BookingCreateTest.php` |
| 2.8 | Server-side validation passes and redirects to payment | `Guest/BookingCreateTest.php` |
| 2.9 | Confirmation page shows booking details | `Guest/BookingConfirmationTest.php` |
| 2.10 | Confirmation page shows password setup link | `Guest/BookingConfirmationTest.php` |
| 2.11 | Payment page redirects to create without session data | `Guest/BookingPaymentTest.php` |
| 2.12 | Guest can view review page via signed url | `Guest/BookingReviewTest.php` |
| 2.13 | Guest can submit review with rating and comment | `Guest/BookingReviewTest.php` |
| 2.14 | Tip input shows stripe card input when filled | `Guest/BookingReviewTest.php` |
| 2.15 | Tip input hides stripe card input when cleared | `Guest/BookingReviewTest.php` |
| 2.16 | Submit with tip but no card shows error | `Guest/BookingReviewTest.php` |

### Planned Tests (remaining)

| # | Test | Key assertions |
|---|------|----------------|
| 2.17 | Guest searches and selects a hotel from autocomplete | Type in autocomplete, click suggestion, assert value set |
| 2.18 | Guest adds a single date block | Pick start/end DateTimePicker, assert block added |
| 2.19 | Guest adds multiple date blocks | Click "Add Dates", assert new row appears, no overlap validation |
| 2.20 | Guest removes a date block | Click remove, assert block removed |
| 2.21 | Guest fills address with Google Autocomplete | Type partial address, select suggestion, assert fields populated |
| 2.22 | Guest toggles sitter preferences checkboxes | Assert checkboxes toggle |
| 2.23 | Guest fills optional textareas | caregiver_notes, notes_to_sitterwise, emergency_instructions, special_needs_notes |
| 2.24 | Guest visits payment page | `assertPathIs()`, `assertSee('Payment')`, BookingProgress shows step 2 |
| 2.25 | Guest completes Stripe payment | Interact with Stripe EmbeddedCheckout, assert redirected to confirmation |
| 2.26 | Guest submits booking with same-day date | Assert same-day warning banner visible |
| 2.27 | Guest creates booking that overlaps dates | Assert overlap validation warning shown |
| 2.28 | Guest resumes partially filled booking via browser back | Form state restored (if applicable) |
| 2.29 | BookingProgress indicator shows correct step | assert step 1 active on `/book`, step 2 on payment, step 3 on confirmation |
| 2.30 | Date block enforces 4-hour minimum | Set start, assert end auto-adjusts to start + 4h |
| 2.31 | Guest enters invalid email format | Assert validation error |

> **Note**: Stripe EmbeddedCheckout tests require Stripe test mode keys and test card numbers. These tests should use the Stripe testing `Visa` card (`4242 4242 4242 4242`).

---

## Tier 3: Authenticated CRUD (≈5 days, ~70 tests) — 12 done

**Goal**: All role-specific dashboards, booking lists, settings, and profile management work correctly.

### Completed Tests

| # | Test | File |
|---|------|------|
| 3.1 | Bookings index page can be viewed | `Client/BookingsTest.php` |
| 3.2 | Bookings index page has create booking link | `Client/BookingsTest.php` |
| 3.3 | Client can view booking detail | `Client/BookingDetailTest.php` |
| 3.4 | Caregiver available bookings index can be viewed | `Caregiver/BookingsTest.php` |
| 3.5 | Jobs index page can be viewed | `Caregiver/JobsTest.php` |
| 3.6 | Profile settings page can be viewed | `Settings/ProfileTest.php` |
| 3.7 | User can update their name | `Settings/ProfileTest.php` |
| 3.8 | User can update their email | `Settings/ProfileTest.php` |
| 3.9 | Security settings page can be viewed | `Settings/SecurityTest.php` |
| 3.10 | User can update their password | `Settings/SecurityTest.php` |
| 3.11 | User sees error with wrong current password | `Settings/SecurityTest.php` |
| 3.12 | Appearance settings page can be viewed | `Settings/AppearanceTest.php` |

### Planned Tests (remaining)

#### Layout & Navigation

| # | Test | Key assertions |
|---|------|----------------|
| 3.13 | App sidebar renders with correct nav items per role | Client sees "My Bookings", caregiver sees "My Jobs", admin sees "Clients" etc. |
| 3.14 | Breadcrumbs render correctly on nested pages | `assertSee('Settings / Profile')` |
| 3.15 | Global search executes and shows results | Type query, assert suggestions appear |
| 3.16 | User menu dropdown opens | Click user avatar, assert menu items visible |
| 3.17 | Theme toggle (Appearance settings) | Switch to dark mode, assert `dark` class on `<html>` |

#### Dashboards (×4 roles)

| # | Test | Key assertions |
|---|------|----------------|
| 3.18 | Client dashboard loads | `assertSee('My Bookings')`, `assertSee('Upcoming')` |
| 3.19 | Caregiver dashboard loads | `assertSee('My Jobs')` |
| 3.20 | Admin dashboard loads | Stats cards visible |
| 3.21 | SuperAdmin dashboard loads | Admin-level stats visible |

#### Settings — remaining

| # | Test | Key assertions |
|---|------|----------------|
| 3.22 | Profile settings — email update triggers verification | change email, assert resend verification prompt |
| 3.23 | Profile settings — delete account | Open dialog, enter password, confirm, assert account deleted |
| 3.24 | Security settings — enable 2FA | Full flow: enable → show QR → confirm OTP → show recovery codes |
| 3.25 | Security settings — disable 2FA | Assert 2FA removed on next login |
| 3.26 | Appearance settings — switch theme | Click light/dark/system, assert applied |
| 3.27 | Caregiver pause account — set pause | Pick resume date, add reason, submit, assert paused status |
| 3.28 | Caregiver pause account — resume | Click resume, assert active status |

#### Client Bookings — remaining

| # | Test | Key assertions |
|---|------|----------------|
| 3.29 | Client creates booking (authenticated) | Same form as guest but pre-filled with user data |
| 3.30 | Client reviews past booking | Star rating + comment submit |

#### Caregiver Bookings / Jobs — remaining

| # | Test | Key assertions |
|---|------|----------------|
| 3.31 | Caregiver reserves a booking | Click "Accept", assert reserved status |
| 3.32 | Caregiver confirms reserved booking within timer | Click "Confirm", assert confirmed status |
| 3.33 | Caregiver lets reservation timer expire | Wait 60s, assert booking released |
| 3.34 | Caregiver releases a confirmed booking | Click "Release", assert released status |
| 3.35 | Caregiver views job detail | Booking details, checkout button visible |
| 3.36 | Caregiver checks out a job (submits hours) | Fill start/end datetime, reimbursement, bonus, submit |
| 3.37 | Caregiver cancels a job | Open cancel dialog, fill reason, submit, assert cancelled |
| 3.38 | Caregiver rates a completed job | Star rating + comment, submit |

#### Client Payments

| # | Test | Key assertions |
|---|------|----------------|
| 3.39 | Payments index loads with payment methods | cards displayed |
| 3.40 | Client adds payment method (Stripe) | Interact with Stripe card input, assert method added |
| 3.41 | Client sets default payment method | Click "Set Default", assert default badge |
| 3.42 | Client removes payment method | Confirm dialog, assert method removed |

#### Caregiver Payouts

| # | Test | Key assertions |
|---|------|----------------|
| 3.43 | Payouts page loads for caregiver | `assertSee('Payouts')` |
| 3.44 | Caregiver initiates Stripe Connect onboarding | Click connect, assert redirected to Stripe |

---

## Tier 4: Admin Back Office (≈5 days, ~70 tests) — 6 done

**Goal**: All admin CRUD pages, the complex booking sheet, caregiver/client management, and application workflow work correctly.

### Completed Tests

| # | Test | File |
|---|------|------|
| 4.1 | Admin client create page can be viewed | `Admin/ClientTest.php` |
| 4.2 | Admin can create a client | `Admin/ClientTest.php` |
| 4.3 | Admin can view client detail page | `Admin/ClientTest.php` |
| 4.4 | Admin caregiver create page can be viewed | `Admin/CaregiverTest.php` |
| 4.5 | Admin can create a caregiver | `Admin/CaregiverTest.php` |
| 4.6 | Admin can view caregiver detail page | `Admin/CaregiverTest.php` |

### Planned Tests (64 remaining)

#### Admin Bookings

| # | Test | Key assertions |
|---|------|----------------|
| 4.7 | Admin bookings index loads (table view) | Booking rows visible with filters |
| 4.8 | Admin bookings index loads (calendar view) | Toggle to calendar, assert calendar rendered |
| 4.9 | Admin switches between calendar/table view | assert persisted in localStorage |
| 4.10 | Admin searches bookings | Type in search input, assert debounced results |
| 4.11 | Admin opens booking sheet (slide-out panel) | Click "Create Booking", assert sheet slides in |
| 4.12 | Admin creates booking via booking sheet | Fill ~30 fields, submit, assert created |
| 4.13 | Admin duplicates booking | Click duplicate, assert form pre-filled |
| 4.14 | Admin edits booking | Modify fields, submit, assert updated |
| 4.15 | Admin deletes booking | Open delete dialog, confirm, assert removed |
| 4.16 | Admin splits booking group | Open split dialog, select bookings, confirm |
| 4.17 | Admin filters bookings by status/caregiver/date | assert URL query params + filtered results |

#### Admin Clients — remaining

| # | Test | Key assertions |
|---|------|----------------|
| 4.18 | Clients index loads | Table with search/filter |
| 4.19 | Admin searches clients | Debounced search, results update |
| 4.20 | Admin creates a client with missing required fields | Assert validation errors |
| 4.21 | Admin edits a client | Update fields, add child/pet/address, submit |
| 4.22 | Admin adds address with Google Autocomplete | Same as guest booking |
| 4.23 | Admin adds a child dynamically | Click "Add Child", fill details |
| 4.24 | Admin adds a pet dynamically | Click "Add Pet", fill details |
| 4.25 | Admin resets client password | Open dialog, enter new password, submit |
| 4.26 | Admin uploads client profile photo | Select file, upload, assert photo updated |
| 4.27 | Admin adds payment method for client | Stripe card input |
| 4.28 | Admin views client booking history | Click link, assert filtered booking table |

#### Admin Caregivers — remaining

| # | Test | Key assertions |
|---|------|----------------|
| 4.29 | Caregivers index loads | Table with search/filter/pagination |
| 4.30 | Admin searches caregivers | Debounced, results update |
| 4.31 | Admin edits a caregiver | Navigate collapsible sections, update attributes/specialties/locations/certifications |
| 4.32 | Admin opens a collapsible section | Click "Certifications" accordion, assert content visible |
| 4.33 | Admin adds a certification dynamically | Fill type + expiration + file, assert row added |
| 4.34 | Admin adds education entry dynamically | Fill school/degree/year |
| 4.35 | Admin toggles availability checkboxes | Toggle morning/afternoon/evening per weekday |
| 4.36 | Admin updates caregiver rating | Select rating, submit, assert updated |
| 4.37 | Admin resets caregiver password | Dialog, new password, submit |
| 4.38 | Admin uploads caregiver profile photo | File upload, assert updated |
| 4.39 | Admin views caregiver job history | Click link, assert job list filtered |
| 4.40 | Admin resumes paused caregiver | Click "Resume Caregiving", assert active |

#### Admin Applications

| # | Test | Key assertions |
|---|------|----------------|
| 4.41 | Applications index loads | Applicant list with status badges |
| 4.42 | Admin views application detail | Full application info visible |
| 4.43 | Admin toggles reference checklist item | Click, assert toggled |
| 4.44 | Admin resends reference email | Click, assert success |
| 4.45 | Admin schedules interview | Pick date/time, submit, assert scheduled |
| 4.46 | Admin submits interview evaluation | Fill heart ratings + notes, submit |
| 4.47 | Admin starts background check | Click, assert status updated |
| 4.48 | Admin approves application | Click, assert status changed to approved |
| 4.49 | Admin hires applicant | Click, assert caregiver created + onboarding started |
| 4.50 | Admin completes onboarding | Click, assert onboarding complete |
| 4.51 | Admin declines application | Open dialog, fill reason, submit, assert declined |

#### Admin Availabilities, Transactions, SuperAdmin

| # | Test | Key assertions |
|---|------|----------------|
| 4.52–4.54 | Availabilities CRUD | index, edit, delete |
| 4.55–4.56 | Transactions | index, filter |
| 4.57–4.64 | SuperAdmin CRUD | Certifications, Specialties, Locations, Attributes, Hotels, Pricing Rules, Quick Links, Broadcast SMS |

---

## Tier 5: Caregiver Application Wizard (≈3 days, ~23 tests) — 0 done

**Goal**: The multi-step caregiver application with OTP verification, 8 wizard steps (covering 14 sections), auto-save, and status tracking works end-to-end in the browser.

**Backend context**: Extensive Feature tests exist (1263 lines in `tests/Feature/CaregiverApplicationTest.php`). Browser tests focus on UI rendering, real navigation, dynamic interactions, and the complete E2E happy path.

**Files to create:**
- `tests/Browser/Caregiver/Apply/VerifyEmailTest.php`
- `tests/Browser/Caregiver/Apply/WizardTest.php`
- `tests/Browser/Caregiver/Apply/ThankYouTest.php`
- `tests/Browser/Caregiver/Apply/StatusTest.php`

### VerifyEmailTest (4 tests)

| # | Test | Key assertions | Notes |
|---|------|----------------|-------|
| 5.1 | Verify email page renders | `assertSee('Verify Email')`, no JS errors | |
| 5.2 | Enter email and send OTP | Fill email, click send, assert success message | OTP sent in non-prod |
| 5.3 | Verify with correct OTP (bypass `000000`) | Fill 6-digit OTP, submit, assert redirected to wizard | Use `000000` bypass |
| 5.4 | Submit incorrect OTP | Fill wrong OTP, assert error message | |

### WizardTest (15 tests)

| # | Test | Key assertions | Notes |
|---|------|----------------|-------|
| 5.5 | Wizard page renders all 8 step indicators | `assertSee('Step 1')`, step names visible, no JS errors | |
| 5.6 | Each step renders its form fields | Navigate all 8 steps, assert section headings/placeholders per step | |
| 5.7 | Navigate between steps (next/previous) | Click "Next" → step advances; click "Back" → step goes back | |
| 5.8 | Step 1 — fill sponsor + personal info | Fill first/last name, address, email, phone, DOB, photo upload | Photo upload via `setInputFiles()`; skip Google Places autocomplete — fill address fields directly |
| 5.9 | Step 2 — toggle positions, availability, education | Click babystitting/petsitting checkboxes, toggle availability timeslots, select education level from dropdown | |
| 5.10 | Step 3 — add/remove experience entries dynamically | Click "Add Experience", fill role/organization/dates/description/ages; click remove, assert row gone | Min 1 entry required, max 3 |
| 5.11 | Step 4 — toggle screening questions + select languages | Click radio buttons for smokes/alcohol/limitations etc.; select languages from multi-select | |
| 5.12 | Step 5 — add/remove reference entries dynamically | Click "Add Reference", fill first/last name, email, phone, relationship, years_known; remove one | Min 3 references required |
| 5.13 | Step 6 — toggle location regions + age groups | Click North County / South-East County / Flexible; click babies/toddlers/preschool/school_age | |
| 5.14 | Step 7 — toggle qualification checkboxes + fill bio | Click qualification checkboxes, fill bio textarea, things_i_bring, interests | |
| 5.15 | Step 8 — type signatures + check agreement boxes | Type full name in verification signature, click verification agree; type in agreement signature, click agreement agree | Signatures must match `personal.first_name + ' ' + personal.last_name` |
| 5.16 | Auto-save persists to sessionStorage | Fill step 1, navigate to step 2, reload page, assert step 1 data restored from sessionStorage | |
| 5.17 | Complete E2E happy path | Fill all 8 steps fully → click submit → assert redirected to `/caregiver/apply/thank-you` | Highest priority test |
| 5.18 | Client-side validation errors shown on incomplete submit | Navigate to step with incomplete required fields, click "Next", assert error messages | |
| 5.19 | "Save & Continue Later" saves progress | Fill partial data, click save button, assert progress persisted in `IncompleteApplication` table | |

### ThankYouTest (2 tests)

| # | Test | Key assertions | Notes |
|---|------|----------------|-------|
| 5.20 | Thank-you page renders | `assertSee('Application Submitted')`, confirmation message visible, no JS errors | |
| 5.21 | Status tracking link visible | `assertSee('Track Your Application')`, link contains application status URL | |

### StatusTest (2 tests)

| # | Test | Key assertions | Notes |
|---|------|----------------|-------|
| 5.22 | Status page loads via valid token URL | `assertPathIs('/caregiver/apply/status/{token}')`, applicant info visible, no JS errors | Requires creating application with known token |
| 5.23 | Invalid/expired token shows error | Visit with bogus token, assert error or not-found message | |

### Key Testing Considerations

| Challenge | Solution |
|-----------|----------|
| OTP 6-digit code | Use `000000` bypass (accepted in non-production environments) |
| Photo upload (Step 1) | Use Playwright's `setInputFiles()` via `script()` with `File` constructor: `new File([''], 'photo.jpg', { type: 'image/jpeg' })` |
| Address autocomplete (Step 1) | Skip Google Places autocomplete; fill address fields directly via `fillField()` |
| File uploads (CPR, Trustline in Step 4) | Same approach as photo upload |
| sessionStorage auto-save | Use `script()` to read `sessionStorage.getItem('caregiver_application_draft')` |
| Step navigation | Click "Next"/"Back" buttons found by `textContent.includes('Next')` / `textContent.includes('Back')` |
| Date picker (DOB, Step 1) | Use `fillField()` on date `<input type="date">` |
| Languages multi-select (Step 4) | Click checkboxes via `script()` |
| Dynamic add/remove (experiences, references) | Click "Add Experience" / "Remove" buttons via `script()` |
| Text signatures (Step 8) | `fillField()` on text input — signatures must match applicant's full name |
| Checkbox groups (Steps 2, 4, 6, 7) | Click individual checkboxes via `script()` |
| Step indicator highlighting | `assertSee()` or check for active class on step indicator |
| VerifyEmail middleware | In local/test env, middleware auto-sets session; for wizard tests, seed session via `actingAs()` or session helper |

---

## Tier 6: Misc & Reference Portal (≈2 days, ~8 tests) — 0 done

| # | Test | Key assertions |
|---|------|----------------|
| 6.1 | Public caregiver bio page loads | `/bio/{slug}`, assert bio info rendered |
| 6.2 | Reference submit page loads via token | `assertSee('Leave a Reference')` |
| 6.3 | Reference submits rating + comments | Star rating, fill fields, submit, assert submitted |
| 6.4 | Reference uses invalid/expired token | Assert error or 404 |
| 6.5 | Export bookings sheet (admin) | Open sheet, select month/year, trigger download |
| 6.6 | Charge booking page (admin) | `/admin/bookings/charge` |
| 6.7 | Pricing rules (superadmin) | CRUD operations |
| 6.8 | 404 page for unknown routes | `/this-route-does-not-exist`, assert 404 page rendered |

---

## Tier 7: Edge Cases & Cross-Cutting (≈2 days, ~30 tests) — 4 done

**Goal**: Authorization boundaries, permission checks, empty states, responsive layout, and smoke tests across all roles.

### Completed Tests

| # | Test | File |
|---|------|------|
| 7.1 | Client pages load without JS errors (7-page smoke) | `Smoke/ClientSmokeTest.php` |
| 7.2 | Caregiver pages load without JS errors (5-page smoke) | `Smoke/CaregiverSmokeTest.php` |
| 7.3 | Admin pages load without JS errors (6-page smoke) | `Smoke/AdminSmokeTest.php` |
| 7.4 | Super admin pages load without JS errors (6-page smoke) | `Smoke/SuperAdminSmokeTest.php` |

### Planned Tests

| # | Test | Key assertions |
|---|------|----------------|
| 7.5 | Unauthenticated user is redirected to login | Visit `/dashboard`, `/settings/*`, `/bookings` |
| 7.6 | Client cannot access caregiver routes | `/jobs`, `/payouts`, assert 403 |
| 7.7 | Caregiver cannot access client routes | `/payments`, assert 403 |
| 7.8 | Non-admin cannot access admin routes | `/clients`, `/caregivers`, assert 403 |
| 7.9 | Non-superadmin cannot access superadmin routes | `/certifications`, `/pricing-rules`, assert 403 |
| 7.10 | All public pages return 200 smoke test | `visit(['/login', '/register', '/forgot-password', '/book'])` |
| 7.11 | All authenticated pages return 200 smoke test | Log in as each role, visit all role-appropriate pages |
| 7.12 | Pagination on index pages | Navigate to next page, assert results change |
| 7.13 | Breadcrumbs reflect current page location | Click through navigation, assert breadcrumb updates |
| 7.14 | Form CSRF protection (if applicable) | Assert CSRF token present on all forms |
| 7.15 | Empty states render without JS errors | View index with no data, assert empty state visible |
| 7.16 | Responsive layout on mobile viewport | `visit('/')->on()->mobile()`, assert hamburger menu, no layout breakage |

---

## Test File Structure (current)

```
tests/Browser/
├── Auth/
│   ├── LoginTest.php              — 4 tests
│   ├── RegisterTest.php           — 2 tests
│   ├── PasswordResetTest.php      — 4 tests
│   ├── TwoFactorTest.php          — 2 tests
│   ├── EmailVerificationTest.php  — 2 tests
│   ├── ConfirmPasswordTest.php    — 1 test
│   └── SmokeTest.php              — 2 tests
├── Guest/
│   ├── BookingCreateTest.php      — 8 tests
│   ├── BookingPaymentTest.php     — 1 test
│   ├── BookingConfirmationTest.php — 2 tests
│   └── BookingReviewTest.php      — 5 tests
├── Client/
│   ├── BookingsTest.php           — 2 tests
│   └── BookingDetailTest.php      — 1 test
├── Caregiver/
│   ├── BookingsTest.php           — 1 test
│   └── JobsTest.php               — 1 test
├── Admin/
│   ├── ClientTest.php             — 3 tests
│   └── CaregiverTest.php          — 3 tests
├── Settings/
│   ├── ProfileTest.php            — 3 tests
│   ├── SecurityTest.php           — 3 tests
│   └── AppearanceTest.php         — 1 test
├── Smoke/
│   ├── ClientSmokeTest.php        — 1 test (7 pages)
│   ├── CaregiverSmokeTest.php     — 1 test (5 pages)
│   ├── AdminSmokeTest.php         — 1 test (6 pages)
│   └── SuperAdminSmokeTest.php    — 1 test (6 pages)
├── Caregiver/Apply/               — planned (23 tests)
│   ├── VerifyEmailTest.php        — planned (4 tests)
│   ├── WizardTest.php             — planned (15 tests)
│   ├── ThankYouTest.php           — planned (2 tests)
│   └── StatusTest.php             — planned (2 tests)
└── helpers.php
```

### Planned directory additions

```
tests/Browser/
├── Caregiver/Apply/
│   ├── VerifyEmailTest.php
│   ├── WizardTest.php
│   ├── ThankYouTest.php
│   └── StatusTest.php
```

---

## Critical Testing Notes

### Playwright/Pest Browser Plugin

- Uses `script()`-based helpers (`fillField`, `clickElement`, `selectOption`, `selectOptionByLabel`, `loginViaJs`, `submitGuestBookingForm`) — Playwright `click()`/`hover()` time out on Inertia pages due to actionability checks never completing
- `fillField()` and `clickElement()` use `addslashes()` to handle selectors containing single quotes
- `selectOptionByLabel()` finds Radix Select triggers by label text → parent div → `button[role="combobox"]`
- Submit buttons that are `disabled` or `aria-disabled` need `form.dispatchEvent(new Event('submit'))` rather than `button.click()`
- `assertPathIs()` must be used instead of `assertUrlIs()` (the latter has a full-URL-vs-path regex bug)
- Vendor click fix: `scripts/patch-pest-click.php` patches `Locator::click()` with `force: true`; persisted via `composer.json` hooks (`post-install-cmd`, `post-update-cmd`)

### Session & Auth

- `actingAs()` sets auth; any route behind `password.confirm` middleware needs `session()->put('auth.password_confirmed_at', time())`
- `SESSION_DRIVER=array` persists within PHP built-in server process via static array — guest booking flow works (validadeOnly stores → payment controller retrieves)
- Always run `php artisan optimize:clear` before tests — stale `bootstrap/cache/config.php` caches MySQL from `.env`, overriding `phpunit.xml`'s SQLite

### Database

- Use `RefreshDatabase` trait for clean state
- Lookup tables (SpecialtyType, Location, AttributeDefinition, CertificationType) must be seeded before creating `Caregiver` via factory (CaregiverFactory's `afterCreating` syncs with these tables)
- `Booking::ulid` returns `Symfony\Component\Uid\Ulid` object — cast with `(string)` or call `->toString()`

### Complex Interactions

- Stripe EmbeddedCheckout: requires Stripe test mode keys and test card (`4242 4242 4242 4242`); use `allow_redirect: false` or intercept the redirect
- Google Places Autocomplete: skip or mock; fall back to manual address input
- File uploads: use `script()` with `new File()` constructor and `DataTransfer` + `input.files = ...` + `dispatchEvent(new Event('change'))`
- Radix UI Select triggers show selected value (e.g., "Babysitter"), not placeholder text — finding by label via parent `div` works reliably

---

## Effort Summary

| Tier | Tests | Done | Dev Effort Remaining | Business Impact |
|------|-------|------|---------------------|-----------------|
| 1. Core Auth | ~30 | 17 | <1 day | Critical |
| 2. Guest Booking Flow | ~35 | 16 | ~1.5 days | Critical |
| 3. Authenticated CRUD | ~70 | 12 | ~4 days | High |
| 4. Admin Back Office | ~70 | 6 | ~4.5 days | Medium |
| 5. Caregiver Application | ~23 | 0 | ~3 days | High |
| 6. Misc & Reference | ~8 | 0 | ~1 day | Low |
| 7. Edge Cases & Smoke | ~30 | 4 | ~2 days | High |
| **Total** | **~266** | **55** | **~17 days** | |
