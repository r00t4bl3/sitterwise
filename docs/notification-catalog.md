# Notification Catalog

## Notifications (`app/Notifications/`)

| Class | Receiver | Trigger | Channels | Template | Test |
|---|---|---|---|---|---|
| AdminCaregiverArchivedNotification | admin | `ArchiveLongTermInactive` command | email | SendGrid `d-6c385f3b5a5f4e5180ccee4fedc09106` | none |
| AdminCaregiverBackedOutNotification | admin | `AssignmentController@backOut` | email | SendGrid `d-44ad02d6c50343709900263b8d1c3b28` | none |
| AdminGroupBookingCreatedNotification | admin | `BookingGroupCreated` event | email | SendGrid `d-de8ddf0050cf4ec29caee8c210c6263f` (shared with single) | none |
| AdminNewApplicationNotification | admin | application submitted | email | SendGrid `d-15f3364a4b4f493a9caa6e7031d96685` | none |
| BookingAcceptedNotification | client, caregiver, admin | `BookingAccepted` event | email, db, sms(client) | SendGrid `d-636bec70c9e74cf8a708086896e84539`, `d-3f3ed05c7e5f4c40bcdffbc967ef8bdb`, `d-3cdfa4a1b83746009e07db0f0261afa4` | ✅ `BookingAcceptedNotificationTest.php` |
| BookingCancelledNotification | client, caregiver, admin | `BookingCancelled` event | email | SendGrid `d-71b39db4e170449fba2de7234e8d5961`, `d-286f15d2045541babef403f5fde86cef`, `d-965c67b476c54002b0912d87f5805303` | none |
| BookingCreatedNotification | client, admin | `BookingCreated` event | email, db | SendGrid `d-53f1d52866924c3096bd0d7deae965e6` / `d-de8ddf0050cf4ec29caee8c210c6263f` | ✅ `BookingCreatedNotificationTest.php` |
| BookingInvitationNotification | caregiver | `BookingInvitationSent` event | email, db, sms, push | SendGrid `d-aac404a830334ae884098a75cb32caca` / Blade `emails.booking-notification` * | ✅ `BookingInvitationNotificationTest.php` |
| BookingReceiptNotification | client | `BookingReceipt` event | email, db | SendGrid `d-ade9c101da2d40d78a2742577e6d3efe` | ✅ `BookingReceiptNotificationTest.php` |
| BookingReminderNotification | caregiver | `SendBookingReminders` command | email, db | SendGrid `d-c141f95e479746dd8af8d96aa1c64067` | ✅ `BookingNotificationTest.php` |
| BookingReviewReminderNotification | client | `SendReviewReminders` command | email, sms | SendGrid `d-ed4e08ffb28648f4aee1485389653810` / Blade `emails.review-reminder` * | ✅ `BookingReviewReminderTest.php` |
| ClientGroupBookingCreatedNotification | client | `BookingGroupCreated` event | email, db | SendGrid `d-53f1d52866924c3096bd0d7deae965e6` (shared with single) | none |
| ClientPaymentRequiredNotification | client | `BookingCreated` / `BookingGroupCreated` | email, db | SendGrid `d-9f4b24bb450140d9bd2c1628b705fbc1` | none |
| ClientPaymentSmsReminderNotification | client | `SendPaymentSmsReminders` command | sms | n/a (plain text) | none |
| GuestAccountSetupNotification | client | `GuestAccountSetup` event | db only | n/a | ✅ `GuestAccountSetupNotificationTest.php` |
| PaymentFailedNotification | client, admin | Stripe charge failure | email, db | SendGrid `d-ffd19317faa641ac83e898f159ed7692` / Blade `emails.admin-payment-failed` * | ✅ `PaymentFailureNotificationTest.php` |
| ReferenceCompletedNotification | admin | reference submitted | email | SendGrid `d-622707caa2b54456a6921f032fb1af3e` | none |

\* Template uses `SendGridDynamicMail` base class — sends branded template via SendGrid in production, falls back to Blade view locally.

## Standalone Mailables

All standalone mailables listed below extend `SendGridDynamicMail` (branded SendGrid template in production, Blade fallback locally/test).

| Mailable | Receiver | Trigger | SendGrid ID | Blade View | BCC Team? |
|---|---|---|---|---|---|
| `AdminNewApplicationMail` | admin | application submitted | `d-15f3364a4b4f493a9caa6e7031d96685` | `emails.admin-new-application` | No (admin-facing) |
| `AdminGroupBookingCreatedMail` | admin | `BookingGroupCreated` | `d-de8ddf0050cf4ec29caee8c210c6263f` (shared with single) | `emails.admin-group-booking-created` | No (admin-facing) |
| `AdminBookingCancelledMail` | admin | booking cancelled | `d-71b39db4e170449fba2de7234e8d5961` | `emails.admin-booking-cancelled` | No (admin-facing) |
| `AdminCaregiverArchivedMail` | admin | `ArchiveLongTermInactive` | `d-6c385f3b5a5f4e5180ccee4fedc09106` | `emails.admin-caregiver-archived` | No (admin-facing) |
| `AdminCaregiverBackedOutMail` | admin | `AssignmentController@backOut` | `d-44ad02d6c50343709900263b8d1c3b28` | `emails.admin-caregiver-backed-out` | No (admin-facing) |
| `AdminPaymentFailedMail` | admin | Stripe charge failure | `d-ffd19317faa641ac83e898f159ed7692` | `emails.admin-payment-failed` | No (admin-facing) |
| `ClientGroupBookingCreatedMail` | client | `BookingGroupCreated` | `d-53f1d52866924c3096bd0d7deae965e6` (shared with single) | `emails.client-group-booking-created` | Yes |
| `ClientBookingCancelledMail` | client | booking cancelled | `d-965c67b476c54002b0912d87f5805303` | `emails.client-booking-cancelled` | Yes |
| `ClientPaymentFailedMail` | client | payment failure | `d-7b4a3691a5f44f3392415ed14143cdd5` | — | No |
| `ApplicantConfirmationMail` | applicant | application submitted | `d-46445a000ef24dc690dc7eda3f438f1e` | `emails.applicant-confirmation` | Yes |
| `ApplicantDeclinedMail` | applicant | admin declines | `d-fbfcb36f2d69474eb764f82ad1dac84b` | `emails.application-declined` | Yes |
| `ApplicantFinalReminderMail` | applicant | `NudgeIncompleteApplications` (7d) | `d-33fa38edec7f4b2cb39b78d2ab652c9f` | `emails.final-reminder` | Yes |
| `ApplicantHiredMail` | applicant | admin hires | `d-4ff3875d2aab4fd293662eabb8aa6e77` | `emails.application-hired` | Yes |
| `ApplicantPendingReferencesMail` | applicant | `NudgePendingReferences` (3d+) | `d-eaa36d01d9e948849e15e2afadb8b71d` | `emails.applicant-pending-references` | Yes |
| `ApplicantResumeApplicationMail` | applicant | `NudgeIncompleteApplications` (48h) | `d-4cf619b4ce1e4b62b1508de56f6a1069` | `emails.resume-application` | Yes |
| `ReferenceRequestMail` | reference contact | application submitted / resend | `d-0533743f636141fe880c9bbe8097b084` | `emails.reference-request` | Yes |
| `ReferenceReminderMail` | reference contact | `NudgePendingReferences` (2-5d) | `d-0ca264e3ff9140f5be97765b372f6846` | `emails.reference-reminder` | Yes |
| `ReferenceFinalReminderMail` | reference contact | `NudgePendingReferences` (5d+) | `d-5edba720ef7b4aec8a8b3d70a4dc2cbd` | `emails.reference-final-reminder` | Yes |
| `ReferenceCompletedMail` | admin | reference submitted | `d-622707caa2b54456a6921f032fb1af3e` | `emails.reference-completed` | No (admin-facing) |
| `PasswordResetMail` | user | password reset requested | `d-ed180932c2904c028fc5df6bd90a0c69` | `emails.password-reset` | No (security) |
| `TrustlineReimbursementEarnedMail` | caregiver | payout threshold met | `d-trustline-reimbursement-earned` | — | No |
| `CaregiverBookingInvitationMail` | caregiver | `BookingInvitationSent` | `d-aac404a830334ae884098a75cb32caca` | `emails.booking-notification` | No |
| `BookingReviewReminderMail` | client | `SendReviewReminders` | `d-ed4e08ffb28648f4aee1485389653810` | `emails.review-reminder` | No |
| `CaregiverOnHoldCheckinMail` | caregiver | `CheckInOnHoldCaregivers` | `d-4de573218a71436d849f2c67a6d9e6e7` | `emails.caregiver-on-hold-checkin` | Yes |
| `CaregiverArchiveWarningMail` | caregiver | `ArchiveLongTermInactive` | `d-6a7ef80cc2b74e978c38d6c1ea897846` | `emails.caregiver-archive-warning` | Yes |
| `CaregiverBookingCancelledMail` | caregiver | booking cancelled | `d-286f15d2045541babef403f5fde86cef` | `emails.caregiver-booking-cancelled` | Yes |

Additional mailables that use `SendGrid` trait directly (no Blade fallback, `Mailable` + `SendGrid` only):
- `AdminBookingCreatedMail` — `d-de8ddf0050cf4ec29caee8c210c6263f`
- `AdminBookingAcceptedMail` — `d-636bec70c9e74cf8a708086896e84539`
- `ClientBookingCreatedMail` — `d-53f1d52866924c3096bd0d7deae965e6`
- `ClientBookingAcceptedMail` — `d-3cdfa4a1b83746009e07db0f0261afa4`
- `ClientReceiptMail` — `d-ade9c101da2d40d78a2742577e6d3efe`
- `ClientPaymentRequiredMail` — `d-9f4b24bb450140d9bd2c1628b705fbc1`
- `CaregiverBookingAcceptedMail` — `d-3f3ed05c7e5f4c40bcdffbc967ef8bdb`
- `CaregiverBookingReminderMail` — `d-c141f95e479746dd8af8d96aa1c64067`

### Team BCC Behavior

Every mailable that extends `SendGridDynamicMail` inherits `shouldBccTeam()` → `true` from the base class, which BCCs `config('mail.team_bcc')` when set. Override `shouldBccTeam()` → `false` to opt out:

- **Opted out (no BCC):** All admin-facing (addressed to team directly), `PasswordResetMail` (security), `CaregiverBookingInvitationMail` (caregiver-facing), `BookingReviewReminderMail` (client review), `ClientPaymentFailedMail`, `TrustlineReimbursementEarnedMail`
- **Default (BCC active):** Applicant, reference, caregiver lifecycle, and remaining client-facing emails

## Event → Listener → Notification Map

| Event | Listener | Notification(s) |
|---|---|---|
| `BookingCreated` | `SendBookingCreatedNotifications` | `BookingCreatedNotification`, `ClientPaymentRequiredNotification` |
| `BookingAccepted` | `SendBookingAcceptedNotifications` | `BookingAcceptedNotification` |
| `BookingCancelled` | `SendBookingCancelledNotifications` | `BookingCancelledNotification` |
| `BookingGroupCreated` | `SendBookingGroupCreatedNotifications` | `ClientGroupBookingCreatedNotification`, `AdminGroupBookingCreatedNotification`, `ClientPaymentRequiredNotification` |
| `BookingInvitationSent` | `SendBookingInvitationNotifications` | `BookingInvitationNotification` |
| `BookingReminderTriggered` | `SendBookingReminderNotifications` | `BookingReminderNotification` |
| `BookingReceipt` | `SendBookingReceiptNotification` | `BookingReceiptNotification` |
| `GuestAccountSetup` | `SendGuestAccountSetupNotification` | `GuestAccountSetupNotification` |

## Scheduled Commands That Send Notifications

| Command | Frequency | What It Sends |
|---|---|---|
| `app:send-booking-reminders` | hourly | `BookingReminderTriggered` → `BookingReminderNotification` |
| `app:send-payment-sms-reminders` | hourly | `ClientPaymentSmsReminderNotification` (SMS) |
| `app:send-review-reminders` | every 6h | `BookingReviewReminderNotification` |
| `app:nudge-incomplete-applications` | every 6h | `ApplicantResumeApplicationMail`, `ApplicantFinalReminderMail` |
| `app:nudge-pending-references` | daily 09:00 | `ReferenceReminderMail`, `ReferenceFinalReminderMail`, `ApplicantPendingReferencesMail` |
| `app:check-in-on-hold-caregivers` | daily 10:00 | `CaregiverOnHoldCheckinMail` |
| `app:archive-long-term-inactive` | daily | `CaregiverArchiveWarningMail` → 14d later: `AdminCaregiverArchivedNotification` |

### Dev/Test Commands

| Command | Purpose |
|---|---|
| `test:email` | Send a plain test email or `SendGridTemplate` proof-of-concept via specified mailer |
| `test:sendgrid-template` | Send a specific SendGrid dynamic template with `$booking->toEmailData()` variables |
| `test:sms` | Send a test SMS message |

## SendGrid Template IDs

| Template ID | Used By |
|---|---|
| `d-aac404a830334ae884098a75cb32caca` | `CaregiverBookingInvitationMail` — New Job Available (caregiver) |
| `d-ed4e08ffb28648f4aee1485389653810` | `BookingReviewReminderMail` — Review Reminder (client) |
| `d-71b39db4e170449fba2de7234e8d5961` | `AdminBookingCancelledMail` — Booking Cancelled (admin) |
| `d-286f15d2045541babef403f5fde86cef` | `CaregiverBookingCancelledMail` — Job Cancelled (caregiver) |
| `d-965c67b476c54002b0912d87f5805303` | `ClientBookingCancelledMail` — Booking Cancelled (client) |
| `d-53f1d52866924c3096bd0d7deae965e6` | `ClientBookingCreatedMail`, `ClientGroupBookingCreatedMail` |
| `d-de8ddf0050cf4ec29caee8c210c6263f` | `AdminBookingCreatedMail`, `AdminGroupBookingCreatedMail` |
| `d-636bec70c9e74cf8a708086896e84539` | `AdminBookingAcceptedMail` |
| `d-3f3ed05c7e5f4c40bcdffbc967ef8bdb` | `CaregiverBookingAcceptedMail` |
| `d-3cdfa4a1b83746009e07db0f0261afa4` | `ClientBookingAcceptedMail` |
| `d-c141f95e479746dd8af8d96aa1c64067` | `CaregiverBookingReminderMail` |
| `d-ade9c101da2d40d78a2742577e6d3efe` | `ClientReceiptMail` |
| `d-9f4b24bb450140d9bd2c1628b705fbc1` | `ClientPaymentRequiredMail` |
| `d-15f3364a4b4f493a9caa6e7031d96685` | `AdminNewApplicationMail` |
| `d-46445a000ef24dc690dc7eda3f438f1e` | `ApplicantConfirmationMail` |
| `d-4cf619b4ce1e4b62b1508de56f6a1069` | `ApplicantResumeApplicationMail` |
| `d-33fa38edec7f4b2cb39b78d2ab652c9f` | `ApplicantFinalReminderMail` |
| `d-eaa36d01d9e948849e15e2afadb8b71d` | `ApplicantPendingReferencesMail` |
| `d-4ff3875d2aab4fd293662eabb8aa6e77` | `ApplicantHiredMail` |
| `d-fbfcb36f2d69474eb764f82ad1dac84b` | `ApplicantDeclinedMail` |
| `d-0533743f636141fe880c9bbe8097b084` | `ReferenceRequestMail` |
| `d-0ca264e3ff9140f5be97765b372f6846` | `ReferenceReminderMail` |
| `d-5edba720ef7b4aec8a8b3d70a4dc2cbd` | `ReferenceFinalReminderMail` |
| `d-622707caa2b54456a6921f032fb1af3e` | `ReferenceCompletedMail` |
| `d-ffd19317faa641ac83e898f159ed7692` | `AdminPaymentFailedMail` |
| `d-6c385f3b5a5f4e5180ccee4fedc09106` | `AdminCaregiverArchivedMail` |
| `d-44ad02d6c50343709900263b8d1c3b28` | `AdminCaregiverBackedOutMail` |
| `d-6a7ef80cc2b74e978c38d6c1ea897846` | `CaregiverArchiveWarningMail` |
| `d-4de573218a71436d849f2c67a6d9e6e7` | `CaregiverOnHoldCheckinMail` |
| `d-7b4a3691a5f44f3392415ed14143cdd5` | `ClientPaymentFailedMail` |
| `d-ed180932c2904c028fc5df6bd90a0c69` | `PasswordResetMail` |

## Multi-Day Booking Support

| Component | Single Booking | Multi-Day Group |
|---|---|---|
| Email — `booking-notification.blade.php` | Shows single start/end datetime | Shows date table with all dates/times |
| Email — `ClientBookingCreatedMail` / `ClientGroupBookingCreatedMail` | Shared template `d-53f1...` | Shared template `d-53f1...` (data carries `dates` array for multi-day) |
| Email — `AdminBookingCreatedMail` / `AdminGroupBookingCreatedMail` | Shared template `d-de8d...` | Shared template `d-de8d...` (data carries `dates` array for multi-day) |
| SMS — `BookingInvitationNotification::toSms()` | "New job – Mon 6/5, 9:00am–5:00pm" | "New job – Mon 6/5–Wed 6/7, 9:00am–5:00pm daily" (or "overnight") |
| WebPush — `BookingInvitationNotification::toWebPush()` | "clientName: 6/5/26 9:00am–5:00pm" | "clientName: multi-day job, 6/5–6/7" |
| `ClientPaymentRequiredMail` | Single date string | "Monday, June 5 (+2 more)" |

## SendGrid Click Tracking Opt-Out

All email anchor tags carry `clicktracking=off` as an HTML attribute to opt out of SendGrid click tracking. Applied in:

- `resources/views/vendor/mail/html/button.blade.php` — notification action buttons (password reset, email verification)
- All anchor tags across `resources/views/emails/*.blade.php` templates

## Totals

- **17** notification classes
- **34** mailable classes (26 extend `SendGridDynamicMail`, 8 use `SendGrid` trait directly)
- **8** with dedicated tests, **9** untested notifications
- **30** SendGrid template IDs, **14** Blade email views
- **4** notifications use SMS (`SmsChannel` → Twilio)
- **1** database-only notification (`GuestAccountSetupNotification`)
- **9** mailables opt out of team BCC (`shouldBccTeam` → `false`)
