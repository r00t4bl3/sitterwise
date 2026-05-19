# SITTERWISE Caregiver Platform — Unified Specification (Merged)

**Source documents merged:**
1. *SITTERWISE Caregiver Profile System Specification v1.0* (PDF)
2. *SITTERWISE — Caregiver Platform Updated Spec* (DOCX)

> Rule applied during merge: when conflicts exist, the updated DOCX specification overrides the original PDF specification.

---

# 1. Platform Overview

The Sitterwise platform manages the full caregiver lifecycle from application through onboarding, compliance, job participation, reviews, and internal evaluation.

The platform includes:

- System A — Caregiver Application Form
- System B — Automated Reference Collection
- System C — Client Reviews & Star Ratings
- System D — Internal Staff Rating
- S2Verify Background Check Integration
- W-2 onboarding via OnPay
- Assignment-based caregiver reliability tracking
- Compliance and inactivity automation
- Admin dashboard queues and alerts

Visibility principles:

| Data Type | Admin | Caregiver | Client |
|---|---|---|---|
| Application data | Yes | Own only | No |
| Reference responses | Yes | No | No |
| Internal ratings | Yes | No | No |
| Client reviews | Yes | Own only | No |
| Background check details | Yes | No | No |

---

# 2. Employment Model (Updated)

Sitterwise is transitioning from:
- Independent contractors + Stripe
to:
- W-2 employees + OnPay

The new platform must assume:
- W-2 employment from day one
- Payroll through OnPay
- No Stripe onboarding replication

Agreement language and onboarding flow must reflect W-2 employment.

---

# 3. System A — Caregiver Application Form

## 3.1 Application Flow

Multi-step wizard with:
- Progress indicator
- Sequential navigation
- Back/Next buttons
- Per-step autosave
- Recoverable partial completion

### Steps

1. Sponsor & Applicant Info
2. Position, Availability & Education
3. Employment & Childcare Experience
4. Screening Questions
5. References
6. Location Preferences & Age Groups
7. Qualifications, Activities & Bio
8. Agreements & Signature

---

## 3.2 Step 1 — Sponsor & Applicant Information

### Sponsor Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| First Name | Text | Yes | |
| Last Name | Text | Yes | |
| Email | Email | Yes | Used for reference request |
| Phone | Phone | No | Follow-up if email bounces |
| Relationship | Text | No | Relationship to applicant |

Sponsor acts as Reference #1.

### Applicant Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| First Name | Text | Yes | |
| Last Name | Text | Yes | |
| Address | Structured Address | Yes | Store street/city/state/zip |
| Phone | Phone | Yes | |
| Email | Email | Yes | Unique identifier |
| Date of Birth | Date | Yes | Needed for S2Verify |
| Profile Photo | Upload | No | Admin-only until approved |

Photo recommendation:
- 5MB max
- Auto-resize to 1200px longest edge

---

## 3.3 Step 2 — Position, Availability & Education

### Positions

- On-Call Babysitting
- On-Call Petsitting
- Group Events

At least one required.

### Availability

Structured checkbox system:

- Weekday Mornings
- Weekday Afternoons
- Weekday Evenings
- Weekends
- Overnights

Additional field:
- Availability Notes

### Education

| Field | Notes |
|---|---|
| Highest Level | Dropdown |
| High School Name | Optional |
| HS Graduation Year | Optional |
| College Name | Conditional |
| College Graduation Year | Conditional |
| Degree / Major | Conditional |

---

## 3.4 Step 3 — Employment & Childcare Experience

### Current Employment

| Field | Notes |
|---|---|
| Employment Status | Full-time / Part-time / No / Student |
| Current Employer | Conditional |

### Childcare Experience Entries

Up to 3 entries.

Experience #1 required.

Fields:
- Start month/year
- End month/year or Present
- Role title
- Family/org name
- Description
- Ages served

Ages served options:
- Infant
- Toddler
- Preschool
- School Age
- Teen

---

## 3.5 Step 4 — Screening Questions

All required unless specified otherwise.

| Field | Notes |
|---|---|
| Smoking | Yes/No |
| Alcohol use | Dropdown |
| Substance abuse history | Confidential |
| Physical/psychological limitations | Confidential |
| Allergies to dogs/cats | Dropdown |
| Visible tattoos | Yes/No |
| Authorized to work in U.S. | HARD GATE |
| Reliable vehicle | Yes/No |
| CPR/First Aid certified | Conditional uploads |
| Trustline certified | Conditional uploads |
| Languages | Optional |
| Own children | Optional |

### Work Authorization Hard Gate (Updated)

If applicant answers “No” to work authorization:
- Prevent submission entirely
- Display required legal message

### CPR Conditional Fields

If CPR certified:
- Expiration date
- CPR card upload

### Trustline Conditional Fields

If Trustline certified:
- Upload required

---

## 3.6 Step 5 — References

Applicant enters 3 references.
Combined with sponsor = 4 total.

### Per-reference fields

- First name
- Last name
- Email
- Phone
- Relationship
- Duration known

### Validation

Prevent:
- Applicant email as reference
- Duplicate reference emails
- Sponsor duplicate

If sponsor duplicated:
> “This person is already listed as your sponsor and will receive a reference request.”

---

## 3.7 Step 6 — Location Preferences & Age Groups

### Location Preferences

- North County
- South/East County
- Flexible

### Age Group Comfort

Self-attestation based checkboxes:

- Babies
- Toddlers
- Preschool
- School Age

Attestation content must be system-managed, not hardcoded.

---

## 3.8 Step 7 — Qualifications, Activities & Bio

### Qualifications

- Special Needs
- Companion Care
- Sick Care
- Work-From-Home Parents
- Driving
- Dogsitting
- Catsitting
- Swimming
- Overnight Care

All use self-attestation text.

### Activities

Field:
- “Things you bring to a job”

### Bio

| Field | Notes |
|---|---|
| Bio | 200–500 words |
| Interests/Hobbies | Optional |

AI-assisted bio generation remains supported.

---

## 3.9 Step 8 — Agreements & Signature

### Agreement 1 — Statement of Verification

Keep original verification agreement.

### Agreement 2 — Conditional Offer Acknowledgment (REPLACES IC AGREEMENT)

Use updated W-2 language:
- Application is not employment offer
- Offer letter issued separately via OnPay
- W-2 employment acknowledgment
- Payroll through OnPay
- CPR/Trustline obligations

### Signature Requirements

Per agreement:
- Typed signature
- Confirmation checkbox
- Auto-populated date

Typed signature must match applicant name.

---

## 3.10 Submission Actions

On submit:
- Create caregiver status = Applicant
- Save application snapshot
- Save agreement PDFs
- Trigger references
- Send applicant confirmation email
- Send admin notification

---

# 4. Application Recovery & Nudges

## 4.1 Incomplete Application Nudges

| Timing | Action |
|---|---|
| 48 hours | Resume application email |
| 7 days | Final reminder |
| 14 days | Auto-archive |
| 90 days after archive | Delete data |

Resume link returns applicant to exact step.

---

# 5. System B — Automated Reference Collection

## 5.1 Reference Email

- Sent automatically
- SendGrid-based
- Tokenized link
- 30-day expiration removed in practice by stalled logic
- Warm/professional tone

Reference should not see applicant-entered relationship/duration.

---

## 5.2 Reference Form Fields

Fields include:
- Name
- Email
- Phone
- Relationship
- Length known
- Child interaction experience
- Ratings
- Open-ended feedback
- Trust question

### Rating Categories

1–5 scale:
- Reliability
- Trustworthiness
- Maturity & Judgment
- Communication
- Warmth with Children
- Overall Recommendation

### Open-ended

- Strengths
- Concerns
- Additional comments

---

## 5.3 Reference Visibility

| Viewer | Visibility |
|---|---|
| Admin | Full |
| Caregiver | None |
| Client | None |

---

## 5.4 Reference Dashboard Features

Admin profile shows:
- Completion tracker
- Average score
- Individual details
- Low-score flags
- Cross-check mismatch alerts

---

## 5.5 Updated Reference Nudges

### Reference-side reminders

| Day | Action |
|---|---|
| Day 2 | Reminder |
| Day 5 | Final reminder |

### Applicant-side reminders

| Day | Action |
|---|---|
| Day 3 | Prompt applicant to nudge reference |
| Day 7 | Stronger reminder |
| Day 14 | Move to “Stalled — references incomplete” |

### Self-Service Reference Status View

Applicant can:
- See who responded
- Replace non-responders

---

# 6. Caregiver Lifecycle

## 6.1 Statuses

| Status | Meaning |
|---|---|
| Applicant | Submitted |
| Stalled — references incomplete | New |
| Under Review | Reviewing |
| Interview Scheduled | Interview scheduled |
| Background Check | BG initiated |
| Hired / Onboarding | Completing onboarding |
| Inactive — onboarding incomplete | Auto-reverted after 30 days |
| Active | Fully onboarded |
| Suspended — Trustline overdue | Missed Trustline deadline |
| On Hold | Caregiver pause |
| Inactive | No jobs / no response |
| Suspended | Admin hold |
| Terminated | Removed |

---

# 7. Post-Hire Onboarding

## 7.1 Onboarding Checklist

Required before first job:

1. OnPay setup complete
2. Background check clear
3. CPR uploaded and valid
4. Trustline submitted
5. Dress code acknowledged
6. Training quiz passed

---

## 7.2 OnPay Handoff

No API integration required.

Flow:
- Admin moves caregiver to Hired / Onboarding
- Platform emails admin to send OnPay invite
- Admin manually confirms completion

---

## 7.3 Trustline Rules

### Requirements

- Submit within 7 days of Active status
- Caregiver pays upfront
- Sitterwise reimburses after:
  - 10 completed jobs
  - Within first 6 months

### Auto Actions

If no submission by day 7:
- Auto-status:
  - Suspended — Trustline overdue

Auto-reactivate after submission confirmed.

### Reimbursement Tracking

Fields:
- Submission date
- Approval date
- Cost paid
- Reimbursement status
- Progress toward 10 jobs
- Days remaining
- Date reimbursed

Statuses:
- Not Yet Eligible
- Eligible
- Paid
- Forfeited

### Forfeiture

If 6 months pass before 10 jobs:
- Mark Forfeited
- Send email

---

## 7.4 CPR/First Aid

- Self-funded
- Track expiration
- Renewal reminders
- No reimbursement

---

## 7.5 Onboarding Nudges

| Timing | Action |
|---|---|
| 48 hours | Reminder |
| Day 7 | Reminder |
| Day 14 | Reminder + admin alert |
| Day 30 | Auto-revert to Inactive — onboarding incomplete |

### Trustline-specific cadence

| Day | Action |
|---|---|
| Day 0 | Email + SMS |
| Day 2 | Reminder |
| Day 4 | Strong reminder |
| Day 6 | Final reminder + admin alert |
| Day 7 | Auto-suspend |

---

# 8. System C — Client Reviews & Ratings

## 8.1 Review Trigger

- Sent 2 hours after checkout
- Email primary
- SMS after 48 hours if no response
- Link active 14 days

---

## 8.2 Review Form

Fields:
- Overall rating
- Punctuality
- Communication
- Engagement with children
- Would book again
- Written review
- Private note to Sitterwise

---

## 8.3 Visibility

| Viewer | Visibility |
|---|---|
| Admin | Full |
| Caregiver | Own only |
| Client | None |

Private notes are admin-only.

---

## 8.4 Aggregate Rating Logic

Display:
- Star average
- Review count

Sub-ratings visible:
- Admin
- Caregiver self-view

---

## 8.5 Admin Performance Dashboard

Widgets:
- Overall average
- Trend chart
- Would-book-again %
- Top-rated caregivers
- Declining ratings
- Review response rate

---

# 9. Assignment-Based Job Tracking (NEW)

## 9.1 Core Principle

Back-outs and reliability belong to caregiver assignments, not job statuses.

Jobs table remains simple.
Reliability metrics derive from caregiver_assignments table.

---

## 9.2 caregiver_assignments Table

Suggested columns:
- id
- caregiver_id
- job_id
- assigned_at
- resolution
- resolution_at
- resolution_note
- late_arrival_flag
- late_arrival_note
- excused_by
- excused_at

---

## 9.3 Assignment Resolution States

- Completed
- Cancelled by Sitterwise
- Reassigned (Swapped)
- Backed Out
- Backed Out (Excused)
- No-Show

---

## 9.4 Back-Out Flow

If caregiver cancels:
- Show warning
- Require reason
- Notify admin

Admin can:
- Leave as Backed Out
- Reassign
- Mark Excused

---

## 9.5 No-Shows

- Logged manually by admin
- Immediate dashboard alert
- Recommended termination review

---

## 9.6 Late Arrivals

Late arrival:
- Flag on assignment
- Not assignment resolution

Three late arrivals in 60 days:
- Auto-flag for admin review

---

# 10. Job Engagement Metrics

Admin-only metrics:
- Total jobs completed
- Jobs offered
- Jobs accepted
- Jobs declined/ignored
- Acceptance rate
- Avg response time
- Jobs this month
- Jobs this quarter
- Last job date
- Days since last job

Caregiver sees:
- Own job count
- Own reviews only

---

# 11. System D — Internal Rating System

## 11.1 Components

- Interview evaluation
- Communication score
- Reliability score
- Composite internal score

---

## 11.2 Interview Evaluation Form (UPDATED)

Replaces original single 1–5 interview score.

### 4-heart scale

- ♥♥♥♥ Strong
- ♥♥♥ Good fit
- ♥♥ Has potential
- ♥ Concern

---

## 11.3 Nine Interview Dimensions

### Soft Skills

- Confidence/presence
- Warmth/smiles
- Experience level
- Communicativeness
- Sense of humor
- Preparedness

### Professionalism

- On time
- Prepared with ID
- In dress code

---

## 11.4 Interview Composite

- 36-point max
- Auto-calculated
- Feeds overall internal score

Display example:
> Interview score: 31 / 36 (86%)

Composite is secondary visual info.

---

## 11.5 Notes

Required notes field.

Must support:
- Strengths
- Concerns
- Context

---

## 11.6 Communication Score

Track:
- Rating
- Last updated
- Notes

---

## 11.7 Reliability Score

Track:
- Reliability rating
- Jobs accepted
- Jobs completed
- Jobs backed out
- Back-out rate
- Last back-out date
- Notes

Suggested calculation:
- Start at 5.0
- -0.5 per back-out
- +0.1 per 10 completed jobs
- Cap at 5.0

Admin override allowed.

---

## 11.8 Composite Internal Score

Suggested weighting:
- Interview: 20%
- Communication: 30%
- Reliability: 50%

Weights configurable.

---

# 12. Caregiver Milestone View (Phase 4)

Caregiver portal displays:
- Total jobs completed
- Review average (minimum 3 reviews)
- Reliability %
- Team average comparison
- Streak count
- Trustline reimbursement progress

Do not display raw back-out counts.

---

# 13. S2Verify Background Checks

## 13.1 Initiation Paths

### Admin-Initiated
Admin clicks:
- “Initiate Background Check”

### Caregiver-Initiated
Allowed after:
- Interview Scheduled or later

Must show cost-awareness language.

---

## 13.2 Shared Flow

- Initiate request
- Send data to S2Verify
- Process via webhook/polling
- Store results
- Update status
- Notify admin

SSN must:
- Go directly to S2Verify
- Never be stored locally

---

## 13.3 Status Tracking

Statuses:
- Not Initiated
- Pending
- Clear
- Review Required
- Failed

Track:
- Check date
- Expiration
- Warning alerts
- Report link

---

## 13.4 Compliance Expiration Logic

Applies to:
- Background checks
- CPR
- Trustline approval

Reminder cadence:
- 90 days
- 60 days
- 30 days
- 7 days
- Expiration day

On expiration:
- Auto-block new job offers

---

# 14. Ongoing Caregiver Maintenance

## 14.1 Inactivity Check-In

| Inactive Duration | Action |
|---|---|
| 30 days | Friendly check-in |
| 45 days | Stronger reminder |
| 60 days | Auto-Inactive |
| 180 days | Archive warning |

No response after 14 additional days:
- Archive profile

---

## 14.2 Self-Service Hold

Caregiver can:
- Pause account
- Set optional return date
- Add optional reason

Status changes to:
- On Hold

Resume:
- Self-service
- No admin intervention required

---

# 15. Admin Dashboard

## 15.1 Applications Ready to Review Queue

Criteria:
- Application complete
- ≥2 references complete OR 14 days passed
- No auto-disqualifiers

Display:
- Name
- Photo
- Sponsor
- Reference completion
- Avg reference score
- Red flags
- Schedule Interview button
- Decline button

---

## 15.2 Needs Attention Widget

Queues:
- Applications ready
- Onboarding stalled
- Compliance expiring
- Compliance expired
- Trustline overdue
- Inactive caregivers
- Stuck references
- No-shows today

Each queue links to filtered list.

---

# 16. Admin Caregiver Profile

## 16.1 Header

Display:
- Photo
- Name
- Phone
- Email
- Status
- Internal score
- Client rating
- Job count
- Acceptance rate
- Background status
- CPR expiration
- Trustline status

---

## 16.2 Tabs

- Application
- References
- Reviews
- Internal Rating
- Engagement
- Compliance
- Job History
- Notes

---

# 17. Data Model Summary

## Core Tables

- caregivers
- caregiver_applications
- caregiver_experiences
- caregiver_agreements

## Reference Tables

- reference_requests
- reference_responses

## Review Tables

- client_reviews

## Internal Rating Tables

- caregiver_internal_ratings
- caregiver_rating_history

## Compliance Tables

- background_checks
- certifications

## Assignment Tables (NEW)

- caregiver_assignments

---

# 18. Infrastructure & Integrations

## Email / SMS

- SendGrid for email
- Twilio for SMS
- Pacific time for all nudges

## File Storage

Open item:
- S3 vs local storage

Files include:
- Agreement PDFs
- Certification uploads

---

# 19. Build Priority

## Phase 1

- Application form with W-2 updates
- Stalled application nudges
- Admin caregiver list
- Profile view
- Lifecycle statuses
- Onboarding checklist
- Hold/resume feature

## Phase 2

- Reference system
- Faster nudge cadence
- Applications Ready queue
- Interview evaluation form

## Phase 3

- caregiver_assignments
- Back-outs/no-shows/late arrivals
- Compliance expiration logic

## Phase 4

- Internal ratings
- Client reviews
- Milestone view

## Phase 5

- S2Verify integration

---

# End of Unified Specification
