# Frontend Browser Test Coverage Plan

**Tool**: Pest 4 Browser Plugin + Playwright (headless Chromium)
**Language**: PHP (Pest) with real browser interactions via Playwright
**Location**: `tests/Browser/`
**Status**: Planned

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

## Tier 1: Core Authentication (≈2 days, ~30 tests)

**Goal**: Every auth flow works end-to-end with real browser interaction — form fill, submit, redirect, session persistence, error states.

### Tests

| # | Test | Key assertions |
|---|------|----------------|
| 1.1 | Guest visits login page | `assertSee('Sign In')`, `assertUrlIs('/login')` |
| 1.2 | Guest logs in with valid credentials | `fill('email')`, `fill('password')`, `click('Submit')`, `assertUrlIs('/dashboard')`, `assertAuthenticated()` |
| 1.3 | Guest logs in with incorrect password | `assertSee('These credentials do not match our records.')` |
| 1.4 | Guest logs in with unverified email | Assert stays on login or shows email verification prompt |
| 1.5 | Guest visits register page | `assertSee('Register')` |
| 1.6 | Guest registers with valid data | `fill('first_name', 'last_name', 'email', 'phone', 'password')`, submit, assert redirected + authenticated |
| 1.7 | Guest registers with missing required fields | Assert validation errors shown per field |
| 1.8 | Guest registers with mismatched passwords | Assert password confirmation error |
| 1.9 | Guest registers with duplicate email | Assert unique validation error |
| 1.10 | Guest visits forgot-password page | `assertSee('Forgot Password')` |
| 1.11 | Guest submits forgot-password with valid email | `assertSee('We have emailed your password reset link.')`, `Notification::assertSent()` |
| 1.12 | Guest submits forgot-password with unknown email | No error shown (security best practice) |
| 1.13 | Guest visits reset-password page with valid token | `assertUrlIs()`, assert form rendered |
| 1.14 | Guest resets password with valid token | Submit new password, assert redirected to login, login with new password works |
| 1.15 | Guest resets password with invalid/expired token | Assert error |
| 1.16 | Authenticated user visits confirm-password page | `assertSee('Confirm Password')` |
| 1.17 | User confirms password correctly | Redirected to intended page |
| 1.18 | User confirms password incorrectly | Assert validation error |
| 1.19 | User logs in with 2FA enabled (OTP) | `fill(code)`, submit, assert authenticated |
| 1.20 | User logs in with 2FA enabled (recovery code) | Toggle to recovery code input, fill, submit, assert authenticated |
| 1.21 | User submits incorrect 2FA code | Assert error message |
| 1.22 | Authenticated user enables 2FA | Click enable, assert QR shown, confirm with OTP, assert recovery codes shown |
| 1.23 | Authenticated user disables 2FA | Submit disable, assert 2FA no longer required |
| 1.24 | Verify email page renders | `assertSee('Verify Email')` |
| 1.25 | User resends verification email | Click resend, `assertSee('Verification link sent')` |
| 1.26 | Unverified user is redirected to verify page | Access dashboard, assert redirected to `/email/verify` |
| 1.27 | Authenticated user logs out | Click logout, assert redirected to `/login`, `assertGuest()` |

---

## Tier 2: Guest Booking Flow (≈3 days, ~35 tests)

**Goal**: Full end-to-end journey of a guest creating a booking through payment to confirmation. This is the highest business-value flow.

### Tests

| # | Test | Key assertions |
|---|------|----------------|
| 2.1 | Guest visits `/book` | `assertSee('Book a Sitter')`, form sections visible |
| 2.2 | Guest fills "About You" section | Fill name, email, phone, how_did_you_hear |
| 2.3 | Guest selects service type | `select('service_type')`, conditional fields appear |
| 2.4 | Guest selects location type | `select('location_type')`, rental_platform/hotel fields toggle |
| 2.5 | Guest searches and selects a hotel from autocomplete | Type in autocomplete, click suggestion, assert value set |
| 2.6 | Guest toggles "My hotel is not listed" | Assert hotel_name input appears, autocomplete hides |
| 2.7 | Guest adds a single date block | Pick start/end DateTimePicker, assert block added |
| 2.8 | Guest adds multiple date blocks | Click "Add Dates", assert new row appears, no overlap validation |
| 2.9 | Guest removes a date block | Click remove, assert block removed |
| 2.10 | Guest fills address with Google Autocomplete | Type partial address, select suggestion, assert fields populated |
| 2.11 | Guest adds a child dynamically | Click "Add Child", fill name/gender/birth info, assert row added |
| 2.12 | Guest removes a child | Click remove, assert child removed |
| 2.13 | Guest adds a pet dynamically | Click "Add Pet", fill name/type/breed/notes, assert row added |
| 2.14 | Guest removes a pet | Click remove, assert pet removed |
| 2.15 | Guest toggles sitter preferences checkboxes | Assert checkboxes toggle |
| 2.16 | Guest fills optional textareas | caregiver_notes, notes_to_sitterwise, emergency_instructions, special_needs_notes |
| 2.17 | Guest submits booking with incomplete form | Assert client-side validation errors shown |
| 2.18 | Guest submits booking with valid complete form | Submit, assert redirected to `/book/payment/{token}` |
| 2.19 | Guest visits payment page | `assertUrlIs()`, `assertSee('Payment')`, BookingProgress shows step 2 |
| 2.20 | Guest completes Stripe payment | Interact with Stripe EmbeddedCheckout, assert redirected to confirmation |
| 2.21 | Guest visits confirmation page | `assertSee('Booking Confirmed')`, booking details displayed |
| 2.22 | Guest visits review page from confirmation link | `assertUrlIs('/review/{ulid}')` |
| 2.23 | Guest submits a review with rating + comment | Select star rating, fill comment, submit, assert redirected to success page |
| 2.24 | Guest submits a review with tip via Stripe | Add tip amount, Stripe card input appears, fill card, submit, assert success |
| 2.25 | Guest submits booking with same-day date | Assert same-day warning banner visible |
| 2.26 | Guest creates booking that overlaps dates | Assert overlap validation warning shown |
| 2.27 | Guest resumes partially filled booking via browser back | Form state restored (if applicable) |
| 2.28 | BookingProgress indicator shows correct step | assert step 1 active on `/book`, step 2 on payment, step 3 on confirmation |
| 2.29 | Date block enforces 4-hour minimum | Set start, assert end auto-adjusts to start + 4h |
| 2.30 | Guest enters invalid email format | Assert validation error |

> **Note**: Stripe EmbeddedCheckout tests require Stripe test mode keys and test card numbers. These tests should use the Stripe testing `Visa` card (`4242 4242 4242 4242`).

---

## Tier 3: Authenticated CRUD (≈5 days, ~70 tests)

**Goal**: All role-specific dashboards, booking lists, settings, and profile management work correctly.

### Layout & Navigation

| # | Test | Key assertions |
|---|------|----------------|
| 3.1 | App sidebar renders with correct nav items per role | Client sees "My Bookings", caregiver sees "My Jobs", admin sees "Clients" etc. |
| 3.2 | Breadcrumbs render correctly on nested pages | `assertSee('Settings / Profile')` |
| 3.3 | Global search executes and shows results | Type query, assert suggestions appear |
| 3.4 | User menu dropdown opens | Click user avatar, assert menu items visible |
| 3.5 | Theme toggle (Appearance settings) | Switch to dark mode, assert `dark` class on `<html>` |

### Dashboards (×4 roles)

| # | Test | Key assertions |
|---|------|----------------|
| 3.6 | Client dashboard loads | `assertSee('My Bookings')`, `assertSee('Upcoming')` |
| 3.7 | Caregiver dashboard loads | `assertSee('My Jobs')` |
| 3.8 | Admin dashboard loads | Stats cards visible |
| 3.9 | SuperAdmin dashboard loads | Admin-level stats visible |

### Settings

| # | Test | Key assertions |
|---|------|----------------|
| 3.10 | Profile settings — update name and email | Fill, submit, assert success flash + updated values displayed |
| 3.11 | Profile settings — email update triggers verification | change email, assert resend verification prompt |
| 3.12 | Profile settings — delete account | Open dialog, enter password, confirm, assert account deleted |
| 3.13 | Security settings — update password | Fill current + new + confirm, submit, assert success |
| 3.14 | Security settings — update password with wrong current | Assert validation error |
| 3.15 | Security settings — enable 2FA | Full flow: enable → show QR → confirm OTP → show recovery codes |
| 3.16 | Security settings — disable 2FA | Assert 2FA removed on next login |
| 3.17 | Appearance settings — switch theme | Click light/dark/system, assert applied |
| 3.18 | Caregiver pause account — set pause | Pick resume date, add reason, submit, assert paused status |
| 3.19 | Caregiver pause account — resume | Click resume, assert active status |

### Client Bookings

| # | Test | Key assertions |
|---|------|----------------|
| 3.20 | Client bookings index loads | Booking list visible, pagination if > 1 page |
| 3.21 | Client booking detail loads | Booking details, status, actions visible |
| 3.22 | Client creates booking (authenticated) | Same form as guest but pre-filled with user data |
| 3.23 | Client reviews past booking | Star rating + comment submit |

### Caregiver Bookings / Jobs

| # | Test | Key assertions |
|---|------|----------------|
| 3.24 | Caregiver available bookings index | `assertSee('Available Bookings')` |
| 3.25 | Caregiver reserves a booking | Click "Accept", assert reserved status |
| 3.26 | Caregiver confirms reserved booking within timer | Click "Confirm", assert confirmed status |
| 3.27 | Caregiver lets reservation timer expire | Wait 60s, assert booking released |
| 3.28 | Caregiver releases a confirmed booking | Click "Release", assert released status |
| 3.29 | Caregiver views their jobs list | `assertSee('My Jobs')` |
| 3.30 | Caregiver views job detail | Booking details, checkout button visible |
| 3.31 | Caregiver checks out a job (submits hours) | Fill start/end datetime, reimbursement, bonus, submit |
| 3.32 | Caregiver cancels a job | Open cancel dialog, fill reason, submit, assert cancelled |
| 3.33 | Caregiver rates a completed job | Star rating + comment, submit |

### Client Payments

| # | Test | Key assertions |
|---|------|----------------|
| 3.34 | Payments index loads with payment methods | cards displayed |
| 3.35 | Client adds payment method (Stripe) | Interact with Stripe card input, assert method added |
| 3.36 | Client sets default payment method | Click "Set Default", assert default badge |
| 3.37 | Client removes payment method | Confirm dialog, assert method removed |

### Caregiver Payouts

| # | Test | Key assertions |
|---|------|----------------|
| 3.38 | Payouts page loads for caregiver | `assertSee('Payouts')` |
| 3.39 | Caregiver initiates Stripe Connect onboarding | Click connect, assert redirected to Stripe |

---

## Tier 4: Admin Back Office (≈5 days, ~70 tests)

**Goal**: All admin CRUD pages, the complex booking sheet, caregiver/client management, and application workflow work correctly.

### Admin Bookings

| # | Test | Key assertions |
|---|------|----------------|
| 4.1 | Admin bookings index loads (table view) | Booking rows visible with filters |
| 4.2 | Admin bookings index loads (calendar view) | Toggle to calendar, assert calendar rendered |
| 4.3 | Admin switches between calendar/table view | assert persisted in localStorage |
| 4.4 | Admin searches bookings | Type in search input, assert debounced results |
| 4.5 | Admin opens booking sheet (slide-out panel) | Click "Create Booking", assert sheet slides in |
| 4.6 | Admin creates booking via booking sheet | Fill ~30 fields (client autocomplete, dates, children, pets, attributes), submit, assert created |
| 4.7 | Admin duplicates booking | Click duplicate, assert form pre-filled |
| 4.8 | Admin edits booking | Modify fields, submit, assert updated |
| 4.9 | Admin deletes booking | Open delete dialog, confirm, assert removed |
| 4.10 | Admin splits booking group | Open split dialog, select bookings, confirm |
| 4.11 | Admin filters bookings by status/caregiver/date | assert URL query params + filtered results |
| 4.12 | Admin uses client autocomplete in booking sheet | Type partial name, assert suggestions appear, select one |
| 4.13 | Admin uses caregiver autocomplete in booking sheet | Same as above |
| 4.14 | Admin uses hotel autocomplete | Same as above |

### Admin Clients

| # | Test | Key assertions |
|---|------|----------------|
| 4.15 | Clients index loads | Table with search/filter |
| 4.16 | Admin searches clients | Debounced search, results update |
| 4.17 | Admin creates a client | Fill all fields, submit, assert created + redirected |
| 4.18 | Admin creates a client with missing required fields | Assert validation errors |
| 4.19 | Admin views client detail | Client info, children, pets, addresses, past bookings |
| 4.20 | Admin edits a client | Update fields, add child/pet/address, submit |
| 4.21 | Admin adds address with Google Autocomplete | Same as guest booking |
| 4.22 | Admin adds a child dynamically | Click "Add Child", fill details |
| 4.23 | Admin adds a pet dynamically | Click "Add Pet", fill details |
| 4.24 | Admin resets client password | Open dialog, enter new password, submit |
| 4.25 | Admin uploads client profile photo | Select file, upload, assert photo updated |
| 4.26 | Admin adds payment method for client | Stripe card input |
| 4.27 | Admin views client booking history | Click link, assert filtered booking table |

### Admin Caregivers

| # | Test | Key assertions |
|---|------|----------------|
| 4.28 | Caregivers index loads | Table with search/filter/pagination |
| 4.29 | Admin searches caregivers | Debounced, results update |
| 4.30 | Admin creates a caregiver | Fill personal info, submit, assert created |
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

### Admin Applications

| # | Test | Key assertions |
|---|------|----------------|
| 4.41 | Applications index loads | Applicant list with status badges |
| 4.42 | Admin views application detail | Full application info visible |
| 4.43 | Admin toggles reference checklist item | Click, assert toggled |
| 4.44 | Admin resends reference email | Click, assert success |
| 4.45 | Admin schedules interview | Pick date/time, submit, assert scheduled |
| 4.46 | Admin submits interview evaluation | Fill heart ratings for soft skills + professionalism, add notes, submit |
| 4.47 | Admin starts background check | Click, assert background check status updated |
| 4.48 | Admin approves application | Click, assert status changed to approved |
| 4.49 | Admin hires applicant | Click, assert caregiver created + onboarding started |
| 4.50 | Admin completes onboarding | Click, assert onboarding complete |
| 4.51 | Admin declines application | Open dialog, fill reason, submit, assert declined |

### Admin Availabilities

| # | Test | Key assertions |
|---|------|----------------|
| 4.52 | Availabilities index loads | caregiver list with availability status |
| 4.53 | Admin edits availability per caregiver | Open sheet, toggle time slots, save |
| 4.54 | Admin deletes availability | Confirm dialog, assert removed |

### Admin Transactions

| # | Test | Key assertions |
|---|------|----------------|
| 4.55 | Transactions index loads | Transaction list, totals visible |
| 4.56 | Admin filters transactions | date range, status filters |

### SuperAdmin Pages

| # | Test | Key assertions |
|---|------|----------------|
| 4.57 | Certifications CRUD | index, create, edit, delete |
| 4.58 | Specialties CRUD | index, create, edit, delete |
| 4.59 | Locations CRUD | index, create, edit, delete |
| 4.60 | Attributes CRUD | index, create, edit, delete |
| 4.61 | Hotels CRUD | index, create, edit, delete |
| 4.62 | Pricing Rules CRUD | index, create, edit, delete |
| 4.63 | Quick Links CRUD | index, create, edit, delete |
| 4.64 | Broadcast SMS form | Fill message, submit, assert sent |

---

## Tier 5: Caregiver Application Wizard (≈3 days, ~25 tests)

**Goal**: The multi-step caregiver application with 14 sections, OTP verification, and auto-save works end-to-end.

| # | Test | Key assertions |
|---|------|----------------|
| 5.1 | Guest visits `/caregiver/apply` | `assertSee('Apply as a Caregiver')` |
| 5.2 | Guest enters email for OTP verification | Submit email, assert OTP sent |
| 5.3 | Guest verifies with correct OTP | Fill OTP, submit, assert redirected to wizard |
| 5.4 | Guest submits incorrect OTP | Assert error message |
| 5.5 | Guest fills personal info section | Name, DOB, address via autocomplete |
| 5.6 | Guest fills availability section | Toggle weekday morning/afternoon/evening |
| 5.7 | Guest fills education section | Select level, fill college/degree/year |
| 5.8 | Guest adds an experience entry dynamically | Role, organization, dates, description, ages served |
| 5.9 | Guest removes an experience entry | Click remove, assert row removed |
| 5.10 | Guest selects age groups comfortable with | Checkbox group |
| 5.11 | Guest fills pet experience section | Yes/no, types, medication comfort |
| 5.12 | Guest fills special needs experience | textarea |
| 5.13 | Guest adds certifications | Type + expiration date |
| 5.14 | Guest fills safety questions | Checkbox group |
| 5.15 | Guest adds 2+ references dynamically | Fill first/last name, email, phone, relationship, years_known |
| 5.16 | Guest fills background section | Conviction radio + explanation |
| 5.17 | Guest checks acknowledgment checkboxes | agree_to_terms, agree_to_background_check |
| 5.18 | Guest saves progress mid-way | Click save, assert progress persisted |
| 5.19 | Guest submits complete application | All sections filled, submit, assert redirected to thank-you |
| 5.20 | Guest submits incomplete application | Assert section-level validation errors |
| 5.21 | Guest returns to application via status link | `/caregiver/apply/status/{token}`, assert correct status shown |
| 5.22 | Auto-save fires after 60s of inactivity | Wait, assert progress saved |

---

## Tier 6: Misc & Reference Portal (≈2 days, ~20 tests)

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

## Tier 7: edge Cases & Cross-Cutting (≈2 days, ~30 tests)

| # | Test | Key assertions |
|---|------|----------------|
| 7.1 | Unauthenticated user is redirected to login for all auth pages | Visit `/dashboard`, `/settings/*`, `/bookings`, assert redirected |
| 7.2 | Client cannot access caregiver routes | `/jobs`, `/payouts`, assert 403 |
| 7.3 | Caregiver cannot access client routes | `/payments`, assert 403 |
| 7.4 | Non-admin cannot access admin routes | `/clients`, `/caregivers`, assert 403 |
| 7.5 | Non-superadmin cannot access superadmin routes | `/certifications`, `/pricing-rules`, assert 403 |
| 7.6 | All public pages return 200 smoke test | `visit(['/login', '/register', '/forgot-password', '/book'])`, `assertNoJavaScriptErrors()` |
| 7.7 | All authenticated pages return 200 smoke test | Log in as each role, visit all role-appropriate pages, `assertNoJavaScriptErrors()` |
| 7.8 | Pagination on index pages | Navigate to next page, assert results change |
| 7.9 | Breadcrumbs reflect current page location | Click through navigation, assert breadcrumb updates |
| 7.10 | Form CSRF protection (if applicable) | Assert CSRF token present on all forms |
| 7.11 | Empty states render without JS errors | View index with no data, assert empty state visible |
| 7.12 | Responsive layout on mobile viewport | `visit('/')->on()->mobile()`, assert hamburger menu, no layout breakage |

---

## Setup

### Dependencies

```bash
composer require pestphp/pest-plugin-browser --dev
npm install playwright@latest
npx playwright install
```

### Configuration

**`tests/Pest.php`** — add Browser test suite:

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

pest()->browser()
    ->inChrome()
    ->in('Browser');
```

**`phpunit.xml`** — add Browser test suite:

```xml
<testsuite name="Browser">
    <directory>tests/Browser</directory>
</testsuite>
```

**`.gitignore`** — add:

```
tests/Browser/Screenshots
tests/Browser/console-logs
```

### Running Tests

```bash
# All browser tests
./vendor/bin/pest --testsuite=Browser

# Single file
./vendor/bin/pest --testsuite=Browser tests/Browser/AuthTest.php

# With headed browser for debugging
./vendor/bin/pest --testsuite=Browser --debug

# Parallel
./vendor/bin/pest --testsuite=Browser --parallel
```

### Test File Structure

```
tests/Browser/
├── Auth/
│   ├── LoginTest.php
│   ├── RegisterTest.php
│   ├── PasswordResetTest.php
│   ├── TwoFactorTest.php
│   └── EmailVerificationTest.php
├── Guest/
│   ├── BookingCreateTest.php
│   ├── BookingPaymentTest.php
│   ├── BookingReviewTest.php
│   └── CaregiverApplyTest.php
├── Client/
│   ├── DashboardTest.php
│   ├── BookingTest.php
│   ├── ReviewTest.php
│   └── PaymentTest.php
├── Caregiver/
│   ├── DashboardTest.php
│   ├── JobTest.php
│   ├── CheckoutTest.php
│   └── PayoutTest.php
├── Admin/
│   ├── BookingTest.php
│   ├── ClientManagementTest.php
│   ├── CaregiverManagementTest.php
│   ├── ApplicationTest.php
│   └── AvailabilityTest.php
├── SuperAdmin/
│   ├── CertificationsTest.php
│   ├── SpecialtiesTest.php
│   ├── PricingRulesTest.php
│   └── BroadcastSMSTest.php
├── Settings/
│   ├── ProfileTest.php
│   ├── SecurityTest.php
│   └── AppearanceTest.php
├── Public/
│   ├── CaregiverBioTest.php
│   └── ReferenceTest.php
└── SmokeTest.php
```

### Stripe Testing Notes

- Use Stripe test mode keys in `.env.testing`
- Use Stripe test card `4242 4242 4242 4242` for successful payments
- Use Stripe test card `4000 0000 0000 0002` for declined payments
- Set `STRIPE_KEY` and `STRIPE_SECRET` in `.env` for test mode
- For EmbeddedCheckout, use `allow_redirect: false` or intercept the redirect

### Google Address Autocomplete Notes

- Use `data: { ... }` mocking or set a known test API key
- Skip autocomplete tests if API key is not configured in CI
- Fall back to manual address input tests as primary coverage

### WebSocket / Echo Notes

- Realtime features (countdown timers, `JobReserved` events) should be tested with:
  - `visit()` to assert UI renders correctly (static)
  - Mocking Echo with a fake driver or skipping realtime assertion
  - Testing the UI state transitions via direct backend state changes + page reload

---

## Effort Summary

| Tier | Tests | Dev Effort | Business Impact |
|------|-------|------------|-----------------|
| 1. Core Auth | ~30 | ~2 days | Critical |
| 2. Guest Booking Flow | ~35 | ~3 days | Critical |
| 3. Authenticated CRUD | ~70 | ~5 days | High |
| 4. Admin Back Office | ~70 | ~5 days | Medium |
| 5. Caregiver Application | ~25 | ~3 days | High |
| 6. Misc & Reference | ~20 | ~2 days | Low |
| 7. Edge Cases & Smoke | ~30 | ~2 days | High |
| **Total** | **~280** | **~22 days** | |
