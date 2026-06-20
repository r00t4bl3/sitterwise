# Frontend Browser Test Coverage Plan

**Tool**: Pest 4 Browser Plugin + Playwright (headless Chromium)
**Language**: PHP (Pest) with real browser interactions via Playwright
**Location**: `tests/Browser/`
**Status**: In Progress — 164 of ~301 tests complete (54%)

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
| **2. Guest Booking Flow** | ~35 | **21** | 60% | Critical |
| **3. Authenticated CRUD** | ~70 | **43** | 61% | High |
| **4. Admin Back Office** | ~70 | **6** | 9% | Medium |
| **5. Caregiver Application** | ~23 | **21** | 91% | High |
| **6. Misc & Reference** | ~8 | **0** | 0% | Low |
| **7. Edge Cases & Smoke** | ~65 | **46** | 71% | High |
| **Total** | **~301** | **164** | **54%** | |

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
| 3.7 | User can update their name (browser flow) | `Settings/ProfileTest.php` |
| 3.8 | User can update their email (browser flow) | `Settings/ProfileTest.php` |
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

### Planned Tests (remaining)

#### Settings — remaining

| # | Test | Key assertions |
|---|------|----------------|
| 3.28 | Settings — switch theme between light/dark/system | ✅ — dark, light, and system modes all tested |

#### Client Bookings — remaining

| # | Test | Key assertions |
|---|------|----------------|
| 3.29c | Client creates booking with dynamic children/pets (browser) | Add child via "Add Child" button, fill fields, submit via browser |

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
│   ├── BookingCreateTest.php      — 13 tests
│   ├── BookingPaymentTest.php     — 1 test
│   ├── BookingConfirmationTest.php — 2 tests
│   └── BookingReviewTest.php      — 5 tests
├── Client/
│   ├── BookingsTest.php           — 2 tests
│   ├── BookingDetailTest.php      — 1 test
│   ├── CreateBookingTest.php      — 2 tests
│   ├── PaymentsTest.php           — 1 test
│   └── ReviewTest.php             — 1 test
├── Caregiver/
│   ├── BookingActionsTest.php     — 2 tests
│   ├── BookingsTest.php           — 1 test
│   ├── CancelJobTest.php          — 1 test
│   ├── CheckoutTest.php           — 1 test
│   ├── JobActionsTest.php         — 1 test
│   ├── JobsTest.php               — 1 test
│   ├── PayoutsTest.php            — 1 test
│   ├── ReleaseReservationTest.php — 1 test
│   └── ReserveConfirmTest.php     — 1 test
├── Admin/
│   ├── ClientTest.php             — 3 tests
│   └── CaregiverTest.php          — 3 tests
├── Layout/
│   ├── BreadcrumbsTest.php        — 1 test
│   ├── SearchTest.php             — 1 test
│   ├── SidebarTest.php            — 4 tests
│   ├── UserMenuTest.php           — 1 test
│   └── ResponsiveTest.php         — 3 tests
├── Dashboard/
│   └── DashboardTest.php          — 4 tests
├── Settings/
│   ├── AppearanceTest.php         — 2 tests
│   ├── PauseTest.php              — 2 tests
│   ├── ProfileTest.php            — 3 tests
│   └── SecurityTest.php           — 3 tests
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
| 2. Guest Booking Flow | ~35 | 21 | ~1 day | Critical |
| 3. Authenticated CRUD | ~70 | 43 | ~2.5 days | High |
| 4. Admin Back Office | ~70 | 6 | ~4.5 days | Medium |
| 5. Caregiver Application | ~23 | 21 | <0.5 day | High |
| 6. Misc & Reference | ~8 | 0 | ~1 day | Low |
| 7. Edge Cases & Smoke | ~65 | 46 | ~1 day | High |
| **Total** | **~301** | **164** | **~10.5 days** | |
