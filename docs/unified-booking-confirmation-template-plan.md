# Unified Booking Confirmation Template

Consolidate single-date and multi-date client booking confirmation emails into a single SendGrid template (`@sitterwise-booking-confirmation.html`).

## Data Gaps

### 1. `dates[]` array missing from both data sources

The template uses `{{#each dates}}` with `{{this.date}}`, `{{this.start_time}}`, `{{this.end_time}}`.

- `Booking::toEmailData()` — has flat `date`, `start_time`, `end_time` but no `dates` array
- `BookingGroup::toEmailData()` — has `sibling_dates` array (wrong key name), but is currently **unused** by any mailable

**Fix:** Add `dates` key to both methods. Single-date gets a 1-element array. Multi-date mirrors `sibling_dates`.

**Files:** `app/Models/Booking.php` (toEmailData), `app/Models/BookingGroup.php` (toEmailData)

### 2. `is_multi_day` flag missing

The template uses `{{#if is_multi_day}}` in 5 locations. No data source sets it.

**Fix:** Add `'is_multi_day' => false` to `Booking::toEmailData()`, `'is_multi_day' => true` to `BookingGroup::toEmailData()`.

**Files:** `app/Models/Booking.php`, `app/Models/BookingGroup.php`

### 3. `password_setup_url` only in `GuestAccountSetupMail`

The template has a "Set up your account" section (lines 198-228) with `{{password_setup_url}}`. Currently only `GuestAccountSetupMail` passes this — it's not available in `ClientBookingCreatedMail` or `ClientGroupBookingCreatedMail`.

**Options:**
- **Thread through events** — modify `BookingCreated`/`BookingGroupCreated` events to carry optional `resetToken`, pass through listeners → notifications → mailables
- **Generate in mailable** — generate password reset link from the mailable using the password broker when user is a guest
- **Keep separate** — add `{{#if password_setup_url}}` to the template, keep `GuestAccountSetupMail` as a second email for guests

**Files involved:** `app/Events/BookingCreated.php`, `app/Events/BookingGroupCreated.php`, `app/Listeners/SendBookingCreatedNotifications.php`, `app/Listeners/SendBookingGroupCreatedNotifications.php`, `app/Notifications/BookingCreatedNotification.php`, `app/Mail/ClientBookingCreatedMail.php`, `app/Mail/ClientGroupBookingCreatedMail.php`

### 4. Template needs `{{#if password_setup_url}}` guard

The account setup section (lines 198-228) renders unconditionally. Existing clients will see a broken "Set Up My Account" button linking to `#` if `password_setup_url` is not passed.

**Fix:** Wrap the section with `{{#if password_setup_url}}` in the SendGrid template.

### 5. Multi-date group mailables use Blade, not SendGrid

`ClientGroupBookingCreatedMail` and `AdminGroupBookingCreatedMail` render Blade templates (`client-group-booking-created.blade.php`, `admin-group-booking-created.blade.php`). They need to be converted to SendGrid to use the new template.

**Fix:** Convert `ClientGroupBookingCreatedMail` to SendGrid with the new template ID. `AdminGroupBookingCreatedMail` can stay as Blade or be updated separately (admin-facing).

**Files:** `app/Mail/ClientGroupBookingCreatedMail.php` (rewrite), `app/Mail/AdminGroupBookingCreatedMail.php` (optional)

### 6. Group listener uses `Mail::to()` directly, not Notification system

`SendBookingGroupCreatedNotifications` sends via `Mail::to()->send()` directly, skipping the Notification system. This means:

- No database notification is created for multi-date bookings (asymmetry vs single-date)
- The mailable's `to` must be set explicitly (pattern already fixed for all notifications)

**Fix:** Convert `SendBookingGroupCreatedNotifications` to use the notification pattern (like `SendBookingCreatedNotifications`), or keep direct `Mail::to()` but ensure `to` is set on the mailable.

**Files:** `app/Listeners/SendBookingGroupCreatedNotifications.php`

### 7. No changes planned for admin emails

The new template is client-facing. Admin emails (`AdminBookingCreatedMail`, `AdminGroupBookingCreatedMail`) still use their own SendGrid template / Blade layout. Confirm if they should remain unchanged or also be consolidated.

### 8. Admin-created bookings for new clients never dispatch `GuestAccountSetup`

When an admin creates a booking for a brand-new email, `AdminBookingService::store()` creates the User+Client but never dispatches `GuestAccountSetup`. This is a pre-existing issue — the new client never receives a password-setup email regardless of this template work.

**Fix (out of scope but noted):** Dispatch `GuestAccountSetup` from `AdminBookingService::store()` when a new user is created.

## Template Already Handles

| Feature | Status |
|---|---|
| `{{client_first_name}}`, `{{client_name}}` | Both data sources have these |
| `{{service_requested}}` | `Booking::toEmailData()` has it; `BookingGroup::toEmailData()` has `service_type` |
| `{{children_summary}}` | Both have it |
| `{{location}}`, `{{is_hotel}}` | Same pattern in both |
| `{{special_considerations}}` | Same pattern in both |
| `{{notes_for_sitter}}` | Same pattern in both |
| `{{#each dates}}` for single-date | Needs `dates` array added (gap 1) |

## Files to Modify

| File | Change |
|---|---|
| `app/Models/Booking.php` | Add `dates`, `is_multi_day` to `toEmailData()` |
| `app/Models/BookingGroup.php` | Add `dates`, `is_multi_day` to `toEmailData()` |
| `app/Mail/ClientBookingCreatedMail.php` | Update `template_id` to new template |
| `app/Mail/ClientGroupBookingCreatedMail.php` | Convert from Blade to SendGrid, new template ID |
| `app/Listeners/SendBookingGroupCreatedNotifications.php` | Update to set `to` on mailable (or convert to notification pattern) |
| SendGrid console | Create new template, add `{{#if password_setup_url}}` guard |

**Optional / depends on `password_setup_url` approach:**

| File | Change |
|---|---|
| `app/Events/BookingCreated.php` | Add optional `?string $resetToken = null` |
| `app/Events/BookingGroupCreated.php` | Add optional `?string $resetToken = null` |
| `app/Listeners/SendBookingCreatedNotifications.php` | Pass `resetToken` to notification |
| `app/Notifications/BookingCreatedNotification.php` | Accept and pass `resetToken` to mailable |
