# Sitterwise — Database Structure

---

## Table of Contents

1. [CLIENT](#1-client)
2. [CLIENT_ADDRESS](#2-client_address)
3. [CLIENT_CHILD](#3-client_child)
4. [CLIENT_PET](#4-client_pet)
5. [HOTEL](#5-hotel)
6. [PAYMENT_METHOD](#6-payment_method)
7. [JOB_GROUP](#7-job_group)
8. [JOB](#8-job)
9. [CAREGIVER_INVITATION](#9-caregiver_invitation)
10. [CLIENT_CAREGIVER_JOB](#10-client_caregiver_job)
11. [CLIENT_FAVORITE_CAREGIVER](#11-client_favorite_caregiver)
12. [JOB_SNAPSHOT](#12-job_snapshot)
13. [CLIENT_TYPE_CHANGE](#13-client_type_change)
14. [PAYMENT](#14-payment)
15. [Relationships Summary](#15-relationships-summary)
16. [Availability Status Logic](#16-availability-status-logic)

---

## 1. CLIENT

Core profile record. One row per client, regardless of job history.

| Field                    | Type              | Required | Notes                                                                                            |
| ------------------------ | ----------------- | -------- | ------------------------------------------------------------------------------------------------ |
| `id`                     | `unsigned bigint` | Yes      | Primary key. Auto-increment                                                                      |
| `user_id`                | `unsigned bigint` | Yes      | FK → `USER.id`                                                                                   |
| `first_name`             | `string`          | Yes      |                                                                                                  |
| `last_name`              | `string`          | Yes      |                                                                                                  |
| `email`                  | `string`          | Yes      | Unique. Used as duplicate-check ID on guest checkout                                             |
| `cell_phone`             | `string`          | Yes      | International format                                                                             |
| `client_type`            | `enum`            | Yes      | `sd_resident` · `vacationer` · `invoiced`                                                        |
| `corporate_id`           | `string`          | No       | Invoiced clients only                                                                            |
| `how_did_you_hear`       | `enum`            | No       | `concierge` · `friend_family` · `google` · `returning_client` · `care_com` · `other`             |
| `sitter_preferences`     | `json`            | No       | Array: `college_aged` · `seasoned` · `baby_specialist` · `special_needs_exp` · `willing_to_swim` |
| `other_adults_in_home`   | `string`          | No       |                                                                                                  |
| `medical_info`           | `text`            | No       | Caregiver-visible before acceptance                                                              |
| `emergency_instructions` | `text`            | No       | Caregiver-visible before acceptance                                                              |
| `caregiver_notes`        | `text`            | No       | Caregiver-visible before acceptance                                                              |
| `created_at`             | `timestamp`       | Yes      |                                                                                                  |
| `updated_at`             | `timestamp`       | Yes      |                                                                                                  |

**Notes:**

- `client_type` is set by the client during job submission (SD Resident / Vacationer) or by admin only (Invoiced).
- Store a change log for `client_type` mutations and surface it in the admin view.
- Dashboard must be filterable by `client_type`.

---

## 2. CLIENT_ADDRESS

Multiple addresses per client. Powers the Address Book on the job form.

| Field           | Type              | Required | Notes                                                        |
| --------------- | ----------------- | -------- | ------------------------------------------------------------ |
| `id`            | `unsigned bigint` | Yes      | Primary key. Auto-increment                                  |
| `client_id`     | `unsigned bigint` | Yes      | FK → `CLIENT.id`                                             |
| `label`         | `string`          | No       | e.g. "Home", "Hotel", "Airbnb"                               |
| `location_type` | `enum`            | Yes      | `hotel` · `private_home` · `vacation_rental` · `event_venue` |
| `line1`         | `string`          | Yes      |                                                              |
| `line2`         | `string`          | No       |                                                              |
| `city`          | `string`          | Yes      |                                                              |
| `state`         | `string`          | Yes      |                                                              |
| `zip`           | `string`          | Yes      | Zip code linked list active                                  |
| `is_primary`    | `boolean`         | Yes      | Default address for jobs                                     |
| `created_at`    | `timestamp`       | Yes      |                                                              |
| `updated_at`    | `timestamp`       | Yes      |                                                              |

**Notes:**

- Address verification + zip code linked list active on all address fields.
- On the job form, addresses pre-fill from this table. The "Save to your Address Book?" prompt on the form controls whether a newly entered address gets inserted here at all — once a row exists, it is by definition part of the address book.

---

## 3. CLIENT_CHILD

One row per child. Replaces the legacy free-text "Kids" field.

| Field                 | Type              | Required | Notes                                             |
| --------------------- | ----------------- | -------- | ------------------------------------------------- |
| `id`                  | `unsigned bigint` | Yes      | Primary key. Auto-increment                       |
| `client_id`           | `unsigned bigint` | Yes      | FK → `CLIENT.id`                                  |
| `name`                | `string`          | No       | Optional — some clients may prefer not to provide |
| `gender`              | `string`          | No       |                                                   |
| `birth_month`         | `integer`         | No       | 1–12. Required if `birth_year` is set             |
| `birth_year`          | `integer`         | No       | Used to calculate current age dynamically         |
| `special_needs`       | `boolean`         | No       |                                                   |
| `special_needs_notes` | `text`            | No       | Caregiver-visible before acceptance               |
| `created_at`          | `timestamp`       | Yes      |                                                   |
| `updated_at`          | `timestamp`       | Yes      |                                                   |

**Notes:**

- Age is always calculated at read time from `birth_month` / `birth_year` — never stored as a static value.
- Validation: if `birth_year` is entered, `birth_month` is required.

---

## 4. CLIENT_PET

One row per pet.

| Field        | Type              | Required | Notes                               |
| ------------ | ----------------- | -------- | ----------------------------------- |
| `id`         | `unsigned bigint` | Yes      | Primary key. Auto-increment         |
| `client_id`  | `unsigned bigint` | Yes      | FK → `CLIENT.id`                    |
| `name`       | `string`          | No       | Optional                            |
| `type`       | `enum`            | Yes      | `dog` · `cat` · `other`             |
| `breed`      | `string`          | No       |                                     |
| `notes`      | `text`            | No       | Caregiver-visible before acceptance |
| `created_at` | `timestamp`       | Yes      |                                     |
| `updated_at` | `timestamp`       | Yes      |                                     |

---

## 5. HOTEL

Lookup table for linked hotel data. Managed via a dedicated admin page.

| Field                  | Type              | Required | Notes                                         |
| ---------------------- | ----------------- | -------- | --------------------------------------------- |
| `id`                   | `unsigned bigint` | Yes      | Primary key. Auto-increment                   |
| `name`                 | `string`          | Yes      | Shown in searchable dropdown on job form      |
| `line1`                | `string`          | Yes      |                                               |
| `line2`                | `string`          | No       |                                               |
| `city`                 | `string`          | Yes      |                                               |
| `state`                | `string`          | Yes      |                                               |
| `zip`                  | `string`          | Yes      |                                               |
| `parking_instructions` | `text`            | Yes      | Caregiver-facing. Shown before acceptance     |
| `hourly_rate`          | `decimal`         | Yes      | Parent-facing. Shown on job form              |
| `resort_fee`           | `decimal`         | No       | Parent-facing. Shown if applicable (e.g. $12) |
| `contact_name`         | `string`          | No       | Internal only                                 |
| `contact_phone`        | `string`          | No       | Internal only                                 |
| `admin_notes`          | `text`            | No       | Internal only                                 |
| `is_active`            | `boolean`         | Yes      | Inactive hotels hidden from dropdown          |
| `created_at`           | `timestamp`       | Yes      |                                               |
| `updated_at`           | `timestamp`       | Yes      |                                               |

**Data visibility by role:**

| Who                          | Sees                                         |
| ---------------------------- | -------------------------------------------- |
| Parent (job form)            | Address auto-fill · hourly rate · resort fee |
| Caregiver (before accepting) | Hotel name · address · parking instructions  |
| Caregiver (after accepting)  | Above + contact name and phone               |
| Admin                        | Everything                                   |

---

## 6. PAYMENT_METHOD

One row per saved payment method per client. Supports multiple cards per client.

| Field                | Type              | Required | Notes                                                      |
| -------------------- | ----------------- | -------- | ---------------------------------------------------------- |
| `id`                 | `unsigned bigint` | Yes      | Primary key. Auto-increment                                |
| `client_id`          | `unsigned bigint` | Yes      | FK → `CLIENT.id`                                           |
| `provider`           | `string`          | Yes      | e.g. `stripe` · `manual`                                   |
| `provider_method_id` | `string`          | Yes      | External provider reference (e.g. Stripe PM ID)            |
| `brand`              | `string`          | Yes      | e.g. `visa` · `mastercard` · `amex`. Returned by Stripe    |
| `last4`              | `string`          | Yes      | Last 4 digits of card. Returned by Stripe                  |
| `exp_month`          | `integer`         | Yes      | Expiry month (1–12). Returned by Stripe                    |
| `exp_year`           | `integer`         | Yes      | Expiry year (4-digit). Returned by Stripe                  |
| `is_default`         | `boolean`         | Yes      | Default method used for new jobs. Only one true per client |
| `created_at`         | `timestamp`       | Yes      |                                                            |
| `updated_at`         | `timestamp`       | Yes      |                                                            |

**Notes:**

- Never store raw card numbers or CVV — all sensitive data lives in Stripe. This table only stores metadata returned by Stripe for display purposes (last4, brand, expiry).
- Unique constraint on `stripe_payment_method_id`.
- When `is_default` is set to true for a row, set it to false for all other rows belonging to the same `client_id`.
- Invoiced clients will have no rows in this table.

---

## 7. JOB_GROUP

Envelope for a multi-job submission (up to 10 jobs). One group per form submission.

| Field             | Type              | Required | Notes                                                       |
| ----------------- | ----------------- | -------- | ----------------------------------------------------------- |
| `id`              | `unsigned bigint` | Yes      | Primary key. Auto-increment                                 |
| `client_id`       | `unsigned bigint` | Yes      | FK → `CLIENT.id`                                            |
| `submitted_at`    | `timestamp`       | Yes      |                                                             |
| `submission_type` | `enum`            | Yes      | `guest` · `logged_in` · `admin`                             |
| `is_split`        | `boolean`         | Yes      | True if admin has split the group for individual assignment |
| `created_at`      | `timestamp`       | Yes      |                                                             |
| `updated_at`      | `timestamp`       | Yes      |                                                             |

---

## 8. JOB

One row per individual job. Always belongs to a `JOB_GROUP`, even if the group contains only one job.

| Field                    | Type              | Required | Notes                                                                                                        |
| ------------------------ | ----------------- | -------- | ------------------------------------------------------------------------------------------------------------ |
| `id`                     | `unsigned bigint` | Yes      | Primary key. Auto-increment                                                                                  |
| `job_group_id`           | `unsigned bigint` | Yes      | FK → `JOB_GROUP.id`                                                                                          |
| `client_id`              | `unsigned bigint` | Yes      | FK → `CLIENT.id`                                                                                             |
| `caregiver_id`           | `unsigned bigint` | No       | FK → `CAREGIVER.id`. Null until assigned                                                                     |
| `availability_id`        | `unsigned bigint` | No       | FK → `AVAILABILITY.id`. Set when caregiver is assigned                                                       |
| `hotel_id`               | `unsigned bigint` | No       | FK → `HOTEL.id`. Null for non-hotel jobs                                                                     |
| `address_id`             | `unsigned bigint` | No       | FK → `CLIENT_ADDRESS.id`                                                                                     |
| `service_type`           | `enum`            | Yes      | `babysitter` · `petsitter` · `companion_care` · `group_childcare_invoiced` · `corporate_invoiced` · `comped` |
| `location_type`          | `enum`            | Yes      | `hotel` · `private_home` · `vacation_rental` · `event_venue`                                                 |
| `start_datetime`         | `timestamp`       | Yes      | Future only. Min 4 hours before `end_datetime`                                                               |
| `end_datetime`           | `timestamp`       | Yes      |                                                                                                              |
| `status`                 | `enum`            | Yes      | `received` · `pending` · `confirmed` · `completed` · `cancelled`                                             |
| `special_considerations` | `json`            | No       | Tags: `infant_care` · `dogs_cats` · `pool`                                                                   |
| `caregiver_notes`        | `text`            | No       | Visible to caregiver after acceptance                                                                        |
| `notes_to_sitterwise`    | `text`            | No       | Internal only                                                                                                |
| `admin_notes`            | `text`            | No       | Internal only                                                                                                |
| `corporate_id`           | `string`          | No       | Invoiced clients only                                                                                        |
| `comped`                 | `boolean`         | Yes      | Defaults false. Caregiver still paid by Sitterwise                                                           |
| `total_amount`           | `decimal`         | Yes      | Total expected charge                                                                                        |
| `payment_status`         | `enum`            | Yes      | `pending` · `paid` · `failed` · `refunded`                                                                   |
| `requires_payment`       | `boolean`         | Yes      | False for invoiced/comped                                                                                    |
| `created_at`             | `timestamp`       | Yes      |                                                                                                              |

**Billing logic:**

| Service type                                      | Stripe charge | Caregiver paid      |
| ------------------------------------------------- | ------------- | ------------------- |
| Babysitter / Petsitter / Companion Care           | Yes           | Yes, via split      |
| Group Childcare (Invoiced) / Corporate (Invoiced) | No — invoiced | Yes                 |
| Comped                                            | No            | Yes — by Sitterwise |

---

## 9. CAREGIVER_INVITATION

Tracks each caregiver's invitation to a job group, including the soft-lock window.

| Field             | Type              | Required | Notes                                                           |
| ----------------- | ----------------- | -------- | --------------------------------------------------------------- |
| `id`              | `unsigned bigint` | Yes      | Primary key. Auto-increment                                     |
| `job_group_id`    | `unsigned bigint` | Yes      | FK → `JOB_GROUP.id`                                             |
| `caregiver_id`    | `unsigned bigint` | Yes      | FK → `CAREGIVER.id`                                             |
| `availability_id` | `unsigned bigint` | Yes      | FK → `AVAILABILITY.id`. The slot being soft-locked              |
| `status`          | `enum`            | Yes      | `pending` · `accepted` · `declined` · `expired` · `rolled_back` |
| `invited_at`      | `timestamp`       | Yes      |                                                                 |
| `lock_expires_at` | `timestamp`       | Yes      | `invited_at + 90 seconds`                                       |
| `responded_at`    | `timestamp`       | No       | Null until caregiver responds                                   |
| `created_at`      | `timestamp`       | Yes      |                                                                 |
| `updated_at`      | `timestamp`       | Yes      |                                                                 |

**Status transition rules:**

| Transition                             | Trigger                                               |
| -------------------------------------- | ----------------------------------------------------- |
| `pending` → `accepted`                 | Caregiver accepts. Job is created and confirmed       |
| `pending` → `declined`                 | Caregiver explicitly declines                         |
| `pending` → `expired`                  | `lock_expires_at` has passed and no response received |
| `pending` / `accepted` → `rolled_back` | Another caregiver was assigned to the same job group  |

**On acceptance:** immediately set all other `pending` invitations on the same `job_group_id` to `rolled_back`. This must run as a single atomic backend workflow — not a scheduled job.

---

## 10. CLIENT_CAREGIVER_JOB

Records every caregiver who has worked a job for a client. Drives the "Previous Caregivers" list on the client profile and factors into assignment suggestions.

| Field          | Type              | Required | Notes                                          |
| -------------- | ----------------- | -------- | ---------------------------------------------- |
| `id`           | `unsigned bigint` | Yes      | Primary key. Auto-increment                    |
| `client_id`    | `unsigned bigint` | Yes      | FK → `CLIENT.id`                               |
| `caregiver_id` | `unsigned bigint` | Yes      | FK → `CAREGIVER.id`                            |
| `job_id`       | `unsigned bigint` | Yes      | FK → `JOB.id`                                  |
| `worked_at`    | `timestamp`       | Yes      | Copied from `JOB.start_datetime` on completion |
| `created_at`   | `timestamp`       | Yes      |                                                |

**Notes:**

- One row per completed job, not per client-caregiver pair. A caregiver who has worked three jobs for the same client has three rows.
- Written when `JOB.status` transitions to `completed`.
- Query distinct `caregiver_id` per `client_id` to build the Previous Caregivers list.

---

## 11. CLIENT_FAVORITE_CAREGIVER

Explicit favorites marked by the client or admin. Separate from job history — a client can favorite a caregiver they haven't worked yet, or remove a favorite without affecting history.

| Field          | Type              | Required | Notes                       |
| -------------- | ----------------- | -------- | --------------------------- |
| `id`           | `unsigned bigint` | Yes      | Primary key. Auto-increment |
| `client_id`    | `unsigned bigint` | Yes      | FK → `CLIENT.id`            |
| `caregiver_id` | `unsigned bigint` | Yes      | FK → `CAREGIVER.id`         |
| `created_at`   | `timestamp`       | Yes      | When the favorite was added |

**Notes:**

- Unique constraint on (`client_id`, `caregiver_id`) — a caregiver can only be favorited once per client.
- Removing a favorite deletes the row. No soft delete needed here since no history depends on it.

---

## 12. JOB_SNAPSHOT

Immutable record of all context-sensitive data at the moment a job is confirmed. Written once — never updated. Survives any future edits or deletions to the source records.

| Field                           | Type              | Required | Notes                                                                                       |
| ------------------------------- | ----------------- | -------- | ------------------------------------------------------------------------------------------- |
| `id`                            | `unsigned bigint` | Yes      | Primary key. Auto-increment                                                                 |
| `job_id`                        | `unsigned bigint` | Yes      | FK → `JOB.id`                                                                               |
| `snapshotted_at`                | `timestamp`       | Yes      | Set to `now()` at confirmation time                                                         |
| `created_at`                    | `timestamp`       | Yes      |                                                                                             |
| `address`                       | `json`            | Yes      | Full address object at time of job                                                          |
| `hotel`                         | `json`            | No       | Full hotel object including rate, resort fee, parking instructions. Null for non-hotel jobs |
| `children`                      | `json`            | Yes      | Array of child objects: name, gender, calculated age at job date, special needs notes       |
| `pets`                          | `json`            | No       | Array of pet objects: type, breed, notes. Null if no pets                                   |
| `client_medical_info`           | `text`            | No       | Copy of `CLIENT.medical_info` at time of job                                                |
| `client_emergency_instructions` | `text`            | No       | Copy of `CLIENT.emergency_instructions` at time of job                                      |
| `client_house_notes`            | `text`            | No       | Copy of `CLIENT.house_notes` at time of job                                                 |
| `caregiver_name`                | `string`          | No       | Full name of assigned caregiver. Set after assignment                                       |
| `hourly_rate`                   | `decimal`         | No       | Rate quoted to parent. Copied from `HOTEL.hourly_rate` or standard rate                     |

**Example `children` JSON structure:**

```json
[
    {
        "name": "Lily",
        "gender": "female",
        "age_at_job": 4,
        "special_needs": true,
        "special_needs_notes": "Peanut allergy — carries EpiPen"
    }
]
```

**Example `hotel` JSON structure:**

```json
{
    "name": "Marriott Gaslamp",
    "address": "660 K St, San Diego, CA 92101",
    "parking_instructions": "Self-park in attached garage, Level 2. Validate at front desk.",
    "hourly_rate": 22.0,
    "resort_fee": 12.0,
    "contact_name": "Maria Santos",
    "contact_phone": "+1 619-446-6000"
}
```

**When to write the snapshot:**

- Trigger: `JOB.status` transitions to `confirmed`.
- Write once, never update. If a job is modified after confirmation (e.g. time change), write a second snapshot row and link both to the same `job_id` — don't overwrite the original.
- For the `children` array, calculate and store `age_at_job` as an integer at snapshot time, derived from `birth_month` / `birth_year` relative to `start_datetime`. This preserves the age the caregiver was shown, even as the child grows.

**What this protects against:**

- A hotel's rate or parking instructions being updated after a job was made.
- A child's special needs notes being edited between submission and job date.
- A client or hotel record being deleted — the job history remains fully readable.
- Disputes about what information the caregiver was given before accepting.

**Laravel implementation note:** Trigger snapshot creation inside a model observer or a dedicated `JobConfirmedListener` that fires when `JOB.status` transitions to `confirmed`. Populate each JSON field from the already-loaded Eloquent relationships to avoid extra queries.

---

## 13. CLIENT_TYPE_CHANGE

Append-only log of every `client_type` change on a client record. Never updated or deleted. Surfaced in the admin client profile view.

| Field                 | Type              | Required | Notes                                                                                         |
| --------------------- | ----------------- | -------- | --------------------------------------------------------------------------------------------- |
| `id`                  | `unsigned bigint` | Yes      | Primary key. Auto-increment                                                                   |
| `client_id`           | `unsigned bigint` | Yes      | FK → `CLIENT.id`                                                                              |
| `changed_by_admin_id` | `unsigned bigint` | Yes      | FK → admin/staff user record. Always an admin — clients cannot change their own type once set |
| `previous_type`       | `enum`            | Yes      | `sd_resident` · `vacationer` · `invoiced`                                                     |
| `new_type`            | `enum`            | Yes      | `sd_resident` · `vacationer` · `invoiced`                                                     |
| `reason`              | `text`            | No       | Optional admin note explaining the change                                                     |
| `changed_at`          | `timestamp`       | Yes      | Set to `now()` at time of change                                                              |

**Notes:**

- A row is inserted on every `client_type` mutation — never update or delete existing rows.
- The first time a client type is set (on initial job submission or admin creation), no row is written here since there is no previous value to log. This table only captures _changes_ to an already-set type.
- `changed_by_admin_id` reinforces the rule that only admins can change `client_type` to or from `invoiced`. Client-initiated changes (SD Resident ↔ Vacationer during job submission) should still be logged here with the acting admin's ID if routed through an admin workflow, or a dedicated system user ID if automated.
- Display in admin UI as a chronological list: "Changed from SD Resident → Invoiced by Kristie on Mar 15, 2026."

---

## 14. PAYMENT

Source of truth for all financial transactions.

| Field                 | Type              | Required | Notes                                                         |
| --------------------- | ----------------- | -------- | ------------------------------------------------------------- |
| `id`                  | `unsigned bigint` | Yes      | Primary key                                                   |
| `job_id`              | `unsigned bigint` | Yes      | FK → `JOB.id`                                                 |
| `client_id`           | `unsigned bigint` | Yes      | FK → `CLIENT.id`                                              |
| `payment_method_id`   | `unsigned bigint` | No       | FK → `PAYMENT_METHOD.id`                                      |
| `amount`              | `decimal`         | Yes      |                                                               |
| `currency`            | `string`          | Yes      | Default USD                                                   |
| `status`              | `enum`            | Yes      | `pending` · `authorized` · `captured` · `failed` · `refunded` |
| `provider`            | `enum`            | Yes      | `stripe` · `invoice` · `manual` · `comped`                    |
| `provider_payment_id` | `string`          | No       | e.g. Stripe PaymentIntent                                     |
| `provider_charge_id`  | `string`          | No       | e.g. Stripe Charge                                            |
| `paid_at`             | `timestamp`       | No       |                                                               |
| `created_at`          | `timestamp`       | Yes      |                                                               |
| `updated_at`          | `timestamp`       | Yes      |                                                               |

**Notes:**

- One job can have multiple payments
- Supports retries, partial payments, refunds
- Invoiced jobs: `provider = invoice`
- Comped jobs: `amount = 0`, `provider = comped`

---

## 15. Relationships Summary

| Relationship                              | Type                                     |
| ----------------------------------------- | ---------------------------------------- |
| `CLIENT` → `CLIENT_ADDRESS`               | One-to-many                              |
| `CLIENT` → `CLIENT_CHILD`                 | One-to-many                              |
| `CLIENT` → `CLIENT_PET`                   | One-to-many                              |
| `CLIENT` → `PAYMENT_METHOD`               | One-to-many                              |
| `CLIENT` → `JOB_GROUP`                    | One-to-many                              |
| `CLIENT` → `CLIENT_TYPE_CHANGE`           | One-to-many                              |
| `CLIENT` → `CLIENT_CAREGIVER_JOB`         | One-to-many                              |
| `CLIENT` → `CLIENT_FAVORITE_CAREGIVER`    | One-to-many                              |
| `JOB_GROUP` → `JOB`                       | One-to-many (up to 10)                   |
| `JOB` → `JOB_SNAPSHOT`                    | One-to-many (one per confirmation event) |
| `JOB` → `CLIENT_CAREGIVER_JOB`            | One-to-many                              |
| `JOB` → `HOTEL`                           | Many-to-one (optional)                   |
| `JOB` → `CAREGIVER`                       | Many-to-one (optional until assigned)    |
| `JOB` → `CLIENT_ADDRESS`                  | Many-to-one (optional)                   |
| `JOB` → `AVAILABILITY`                    | Many-to-one (optional until assigned)    |
| `JOB_GROUP` → `CAREGIVER_INVITATION`      | One-to-many                              |
| `CAREGIVER_INVITATION` → `CAREGIVER`      | Many-to-one                              |
| `CAREGIVER_INVITATION` → `AVAILABILITY`   | Many-to-one                              |
| `CAREGIVER` → `CLIENT_CAREGIVER_JOB`      | One-to-many                              |
| `CAREGIVER` → `CLIENT_FAVORITE_CAREGIVER` | One-to-many                              |

---

## 16. Availability Status Logic

The effective status of an availability slot is **never stored** — it is always derived at query time from the following priority order:

```
effective_status =
  if JOB exists for this availability_id
    → "booked"
  else if CAREGIVER_INVITATION exists where
       availability_id matches
       AND status = 'pending'
       AND lock_expires_at > now()
    → "soft-locked"
  else
    → AVAILABILITY.base_status
```

**Why this approach:**

- Rollback requires no writes to `AVAILABILITY` — only the invitation rows are updated.
- The 90-second hold auto-releases passively: once `lock_expires_at` passes, the slot reads as available again without any cleanup job.
- A single source of truth prevents sync issues between the availability table and job state.

**Laravel implementation note:** Encapsulate this logic in a dedicated query scope or service method (e.g. `AvailabilityService::getEffectiveStatus()`). Do not cache the result on the `AVAILABILITY` model itself. Use Laravel's `whereHas` / `whereDoesntHave` to check related `JOB` and `CAREGIVER_INVITATION` rows efficiently in a single query.
