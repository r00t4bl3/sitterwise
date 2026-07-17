<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'lifesaver.hours_unclaimed',
                'value' => '10',
                'type' => 'int',
                'group' => 'lifesaver',
                'label' => 'Hours unclaimed before Lifesaver',
                'description' => 'Hours a booking may sit unaccepted (after the first caregiver notification round) before it is flagged a Lifesaver.',
                'sort_order' => 1,
            ],
            [
                'key' => 'lifesaver.short_notice_hours',
                'value' => '18',
                'type' => 'int',
                'group' => 'lifesaver',
                'label' => 'Short-notice hours',
                'description' => 'A booking created fewer than this many hours before its start time is flagged a Lifesaver.',
                'sort_order' => 2,
            ],
            [
                'key' => 'lifesaver.bonus',
                'value' => '15.00',
                'type' => 'float',
                'group' => 'lifesaver',
                'label' => 'Lifesaver bonus ($)',
                'description' => 'Bonus (in US dollars) added to the caregiver payout for a Lifesaver rescue job, and billed to the client. Applies to auto-detected rescues and admin-flagged Lifesaver bookings.',
                'sort_order' => 3,
            ],

            // Tier 1 — migrated from config files. Seeded from the current config
            // value so any existing env override is preserved at migration time.
            [
                'key' => 'trustline.jobs_threshold',
                'value' => (string) config('trustline.jobs_threshold', 10),
                'type' => 'int',
                'group' => 'trustline',
                'label' => 'Jobs required for Trustline reward',
                'description' => 'Completed jobs a caregiver needs before earning the Trustline reimbursement.',
                'sort_order' => 1,
            ],
            [
                'key' => 'trustline.reward_amount',
                'value' => (string) config('trustline.reward_amount', 140),
                'type' => 'int',
                'group' => 'trustline',
                'label' => 'Trustline reward amount ($)',
                'description' => 'Dollar amount reimbursed for the Trustline certification.',
                'sort_order' => 2,
            ],
            [
                'key' => 'caregiver.buffer_minutes',
                'value' => (string) config('caregiver.buffer_minutes', 60),
                'type' => 'int',
                'group' => 'caregiver',
                'label' => 'Booking buffer (minutes)',
                'description' => 'Minimum travel gap (in minutes) enforced between a caregiver\'s bookings on the same day.',
                'sort_order' => 1,
            ],

            // Tier 2 Phase A — caregiver lifecycle / retention thresholds.
            [
                'key' => 'caregiver.session_lifetime_days',
                'value' => '30',
                'type' => 'int',
                'group' => 'caregiver',
                'label' => 'Caregiver session length (days)',
                'description' => 'How long (in days) a caregiver stays logged in (rolling, reset by activity).',
                'sort_order' => 2,
            ],
            [
                'key' => 'caregiver.checkin_start_days',
                'value' => '30',
                'type' => 'int',
                'group' => 'caregiver',
                'label' => 'On-hold check-in starts (days)',
                'description' => 'Days on hold before the first check-in email goes out.',
                'sort_order' => 3,
            ],
            [
                'key' => 'caregiver.checkin_reminder_days',
                'value' => '45',
                'type' => 'int',
                'group' => 'caregiver',
                'label' => 'On-hold reminder tier (days)',
                'description' => 'Days on hold at which the check-in email escalates to the reminder tier.',
                'sort_order' => 4,
            ],
            [
                'key' => 'caregiver.checkin_final_days',
                'value' => '60',
                'type' => 'int',
                'group' => 'caregiver',
                'label' => 'On-hold final tier (days)',
                'description' => 'Days on hold at which the check-in email escalates to the final tier.',
                'sort_order' => 5,
            ],
            [
                'key' => 'caregiver.archive_warning_days',
                'value' => '166',
                'type' => 'int',
                'group' => 'caregiver',
                'label' => 'Archive warning (days on hold)',
                'description' => 'Days on hold at which a caregiver is warned their account will be archived.',
                'sort_order' => 6,
            ],
            [
                'key' => 'caregiver.archive_days',
                'value' => '180',
                'type' => 'int',
                'group' => 'caregiver',
                'label' => 'Archive to inactive (days on hold)',
                'description' => 'Days on hold at which a caregiver is archived to inactive.',
                'sort_order' => 7,
            ],
            [
                'key' => 'caregiver.late_arrival_count',
                'value' => '3',
                'type' => 'int',
                'group' => 'caregiver',
                'label' => 'Late-arrival flag threshold',
                'description' => 'Number of late arrivals within the window that flags a caregiver for admin review.',
                'sort_order' => 8,
            ],
            [
                'key' => 'caregiver.late_arrival_window_days',
                'value' => '60',
                'type' => 'int',
                'group' => 'caregiver',
                'label' => 'Late-arrival window (days)',
                'description' => 'Look-back window (in days) for counting a caregiver\'s late arrivals.',
                'sort_order' => 9,
            ],

            // Tier 2 Phase B — reference reminder cadence + applicant escalation.
            [
                'key' => 'references.first_reminder_days',
                'value' => '2',
                'type' => 'int',
                'group' => 'references',
                'label' => 'Reference first reminder (days)',
                'description' => 'Days after a reference request before the first reminder email.',
                'sort_order' => 1,
            ],
            [
                'key' => 'references.final_reminder_days',
                'value' => '5',
                'type' => 'int',
                'group' => 'references',
                'label' => 'Reference final reminder (days)',
                'description' => 'Days after a reference request before the final reminder email.',
                'sort_order' => 2,
            ],
            [
                'key' => 'references.applicant_prompt_days',
                'value' => '3',
                'type' => 'int',
                'group' => 'references',
                'label' => 'Applicant prompt (days since submission)',
                'description' => 'Days since submission before nudging the applicant about pending references.',
                'sort_order' => 3,
            ],
            [
                'key' => 'references.applicant_reminder_days',
                'value' => '7',
                'type' => 'int',
                'group' => 'references',
                'label' => 'Applicant reminder tier (days)',
                'description' => 'Days since submission at which the applicant nudge escalates.',
                'sort_order' => 4,
            ],
            [
                'key' => 'references.applicant_stale_days',
                'value' => '14',
                'type' => 'int',
                'group' => 'references',
                'label' => 'Applicant stale → inactive (days)',
                'description' => 'Days since submission with references still pending before the applicant is set inactive.',
                'sort_order' => 5,
            ],

            // Tier 2 Phase B — incomplete application lifecycle.
            [
                'key' => 'applications.needs_nudge_hours',
                'value' => '48',
                'type' => 'int',
                'group' => 'applications',
                'label' => 'Incomplete app: first nudge (hours)',
                'description' => 'Hours of inactivity before an unsubmitted application gets its first resume nudge.',
                'sort_order' => 1,
            ],
            [
                'key' => 'applications.final_nudge_days',
                'value' => '7',
                'type' => 'int',
                'group' => 'applications',
                'label' => 'Incomplete app: final nudge (days)',
                'description' => 'Days of inactivity before an already-nudged application gets the final reminder.',
                'sort_order' => 2,
            ],
            [
                'key' => 'applications.stale_days',
                'value' => '14',
                'type' => 'int',
                'group' => 'applications',
                'label' => 'Incomplete app: archive (days)',
                'description' => 'Days of inactivity before an incomplete application is archived.',
                'sort_order' => 3,
            ],
            [
                'key' => 'applications.expired_days',
                'value' => '90',
                'type' => 'int',
                'group' => 'applications',
                'label' => 'Incomplete app: delete (days)',
                'description' => 'Days of inactivity before an incomplete application is permanently deleted.',
                'sort_order' => 4,
            ],

            // Tier 2 Phase C — bookings & billing.
            [
                'key' => 'bookings.reminder_hours_before',
                'value' => '24',
                'type' => 'int',
                'group' => 'bookings',
                'label' => 'Booking reminder lead time (hours)',
                'description' => 'Hours before start that a confirmed booking\'s reminder is sent (a 1-hour window ending at this mark).',
                'sort_order' => 1,
            ],
            [
                'key' => 'bookings.reservation_hold_seconds',
                'value' => '60',
                'type' => 'int',
                'group' => 'bookings',
                'label' => 'Reservation hold (seconds)',
                'description' => 'How long (in seconds) a caregiver\'s reserve lock on a booking lasts before it expires.',
                'sort_order' => 2,
            ],
            [
                'key' => 'bookings.review_email_min_hours',
                'value' => '2',
                'type' => 'int',
                'group' => 'bookings',
                'label' => 'Review email — earliest (hours after end)',
                'description' => 'Minimum hours after a booking ends before the review-request email may go out.',
                'sort_order' => 3,
            ],
            [
                'key' => 'bookings.review_email_max_hours',
                'value' => '26',
                'type' => 'int',
                'group' => 'bookings',
                'label' => 'Review email — latest (hours after end)',
                'description' => 'Maximum hours after a booking ends that the review-request email is still sent.',
                'sort_order' => 4,
            ],
            [
                'key' => 'bookings.review_sms_min_hours',
                'value' => '48',
                'type' => 'int',
                'group' => 'bookings',
                'label' => 'Review SMS — earliest (hours after end)',
                'description' => 'Minimum hours after a booking ends before the review-request SMS may go out.',
                'sort_order' => 5,
            ],
            [
                'key' => 'bookings.review_sms_max_hours',
                'value' => '72',
                'type' => 'int',
                'group' => 'bookings',
                'label' => 'Review SMS — latest (hours after end)',
                'description' => 'Maximum hours after a booking ends that the review-request SMS is still sent.',
                'sort_order' => 6,
            ],
            [
                'key' => 'bookings.minimum_hours',
                'value' => '4',
                'type' => 'int',
                'group' => 'bookings',
                'label' => 'Minimum booking hours',
                'description' => 'Minimum billable/bookable hours. Bookings must be at least this long, and jobs are billed and paid for at least this many hours even if the actual worked time is shorter.',
                'sort_order' => 7,
            ],
            [
                'key' => 'billing.max_charge_attempts',
                'value' => '4',
                'type' => 'int',
                'group' => 'billing',
                'label' => 'Max charge attempts',
                'description' => 'Number of times a failed booking charge is retried before giving up (count).',
                'sort_order' => 1,
            ],
        ];

        foreach ($settings as $setting) {
            // firstOrCreate (not updateOrCreate): these are admin-editable, so a
            // re-seed must backfill missing keys without clobbering edited values.
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
