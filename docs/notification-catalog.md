# Notification Catalog

## Notifications (`app/Notifications/`)

| Class | Receiver | Trigger | Channels | Template | Test |
|---|---|---|---|---|---|
| AdminCaregiverArchivedNotification | admin | `ArchiveLongTermInactive` command | email | Blade `emails.admin-caregiver-archived` | none |
| AdminCaregiverBackedOutNotification | admin | `AssignmentController@backOut` | email | Blade `emails.admin-caregiver-backed-out` | none |
| AdminGroupBookingCreatedNotification | admin | `BookingGroupCreated` event | email | Blade `emails.admin-group-booking-created` | none |
| AdminNewApplicationNotification | admin | application submitted | email | Blade `emails.admin-new-application` | none |
| BookingAcceptedNotification | client, caregiver, admin | `BookingAccepted` event | email, db, sms(client) | SendGrid `d-636...`, `d-3f3...`, `d-3cd...` | ✅ `BookingAcceptedNotificationTest.php` |
| BookingCancelledNotification | client, caregiver, admin | `BookingCancelled` event | email | SendGrid `d-2a5...`, `d-97b...`, `d-34a...` | none |
| BookingCreatedNotification | client, admin | `BookingCreated` event | email, db | SendGrid `d-53f...` / `d-de8...` | ✅ `BookingCreatedNotificationTest.php` |
| BookingInvitationNotification | caregiver | `BookingInvitationSent` event | email, db, sms, push | Blade `emails.booking-notification` | ✅ `BookingInvitationNotificationTest.php` |
| BookingReceiptNotification | client | `BookingReceipt` event | email, db | SendGrid `d-ade...` | ✅ `BookingReceiptNotificationTest.php` |
| BookingReminderNotification | caregiver | `SendBookingReminders` command | email, db | SendGrid `d-c14...` | ✅ `BookingNotificationTest.php` |
| BookingReviewReminderNotification | client | `SendReviewReminders` command | email, sms | Blade `emails.review-reminder` | ✅ `BookingReviewReminderTest.php` |
| ClientGroupBookingCreatedNotification | client | `BookingGroupCreated` event | email, db | SendGrid `d-53f...` | none |
| ClientPaymentRequiredNotification | client | `BookingCreated` / `BookingGroupCreated` | email, db | SendGrid `d-9f4...` | none |
| ClientPaymentSmsReminderNotification | client | `SendPaymentSmsReminders` command | sms | n/a (plain text) | none |
| GuestAccountSetupNotification | client | `GuestAccountSetup` event | db only | n/a | ✅ `GuestAccountSetupNotificationTest.php` |
| PaymentFailedNotification | client, admin | Stripe charge failure | email, db | Blade `emails.admin-payment-failed` | ✅ `PaymentFailureNotificationTest.php` |
| ReferenceCompletedNotification | admin | reference submitted | email | Blade `emails.reference-completed` | none |

## Standalone Mailables (`Mail::to()`, no notification wrapper)

| Mailable | Receiver | Trigger | Template |
|---|---|---|---|
| ApplicantConfirmationMail | applicant | application submitted | Blade `emails.applicant-confirmation` |
| ApplicantDeclinedMail | applicant | admin declines | Blade `emails.application-declined` |
| ApplicantFinalReminderMail | applicant | `NudgeIncompleteApplications` (7d) | Blade `emails.final-reminder` |
| ApplicantHiredMail | applicant | admin hires | Blade `emails.application-hired` |
| ApplicantPendingReferencesMail | applicant | `NudgePendingReferences` (3d+) | Blade `emails.applicant-pending-references` |
| ApplicantResumeApplicationMail | applicant | `NudgeIncompleteApplications` (48h) | Blade `emails.resume-application` |
| CaregiverArchiveWarningMail | caregiver | `ArchiveLongTermInactive` (166d) | Blade `emails.caregiver-archive-warning` |
| CaregiverOnHoldCheckinMail | caregiver | `CheckInOnHoldCaregivers` (30/45/60d) | Blade `emails.caregiver-on-hold-checkin` |
| ReferenceRequestMail | reference contact | application submitted / resend | Blade `emails.reference-request` |
| ReferenceReminderMail | reference contact | `NudgePendingReferences` (2-5d) | Blade `emails.reference-reminder` |
| ReferenceFinalReminderMail | reference contact | `NudgePendingReferences` (5d+) | Blade `emails.reference-final-reminder` |

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

## SendGrid Template IDs

| Template ID | Used By |
|---|---|
| `d-53f1d52866924c3096bd0d7deae965e6` | `ClientBookingCreatedMail`, `ClientGroupBookingCreatedMail` |
| `d-de8ddf0050cf4ec29caee8c210c6263f` | `AdminBookingCreatedMail` |
| `d-636bec70c9e74cf8a708086896e84539` | `AdminBookingAcceptedMail` |
| `d-3f3ed05c7e5f4c40bcdffbc967ef8bdb` | `CaregiverBookingAcceptedMail` |
| `d-3cdfa4a1b83746009e07db0f0261afa4` | `ClientBookingAcceptedMail` |
| `d-c141f95e479746dd8af8d96aa1c64067` | `CaregiverBookingReminderMail` |
| `d-ade9c101da2d40d78a2742577e6d3efe` | `ClientReceiptMail` |
| `d-9f4b24bb450140d9bd2c1628b705fbc1` | `ClientPaymentRequiredMail` |
| `d-2a539fde38bb46788fc96baf7fb6366b` | `CaregiverBookingCancelledMail` |
| `d-97bbdd77080441da98575c65f9bd1901` | `AdminBookingCancelledMail` |
| `d-34a42e715fa541e484c9c17030cdebbe` | `ClientBookingCancelledMail` |

## Totals

- **17** notification classes, **30** mailable classes
- **8** with dedicated tests, **9** untested notifications
- **11** SendGrid template IDs, **10** Blade email views
- **4** notifications use SMS (`SmsChannel` → Twilio)
- **1** database-only notification (`GuestAccountSetupNotification`)
- **11** standalone mailables sent directly via `Mail::to()` (no notification wrapper)
