Here’s a **developer-friendly breakdown** of the spec, rewritten as something you can actually build against in a Laravel app. I’ve removed fluff and reorganized it into **modules, flows, data, and rules**.

---

# 🧩 1. Core Concept

Everything revolves around a **`caregivers`** table (single source of truth).

Four subsystems write into it:

| System              | Purpose             | Visibility              |
| ------------------- | ------------------- | ----------------------- |
| A. Application      | Intake data         | Caregiver + Admin       |
| B. References       | External validation | Admin only              |
| C. Reviews          | Client feedback     | Caregiver (own) + Admin |
| D. Internal Rating  | Staff scoring       | Admin only              |
| E. Background Check | Compliance          | Admin only              |

---

# 🏗️ 2. High-Level Architecture (Laravel)

### Likely Structure

- Models:
    - `Caregiver`
    - `CaregiverApplication`
    - `ReferenceRequest`, `ReferenceResponse`
    - `ClientReview`
    - `CaregiverInternalRating`
    - `BackgroundCheck`
    - `Certification`

- Services:
    - `ReferenceService`
    - `ReviewService`
    - `BackgroundCheckService`
    - `RatingService`

- Jobs/Queues:
    - Send reference emails
    - Reminder emails (Day 7, 14)
    - Review request (post-job)
    - Background check webhook handling

---

# 📋 3. System A — Application (Public, No Auth)

### Flow

1. Multi-step wizard (8 steps)
2. Save **per step (draft support)**
3. On submit:
    - Create `caregivers` record (status = `applicant`)
    - Save application snapshot
    - Generate agreement PDFs
    - Trigger references (System B)
    - Send emails

---

## Key Implementation Notes

### ❗ Important Rules

- Email must be **unique**
- References:
    - Cannot include applicant email
    - No duplicate emails

- Structured fields > free text (availability, age groups)

---

## 🧱 Suggested Tables

### `caregivers`

```php
id
first_name
last_name
email (unique)
phone
dob
status
photo_path
```

### `caregiver_applications`

```php
id
caregiver_id
data (JSON snapshot)
submitted_at
```

### `caregiver_experiences`

```php
id
caregiver_id
start_month
end_month
role
organization
description
ages_served (JSON)
```

### `caregiver_agreements`

```php
id
caregiver_id
type (verification/agreement)
pdf_path
signed_at
```

---

# 📩 4. System B — References

## Flow

- On application submit:
    - Create **4 `reference_requests`**
    - Send email with **tokenized link**

---

## Tokenized Access Pattern

```php
reference_requests:
- token (uuid)
- expires_at (30 days)
- completed_at
```

Route example:

```php
Route::get('/reference/{token}', ...)
```

---

## Reference Form Output

### `reference_responses`

```php
id
reference_request_id
ratings (JSON or columns)
strengths
concerns
would_trust
relationship
duration
```

---

## Admin Logic

- Show:
    - Completion count (e.g. 3/4)
    - Average rating
    - Flag if any rating ≤ 2
    - Cross-check mismatch vs application

---

## Scheduled Jobs

| Day | Action         |
| --- | -------------- |
| 0   | Send email     |
| 7   | Reminder       |
| 14  | Final reminder |
| 30  | Expire         |

---

# ⭐ 5. System C — Client Reviews

## Trigger

- After job checkout (+2 hours)

---

## `client_reviews`

```php
id
caregiver_id
booking_id
overall_rating
punctuality
communication
engagement
would_book_again
review_text
private_note
```

---

## Visibility Rules (IMPORTANT)

| Role      | Access           |
| --------- | ---------------- |
| Admin     | Everything       |
| Caregiver | Own reviews only |
| Client    | ❌ No access     |

---

## Aggregation

```php
avg_rating = avg(overall_rating)
```

Display:

```
4.7 ⭐ (23 reviews)
```

---

## Extra Metrics (Admin Only)

- Acceptance rate
- Response time
- Jobs completed
- Jobs this month/quarter
- Days since last job

---

# 🧠 6. System D — Internal Rating (Admin Only)

## Components

| Score         | Type            |
| ------------- | --------------- |
| Interview     | Static          |
| Communication | Editable        |
| Reliability   | Auto + editable |

---

## Reliability Auto Logic

```text
start = 5.0
-0.5 per back-out
+0.1 per 10 completed jobs (max 5.0)
```

---

## Composite Score

```text
= (Interview * 0.2)
+ (Communication * 0.3)
+ (Reliability * 0.5)
```

---

## Tables

### `caregiver_internal_ratings`

```php
caregiver_id
interview_score
communication_score
reliability_score
composite_score
```

### `caregiver_rating_history`

```php
caregiver_id
field
old_value
new_value
changed_by
notes
```

---

# 🔐 7. Background Check (S2Verify)

## Flow

### Admin OR Caregiver triggers:

1. Confirm action
2. Send data to API
3. Handle webhook response
4. Update status

---

## Status Enum

```text
not_initiated
pending
clear
review_required
failed
```

---

## Critical Security Rule ⚠️

- **SSN must NOT be stored**
- Use:
    - One-time token
    - Direct submission to S2Verify

---

## `background_checks`

```php
caregiver_id
status
initiated_at
expires_at
report_url
```

---

# 🧑‍💼 8. Admin Dashboard (Core UI)

## Profile Header

- Name + photo
- Status
- Internal score ⭐
- Review score ⭐
- Job stats
- Background check status

---

## Tabs

| Tab             | Data             |
| --------------- | ---------------- |
| Application     | Snapshot         |
| References      | Responses        |
| Reviews         | Client feedback  |
| Internal Rating | Scores           |
| Engagement      | Metrics          |
| Compliance      | BG check + certs |
| Job History     | All jobs         |
| Notes           | Admin notes      |

---

# 🔄 9. Caregiver Status Lifecycle

```text
Applicant
→ Under Review
→ Interview Scheduled
→ Background Check
→ Hired
→ Active
→ Inactive / Suspended / Terminated
```

---

# 🗃️ 10. Key Relationships

```text
Caregiver
 ├── Application (1:1)
 ├── Experiences (1:N)
 ├── References (1:N)
 │    ├── Requests
 │    └── Responses
 ├── Reviews (1:N)
 ├── Internal Rating (1:1)
 ├── Background Checks (1:N)
 ├── Certifications (1:N)
```

---

# 🚀 11. Build Priority (Recommended Order)

### Phase 1 (MVP)

- Application wizard
- Caregiver model
- Admin view (basic)

### Phase 2

- Reference system (token + email)

### Phase 3

- Internal rating

### Phase 4

- Client reviews

### Phase 5

- Background check integration

---

# ⚠️ 12. Critical Gotchas

- ❌ Do NOT expose:
    - References
    - Internal ratings
    - Background check details

- ✅ Must implement:
    - Tokenized links (references)
    - Draft saving (application)
    - Structured fields (filtering later)

- ⚠️ Performance:
    - Precompute aggregates (ratings, counts)
    - Don’t calculate everything on the fly

---
