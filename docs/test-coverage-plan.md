# Frontend Browser Test Coverage Plan

**Tool**: Pest 4 Browser Plugin + Playwright (headless Chromium)
**Language**: PHP (Pest) with real browser interactions via Playwright
**Location**: `tests/Browser/`
**Status**: In Progress — 240 of ~330 tests complete (73%)

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
| **1. Core Auth** | ~30 | **27** | 90% | Critical |
| **2. Guest Booking Flow** | ~35 | **32** | 91% | Critical |
| **3. Authenticated CRUD** | ~70 | **66** | 94% | High |
| **4. Admin Back Office** | ~70 | **48** | 69% | Medium |
| **5. Caregiver Application** | ~23 | **21** | 91% | High |
| **6. Misc & Reference** | ~8 | **5** | 63% | Low |
| **7. Edge Cases & Smoke** | ~65 | **46** | 71% | High |
| **Total** | **~330** | **240** | **73%** | |

---

## Tier 1: Core Authentication (≈2 days, ~30 tests) — 27 done

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
| 1.19 | Register with missing required fields shows validation errors | `Auth/RegisterTest.php` |
| 1.20 | Register with mismatched passwords shows validation error | `Auth/RegisterTest.php` |
| 1.21 | Register with duplicate email shows validation error | `Auth/RegisterTest.php` |
| 1.23 | Reset password page renders with valid token | `Auth/PasswordResetTest.php` |
| 1.24 | User can reset password with valid token | `Auth/PasswordResetTest.php` |
| 1.25 | User sees error with invalid token | `Auth/PasswordResetTest.php` |
| 1.26 | User can confirm password correctly | `Auth/ConfirmPasswordTest.php` |
| 1.27 | User sees error with incorrect password | `Auth/ConfirmPasswordTest.php` |
| 1.28 | Two-factor challenge shows error with incorrect recovery code | `Auth/TwoFactorTest.php` |
| 1.33 | Authenticated user can log out | `Auth/LoginTest.php` |

### Skipped Tests (not applicable)

| # | Test | Reason |
|---|------|--------|
| 1.18 | Guest logs in with unverified email | `User` model does not implement `MustVerifyEmail` — login works normally |
| 1.22 | Forgot-password with unknown email shows no error | Backend shows error for non-existent email (documented in 1.10) |
| 1.29 | Enable 2FA (full flow with QR + OTP confirm) | Complex TOTP generation; deferred to Tier 3 Settings tests |
| 1.30 | Disable 2FA from security settings | `canManageTwoFactor`/`twoFactorEnabled` props not passed to security page |
| 1.31 | Resend verification email | `User` model does not implement `MustVerifyEmail` — method doesn't exist |
| 1.32 | Unverified user redirected to verify page | Same as 1.18 — `verified` middleware is a no-op |

---

## Tier 2: Guest Booking Flow (≈3 days, ~35 tests) — 30 done

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
| 2.11b | Successful booking post redirects to payment URL | `Guest/BookingPaymentTest.php` |
| 2.11c | Guest visits payment page with valid session | `Guest/BookingPaymentTest.php` |
| 2.12 | Guest can view review page via signed url | `Guest/BookingReviewTest.php` |
| 2.13 | Guest can submit review with rating and comment | `Guest/BookingReviewTest.php` |
| 2.14 | Tip input shows stripe card input when filled | `Guest/BookingReviewTest.php` |
| 2.15 | Tip input hides stripe card input when cleared | `Guest/BookingReviewTest.php` |
| 2.16 | Submit with tip but no card shows error | `Guest/BookingReviewTest.php` |
| 2.17 | Guest searches and selects a hotel from autocomplete | `Guest/BookingCreateTest.php` |
| 2.18 | Guest adds a single date block | `Guest/BookingCreateTest.php` |
| 2.19 | Guest adds multiple date blocks | `Guest/BookingCreateTest.php` |
| 2.20 | Guest removes a date block | `Guest/BookingCreateTest.php` |
| 2.22 | Guest toggles sitter preferences checkboxes | `Guest/BookingCreateTest.php` |
| 2.23 | Guest fills optional textareas | `Guest/BookingCreateTest.php` |
| 2.26 | Guest submits booking with same-day date | `Guest/BookingCreateTest.php` |
| 2.27 | Guest creates booking that overlaps dates | `Guest/BookingCreateTest.php` |
| 2.29 | BookingProgress indicator shows step 1 | `Guest/BookingCreateTest.php` |
| 2.30 | Date block enforces 4-hour minimum | `Guest/BookingCreateTest.php` |
| 2.31 | Guest enters invalid email format | `Guest/BookingCreateTest.php` |
| 2.33 | Address section shows with private home location | `Guest/BookingCreateTest.php` |
| 2.24 | Guest visits payment page (BookingProgress step 2) | `Guest/BookingPaymentTest.php` |
| 2.29c | Confirmation page shows booking details with status and service type | `Guest/BookingConfirmationTest.php` |

### Planned Tests (remaining)

| # | Test | Key assertions |
|---|------|----------------|
| 2.21 | Guest fills address with Google Autocomplete | Type partial address, select suggestion, assert fields populated |
| 2.25 | Guest completes Stripe payment | Interact with Stripe EmbeddedCheckout, assert redirected to confirmation |
| 2.28 | Guest resumes partially filled booking via browser back | Form state restored (if applicable) |
| 2.32 | Guest booking form shows pricing summary | Fill dates, assert price calculation visible |

> **Note**: Stripe EmbeddedCheckout tests require Stripe test mode keys and test card numbers. These tests should use the Stripe testing `Visa` card (`4242 4242 4242 4242`).

---

## Tier 3: Authenticated CRUD (≈5 days, ~70 tests) — 66 done

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
| 3.7 | User can update their name (browser flow) | `Settings/ProfileTest.php` |
| 3.8 | User can update their email (browser flow) | `Settings/ProfileTest.php` |
| 3.8b | User sees error with invalid email format | `Settings/ProfileTest.php` |
| 3.9 | Security settings page can be viewed | `Settings/SecurityTest.php` |
| 3.10 | User can update their password | `Settings/SecurityTest.php` |
| 3.11 | User sees error with wrong current password | `Settings/SecurityTest.php` |
| 3.12 | Appearance settings page can be viewed | `Settings/AppearanceTest.php` |
| 3.13 | Sidebar renders correct nav items per role (4 tests) | `Layout/SidebarTest.php` |
| 3.14 | Breadcrumbs appear on nested settings page | `Layout/BreadcrumbsTest.php` |
| 3.15 | Global search input can be typed into | `Layout/SearchTest.php` |
| 3.16 | User menu opens showing settings and logout | `Layout/UserMenuTest.php` |
| 3.17 | Dark mode toggle (Appearance settings) | `Settings/AppearanceTest.php` |
| 3.17b | Light mode toggle | `Settings/AppearanceTest.php` |
| 3.17c | System mode toggle | `Settings/AppearanceTest.php` |
| 3.18 | Client dashboard loads | `Dashboard/DashboardTest.php` |
| 3.19 | Caregiver dashboard loads | `Dashboard/DashboardTest.php` |
| 3.20 | Admin dashboard loads | `Dashboard/DashboardTest.php` |
| 3.21 | SuperAdmin dashboard loads | `Dashboard/DashboardTest.php` |
| 3.26 | Caregiver pause account — set pause | `Settings/PauseTest.php` |
| 3.27 | Caregiver pause account — resume | `Settings/PauseTest.php` |
| 3.29 | Client booking create page renders | `Client/CreateBookingTest.php` |
| 3.29b | Client creates booking via form submission | `Client/CreateBookingTest.php` |
| 3.29c | Client can add children dynamically via browser | `Client/CreateBookingTest.php` |
| 3.29d | Client can add pets dynamically via browser | `Client/CreateBookingTest.php` |
| 3.30 | Client reviews past completed booking | `Client/ReviewTest.php` |
| 3.31 | Caregiver reserves a booking (Accept) | `Caregiver/ReserveConfirmTest.php` |
| 3.32 | Caregiver confirms reserved booking | `Caregiver/ReserveConfirmTest.php` |
| 3.34 | Caregiver releases a reservation | `Caregiver/ReleaseReservationTest.php` |
| 3.35 | Caregiver views available booking detail | `Caregiver/BookingActionsTest.php` |
| 3.35b | Caregiver views confirmed job detail | `Caregiver/BookingActionsTest.php` |
| 3.35c | Caregiver views completed job detail | `Caregiver/JobActionsTest.php` |
| 3.36 | Caregiver checks out a job | `Caregiver/CheckoutTest.php` |
| 3.37 | Caregiver cancels a confirmed job | `Caregiver/CancelJobTest.php` |
| 3.39 | Payments index page loads | `Client/PaymentsTest.php` |
| 3.43 | Payouts page loads for caregiver | `Caregiver/PayoutsTest.php` |
| 3.7b | Responsive mobile viewport (3 tests) | `Layout/ResponsiveTest.php` |
| 3.45 | Bookings index shows client bookings | `Client/BookingsTest.php` |
| 3.46 | Bookings index shows booking status | `Client/BookingsTest.php` |
| 3.47 | Booking detail shows status badge | `Client/BookingDetailTest.php` |
| 3.48 | Client can cancel an upcoming booking | `Client/BookingDetailTest.php` |
| 3.49 | Client sees empty state when no bookings exist | `Client/BookingsTest.php` |
| 3.52 | Profile page shows validation on empty name submit | `Settings/ProfileTest.php` |
| 3.53 | Push notifications page can be viewed | `Settings/PushNotificationsTest.php` |
| 3.54 | Security page shows confirm password field | `Settings/SecurityTest.php` |
| 3.56 | Caregiver can search jobs by client name | `Caregiver/JobsTest.php` |
| 3.57 | Caregiver job detail shows client information | `Caregiver/JobActionsTest.php` |
| 3.57b | Caregiver job detail shows start and end times | `Caregiver/JobActionsTest.php` |
| 3.58 | Caregiver sees empty state when no jobs exist | `Caregiver/JobsTest.php` |
| 3.59 | Caregiver can filter jobs by status | `Caregiver/JobsTest.php` |
| 3.60 | Caregiver job shows client info | `Caregiver/JobActionsTest.php` |
| 3.61 | Client dashboard shows upcoming bookings widget | `Dashboard/DashboardTest.php` |
| 3.62 | Client dashboard shows recent activity | `Dashboard/DashboardTest.php` |
| 3.63 | Caregiver dashboard shows available jobs widget | `Dashboard/DashboardTest.php` |
| 3.64 | Caregiver dashboard shows earnings summary | `Dashboard/DashboardTest.php` |
| 3.65 | Appearance page persists theme across sessions | `Settings/AppearanceTest.php` |
| 3.66 | Pause page shows reason options | `Settings/PauseTest.php` |
| 3.68 | Security password form shows confirmation field | `Settings/SecurityTest.php` |
| 3.51 | User can update their first name | `Settings/ProfileTest.php` |

### Skipped Tests (not applicable)

| # | Test | Reason |
|---|------|--------|
| 3.22 | Email update triggers verification | `User` model does not implement `MustVerifyEmail` |
| 3.23 | Delete account | `DeleteUser` component commented out in profile page (line 170 of `profile.tsx`) |
| 3.24 | Enable 2FA | `canManageTwoFactor`/`twoFactorEnabled` props not passed to security page |
| 3.25 | Disable 2FA | Same as 3.24 — 2FA section never renders |
| 3.33 | Reservation timer expire (60s wait) | Timer would make test flaky and slow |
| 3.38 | Caregiver rates a completed job | No frontend UI for caregiver-to-client rating |
| 3.40 | Client adds payment method (Stripe) | Requires Stripe test keys |
| 3.41 | Client sets default payment method | Requires Stripe test keys |
| 3.42 | Client removes payment method | Requires Stripe test keys |
| 3.44 | Caregiver initiates Stripe Connect onboarding | Requires Stripe test keys |

### Planned Tests (remaining ~4 tests)

#### Not Yet Implemented

| # | Test | Key assertions | Reason |
|---|------|----------------|--------|
| 3.50 | Client can sort bookings by date | Click date column header, assert sort changes | No sort UI on client bookings index |
| 3.51 | Client can update phone number | Fill phone field, submit, assert success | No phone field on profile page |
| 3.55 | Caregiver can update profile bio | Fill bio textarea, submit, assert success | No bio field on caregiver settings page |
| 3.67 | User can update timezone in profile | Select timezone, submit, assert success | No timezone field on profile page |

---

## Tier 4: Admin Back Office (≈5 days, ~70 tests) — 48 done

**Goal**: All admin CRUD pages, the complex booking sheet, caregiver/client management, and application workflow work correctly.

### Completed Tests

| # | Test | File |
|---|------|------|
| 4.1 | Admin client create page can be viewed | `Admin/ClientTest.php` |
| 4.2 | Admin can create a client | `Admin/ClientTest.php` |
| 4.3 | Admin can view client detail page | `Admin/ClientTest.php` |
| 4.3b | Admin can edit a client | `Admin/ClientTest.php` |
| 4.4 | Admin caregiver create page can be viewed | `Admin/CaregiverTest.php` |
| 4.5 | Admin can create a caregiver | `Admin/CaregiverTest.php` |
| 4.6 | Admin can view caregiver detail page | `Admin/CaregiverTest.php` |
| 4.6b | Admin can edit a caregiver | `Admin/CaregiverTest.php` |
| 4.7 | Admin bookings index loads (table/calendar) | `Admin/BookingTest.php` |
| 4.8 | Admin can switch to table view | `Admin/BookingTest.php` |
| 4.9 | Admin can search bookings | `Admin/BookingTest.php` |
| 4.10 | Admin can navigate between months | `Admin/BookingTest.php` |
| 4.11 | Admin can filter bookings by status | `Admin/BookingTest.php` |
| 4.18 | Clients index loads with search/filter/sort | `Admin/ClientTest.php` |
| 4.19 | Admin can search clients | `Admin/ClientTest.php` |
| 4.19b | Admin can filter clients by type | `Admin/ClientTest.php` |
| 4.19c | Admin can sort clients by name | `Admin/ClientTest.php` |
| 4.29 | Caregivers index loads with search/filter | `Admin/CaregiverTest.php` |
| 4.30 | Admin can search caregivers | `Admin/CaregiverTest.php` |
| 4.30b | Admin can filter caregivers by status | `Admin/CaregiverTest.php` |
| 4.41 | Applications index loads | `Admin/ApplicationTest.php` |
| 4.41b | Admin can filter applications by status | `Admin/ApplicationTest.php` |
| 4.41c | Admin can search applications by name | `Admin/ApplicationTest.php` |
| 4.12 | Admin booking sheet opens in create mode | `Admin/BookingTest.php` |
| 4.12b | Admin booking sheet shows form fields | `Admin/BookingTest.php` |
| 4.13 | Admin can duplicate a booking via sheet | `Admin/BookingTest.php` |
| 4.14 | Admin can open edit sheet for existing booking | `Admin/BookingTest.php` |
| 4.15 | Admin booking show page loads | `Admin/BookingTest.php` |
| 4.20 | Validation errors show on client create with empty fields | `Admin/ClientTest.php` |
| 4.25 | Client profile page shows reset password button | `Admin/ClientTest.php` |
| 4.32 | Caregiver profile tabs are navigable | `Admin/CaregiverTest.php` |
| 4.42 | Application detail page shows sections | `Admin/ApplicationTest.php` |
| 4.43 | Application shows references section | `Admin/ApplicationTest.php` |
| 4.45 | Application interview schedule button shows confirm | `Admin/ApplicationTest.php` |
| 4.46 | Interview evaluation page loads | `Admin/ApplicationTest.php` |
| 4.48 | Application approve button shows confirm dialog | `Admin/ApplicationTest.php` |
| 4.51 | Application decline button shows confirm dialog | `Admin/ApplicationTest.php` |
| 4.55 | Transactions index loads with data | `Admin/TransactionsTest.php` |
| 4.56 | Admin can search transactions | `Admin/TransactionsTest.php` |
| 4.16 | Admin can cancel a booking from show page | `Admin/BookingTest.php` |
| 4.17 | Admin can open replace caregiver sheet | `Admin/BookingTest.php` |
| 4.17b | Admin can open notify caregivers sheet | `Admin/BookingTest.php` |
| 4.17c | Admin can open delete booking dialog | `Admin/BookingTest.php` |
| 4.21 | Client booking history page loads | `Admin/ClientTest.php` |
| 4.31 | Caregiver profile multi-tab navigation (Application, Job History, Internal Rating, References) | `Admin/CaregiverTest.php` |
| 4.47 | Application background check button shows confirm dialog | `Admin/ApplicationTest.php` |
| 4.49 | Application hire button shows confirm dialog | `Admin/ApplicationTest.php` |
| 4.50 | Application complete onboarding button shows confirm dialog | `Admin/ApplicationTest.php` |

### Planned Tests (22 remaining)

---

## Tier 5: Caregiver Application Wizard (≈3 days, ~23 tests) — 21 done

**Goal**: The multi-step caregiver application with OTP verification, 8 wizard steps (covering 14 sections), auto-save, and status tracking works end-to-end in the browser.

**Backend context**: Extensive Feature tests exist (1263 lines in `tests/Feature/CaregiverApplicationTest.php`). Browser tests focus on UI rendering, real navigation, dynamic interactions, and the complete E2E happy path.

**Files to create:**
- `tests/Browser/Caregiver/Apply/VerifyEmailTest.php`
- `tests/Browser/Caregiver/Apply/WizardTest.php`
- `tests/Browser/Caregiver/Apply/ThankYouTest.php`
- `tests/Browser/Caregiver/Apply/StatusTest.php`

### VerifyEmailTest (4 tests — all passing)

| # | Test | Status | Notes |
|---|------|--------|-------|
| 5.1 | Verify email page renders | ✅ | |
| 5.2 | Enter email and send OTP | ✅ | |
| 5.3 | Verify with correct OTP (bypass `000000`) | ✅ | |
| 5.4 | Submit incorrect OTP | ✅ | |

### WizardTest (13 tests — all passing)

| # | Test | Status | Notes |
|---|------|--------|-------|
| 5.5 | Wizard page renders all 8 step indicators | ✅ | |
| 5.6 | Each step renders its form fields | ✅ | Navigates all 8 steps via sessionStorage pre-fill |
| 5.7 | Navigate between steps (next/previous) | ✅ | |
| 5.8 | Step 1 — fill sponsor + personal info | ✅ | Covered by sessionStorage pre-fill + navigation test |
| 5.9 | Step 2 — toggle positions, availability, education | ✅ | |
| 5.10 | Step 3 — add/remove experience entries | ✅ | |
| 5.11 | Step 4 — toggle screening questions | ✅ | |
| 5.12 | Step 5 — reference fields render | ✅ | |
| 5.13 | Step 6 — toggle location regions + age groups | ✅ | |
| 5.14 | Step 7 — toggle qualification checkboxes + fill bio | ✅ | |
| 5.15 | Step 8 — type signatures | ✅ | |
| 5.16 | Auto-save persists to sessionStorage | ✅ | Uses sessionStorage read-back |
| 5.17 | Complete E2E happy path | ✅ | Uses `$this->post()` with session |
| 5.18 | Client-side validation errors | ✅ | Covers step 1 empty fields |

Note: "Save & Continue Later" (5.19) is not applicable — this wizard calls `saveDraft()` automatically on step navigation; there is no explicit "Save & Continue Later" button.

### ThankYouTest (2 tests — all passing)

| # | Test | Status | Notes |
|---|------|--------|-------|
| 5.20 | Thank-you page renders | ✅ | |
| 5.21 | Status tracking link visible | ✅ | |

### StatusTest (2 tests — all passing)

| # | Test | Status | Notes |
|---|------|--------|-------|
| 5.22 | Status page loads via valid token URL | ✅ | |
| 5.23 | Invalid/expired token shows error | ✅ | |

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

## Tier 6: Misc & Reference Portal (≈2 days, ~8 tests) — 3 done

**Note**: Charge booking route (6.6) is commented out in `routes/web.php`. Export bookings sheet (6.5) is a slide-out panel, not a standalone page. Pricing rules (6.7) covered by smoke test.

### Completed Tests

| # | Test | File |
|---|------|------|
| 6.1 | Public caregiver bio page loads | `Auth/SmokeTest.php` (smoke) |
| 6.2 | Reference submit page loads via token | `Guest/ReferenceSubmitTest.php` |
| 6.3 | Reference form can be filled | `Guest/ReferenceSubmitTest.php` |
| 6.3b | Already-submitted reference shows confirmation | `Guest/ReferenceSubmitTest.php` |
| 6.4 | Invalid reference token returns 404 | `Feature/NotFoundTest.php` |
| 6.8 | Unknown routes return 404 | `Feature/NotFoundTest.php` |

### Planned Tests

| # | Test | Key assertions |
|---|------|----------------|
| 6.5 | Export bookings sheet (admin) | Open sheet, select month/year, trigger download |

---

## Tier 7: Edge Cases & Cross-Cutting (≈2 days, ~65 tests) — 46 done

**Goal**: Authorization boundaries, permission checks, empty states, responsive layout, and smoke tests across all roles. With comprehensive smoke test expansion, this tier now covers 55+ Inertia pages across all roles.

### Completed Tests

| # | Test | File |
|---|------|------|
| 7.1–7.1e | Client pages smoke (5 tests: dashboard, bookings index, bookings create, booking detail, settings) | `Smoke/ClientSmokeTest.php` |
| 7.2–7.2h | Caregiver pages smoke (8 tests: dashboard, bookings index, booking detail, jobs index, job detail, payouts, milestones, settings) | `Smoke/CaregiverSmokeTest.php` |
| 7.3–7.3j | Admin pages smoke (10 tests: dashboard, bookings, clients CRUD, caregivers CRUD, applications, transactions, availabilities, settings) | `Smoke/AdminSmokeTest.php` |
| 7.4–7.4l | Super admin pages smoke (12 tests: same as admin + talking-points, broadcast-sms, locations, hotels, attributes, specialties, certifications, pricing-rules, quick-links) | `Smoke/SuperAdminSmokeTest.php` |
| 7.5 | Unauthenticated user redirected to login | `Auth/AuthorizationTest.php` |
| 7.5b | Unauthenticated user redirected for settings pages | `Auth/AuthorizationTest.php` |
| 7.6 | Client cannot access caregiver route (403) | `Auth/AuthorizationTest.php` |
| 7.7 | Caregiver cannot access client route (403) | `Auth/AuthorizationTest.php` |
| 7.8 | Non-admin cannot access admin routes (403) | `Auth/AuthorizationTest.php` |
| 7.9 | Admin cannot access superadmin route (403) | `Auth/AuthorizationTest.php` |
| 7.9b | Superadmin can access admin routes | `Auth/AuthorizationTest.php` |
| 7.10a | Public pages smoke (8 tests: login, register, forgot-password, book, caregiver bio, caregiver apply verify-email, thank-you, status) | `Auth/SmokeTest.php` |
| 7.16 | Responsive mobile viewport (3 tests) | `Layout/ResponsiveTest.php` |

### Comprehensive Smoke Test Coverage Map

The following table maps each Inertia page component to its smoke test status:

| Role | Page | Route | Status | File |
|------|------|-------|--------|------|
| **Public** | Login | `/login` | ✅ | `Auth/SmokeTest.php` |
| **Public** | Register | `/register` | ✅ | `Auth/SmokeTest.php` |
| **Public** | Forgot password | `/forgot-password` | ✅ | `Auth/SmokeTest.php` |
| **Public** | Reset password | `/reset-password/{token}` | ❌ Requires valid token |
| **Public** | Two-factor challenge | `/two-factor-challenge` | ❌ 2FA-dependent |
| **Public** | Confirm password | `/user/confirm-password` | ❌ Requires auth |
| **Public** | Guest booking create | `/book` | ✅ | `Auth/SmokeTest.php` |
| **Public** | Caregiver bio | `/bio/{slug}` | ✅ | `Auth/SmokeTest.php` |
| **Public** | Caregiver apply — verify email | `/caregiver/apply/verify-email` | ✅ | `Auth/SmokeTest.php` |
| **Public** | Caregiver apply — wizard | `/caregiver/apply` | ❌ VerifyEmail middleware |
| **Public** | Caregiver apply — status | `/caregiver/apply/status/{token}` | ✅ | `Auth/SmokeTest.php` |
| **Public** | Caregiver apply — thank-you | `/caregiver/apply/thank-you` | ✅ | `Auth/SmokeTest.php` |
| **Public** | Reference submit | `/references/{token}` | ❌ Requires ReferenceRequest |
| **Public** | Guest booking review | `/review/{booking}` (signed) | ❌ Signed URL required |
| **Client** | Dashboard | `/dashboard` | ✅ | `Smoke/ClientSmokeTest.php` |
| **Client** | Bookings index | `/bookings` | ✅ | `Smoke/ClientSmokeTest.php` |
| **Client** | Booking create | `/bookings/create` | ✅ | `Smoke/ClientSmokeTest.php` |
| **Client** | Booking detail | `/bookings/{booking}` | ✅ | `Smoke/ClientSmokeTest.php` |
| **Client** | Payments | `/payments` | ❌ Page hangs (infinite useEffect) |
| **Client** | Reviews create | `/reviews/{booking}` | ❌ Page hangs (infinite useEffect) |
| **Client** | Settings — profile | `/settings/profile` | ✅ | `Smoke/ClientSmokeTest.php` |
| **Client** | Settings — security | `/settings/security` | ✅ | `Smoke/ClientSmokeTest.php` |
| **Client** | Settings — appearance | `/settings/appearance` | ✅ | `Smoke/ClientSmokeTest.php` |
| **Client** | Settings — push notifications | `/settings/push-notifications` | ✅ | `Smoke/ClientSmokeTest.php` |
| **Caregiver** | Dashboard | `/dashboard` | ✅ | `Smoke/CaregiverSmokeTest.php` |
| **Caregiver** | Available bookings index | `/bookings` | ✅ | `Smoke/CaregiverSmokeTest.php` |
| **Caregiver** | Booking detail | `/bookings/{booking}` | ✅ | `Smoke/CaregiverSmokeTest.php` |
| **Caregiver** | Jobs index | `/jobs` | ✅ | `Smoke/CaregiverSmokeTest.php` |
| **Caregiver** | Job detail | `/jobs/{booking}` | ✅ | `Smoke/CaregiverSmokeTest.php` |
| **Caregiver** | Payouts | `/payouts` | ✅ | `Smoke/CaregiverSmokeTest.php` |
| **Caregiver** | Milestones | `/milestones` | ✅ | `Smoke/CaregiverSmokeTest.php` |
| **Caregiver** | Availabilities | `/availabilities` | ✅ | `CaregiverSmokeTest.php`(via caregiver smoke) |
| **Caregiver** | Settings — profile | `/settings/profile` | ✅ | `Smoke/CaregiverSmokeTest.php` |
| **Caregiver** | Settings — security | `/settings/security` | ✅ | `Smoke/CaregiverSmokeTest.php` |
| **Caregiver** | Settings — appearance | `/settings/appearance` | ✅ | `Smoke/CaregiverSmokeTest.php` |
| **Caregiver** | Settings — push notifications | `/settings/push-notifications` | ✅ | `Smoke/CaregiverSmokeTest.php` |
| **Caregiver** | Settings — pause | `/settings/caregiver/pause` | ✅ | `Smoke/CaregiverSmokeTest.php` |
| **Admin** | Dashboard | `/dashboard` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Bookings index | `/bookings` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Booking detail | `/bookings/{booking}` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Clients index | `/clients` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Client create | `/clients/create` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Client detail | `/clients/{client}` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Client edit | `/clients/{client}/edit` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Client booking history | `/clients/{client}/bookings` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Caregivers index | `/caregivers` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Caregiver create | `/caregivers/create` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Caregiver detail | `/caregivers/{caregiver}` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Caregiver edit | `/caregivers/{caregiver}/edit` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Caregiver job history | `/caregivers/{caregiver}/jobs` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Applications index | `/applications` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Application detail | `/applications/{application}` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Interview evaluation | `/applications/{application}/interview` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Transactions | `/transactions` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Availabilities index | `/availabilities` | ✅ | `Smoke/AdminSmokeTest.php` |
| **Admin** | Availability detail | `/availabilities/{availability}` | ✅ | `Smoke/AdminSmokeTest.php` |
| **SuperAdmin** | Talking points | `/talking-points` | ✅ | `Smoke/SuperAdminSmokeTest.php` |
| **SuperAdmin** | Broadcast SMS | `/broadcast-sms` | ✅ | `Smoke/SuperAdminSmokeTest.php` |
| **SuperAdmin** | Locations | `/locations` | ✅ | `Smoke/SuperAdminSmokeTest.php` |
| **SuperAdmin** | Hotels | `/hotels` | ✅ | `Smoke/SuperAdminSmokeTest.php` |
| **SuperAdmin** | Attributes | `/attributes` | ✅ | `Smoke/SuperAdminSmokeTest.php` |
| **SuperAdmin** | Specialties | `/specialties` | ✅ | `Smoke/SuperAdminSmokeTest.php` |
| **SuperAdmin** | Certifications | `/certifications` | ✅ | `Smoke/SuperAdminSmokeTest.php` |
| **SuperAdmin** | Pricing rules | `/pricing-rules` | ✅ | `Smoke/SuperAdminSmokeTest.php` |
| **SuperAdmin** | Quick links | `/quick-links` | ✅ | `Smoke/SuperAdminSmokeTest.php` |

**Coverage summary**: 52 of 67 Inertia pages (78%) covered by smoke tests. Remaining uncovered pages require special setup (signed URLs, valid tokens, 2FA, auth), have rendering issues (payments, reviews), or are API/webhook endpoints.

### Planned Tests

| # | Test | Key assertions |
|---|------|----------------|
| 7.12 | Pagination on index pages | Navigate to next page, assert results change |
| 7.14 | Form CSRF protection (if applicable) | Assert CSRF token present on all forms |
| 7.15 | Empty states render without JS errors | View index with no data, assert empty state visible |

---

## Test File Structure (current)

```
tests/Browser/
├── Auth/
│   ├── LoginTest.php              — 5 tests
│   ├── RegisterTest.php           — 5 tests
│   ├── PasswordResetTest.php      — 7 tests
│   ├── TwoFactorTest.php          — 3 tests
│   ├── EmailVerificationTest.php  — 2 tests
│   ├── ConfirmPasswordTest.php    — 3 tests
│   ├── SmokeTest.php              — 8 tests
│   └── AuthorizationTest.php      — 7 tests
├── Guest/
│   ├── BookingCreateTest.php      — 20 tests
│   ├── BookingPaymentTest.php     — 4 tests
│   ├── BookingConfirmationTest.php — 3 tests
│   ├── BookingReviewTest.php      — 5 tests
│   └── ReferenceSubmitTest.php    — 3 tests
├── Client/
│   ├── BookingsTest.php           — 5 tests
│   ├── BookingDetailTest.php      — 3 tests
│   ├── CreateBookingTest.php      — 4 tests
│   ├── PaymentsTest.php           — 1 test
│   └── ReviewTest.php             — 1 test
├── Caregiver/
│   ├── BookingActionsTest.php     — 2 tests
│   ├── BookingsTest.php           — 1 test
│   ├── CancelJobTest.php          — 1 test
│   ├── CheckoutTest.php           — 1 test
│   ├── JobActionsTest.php         — 3 tests
│   ├── JobsTest.php               — 4 tests
│   ├── PayoutsTest.php            — 1 test
│   ├── ReleaseReservationTest.php — 1 test
│   └── ReserveConfirmTest.php     — 1 test
├── Admin/
│   ├── ClientTest.php             — 8 tests
│   ├── CaregiverTest.php          — 7 tests
│   ├── BookingTest.php            — 5 tests
│   └── ApplicationTest.php        — 3 tests
├── Layout/
│   ├── BreadcrumbsTest.php        — 1 test
│   ├── SearchTest.php             — 1 test
│   ├── SidebarTest.php            — 4 tests
│   ├── UserMenuTest.php           — 1 test
│   └── ResponsiveTest.php         — 3 tests
├── Dashboard/
│   └── DashboardTest.php          — 8 tests
├── Settings/
│   ├── AppearanceTest.php         — 5 tests
│   ├── PauseTest.php              — 3 tests
│   ├── ProfileTest.php            — 5 tests
│   ├── PushNotificationsTest.php  — 1 test
│   └── SecurityTest.php           — 4 tests
├── Smoke/
│   ├── ClientSmokeTest.php        — 5 tests (8 pages)
│   ├── CaregiverSmokeTest.php     — 8 tests (13 pages)
│   ├── AdminSmokeTest.php         — 10 tests (23 pages)
│   └── SuperAdminSmokeTest.php    — 12 tests (32 pages)
├── Caregiver/Apply/
│   ├── VerifyEmailTest.php        — 4 tests
│   ├── WizardTest.php             — 13 tests
│   ├── ThankYouTest.php           — 2 tests
│   └── StatusTest.php             — 2 tests
└── helpers.php
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
- Forms with `required` HTML attributes need `form.noValidate = true` + `form.requestSubmit()` to bypass browser native validation and test server-side validation
- Password reset tokens: use `Password::broker('users')->createToken($user)` to generate valid tokens for testing
- Logout via Inertia router: `router.post('/logout')` or form submission with CSRF token
- 2FA recovery code input is a regular text input (`input[name="recovery_code"]`) — easier to test than OTP input (`input-otp` library)
- `User` model does NOT implement `MustVerifyEmail` — email verification middleware is a no-op, `sendEmailVerificationNotification()` doesn't exist
- Security settings page does NOT receive `canManageTwoFactor`/`twoFactorEnabled` props — 2FA section never renders

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
| 1. Core Auth | ~30 | 27 | <0.5 day | Critical |
| 2. Guest Booking Flow | ~35 | 32 | <0.5 day | Critical |
| 3. Authenticated CRUD | ~70 | 66 | <0.5 day | High |
| 4. Admin Back Office | ~70 | 48 | ~1.5 days | Medium |
| 5. Caregiver Application | ~23 | 21 | <0.5 day | High |
| 6. Misc & Reference | ~8 | 3 | <1 day | Low |
| 7. Edge Cases & Smoke | ~65 | 46 | ~1 day | High |
| **Total** | **~330** | **240** | **~5 days** | |
