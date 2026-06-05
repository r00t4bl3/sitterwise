# Bubble.io → Database Field Mapping

The import pipeline scrapes Bubble.io data via its Elasticsearch API (intercepted from the Data Editor UI), stores the raw JSON in a SQLite staging database (`staged_records.raw_json`), then processes it into the application database via `ImportUserService`.

## Conventions

Bubble uses suffix conventions for field types:

| Suffix | PHP Type | Transformation |
|--------|----------|---------------|
| `_text` | string | direct copy |
| `_number` | float/int | direct copy |
| `_boolean` | bool | truthy check |
| `_date` | int (ms epoch) | `timestampToDate()` → `Y-m-d` (America/Los_Angeles) |
| `_date` (datetime) | int (ms epoch) | `timestampToDateTime()` → `Y-m-d H:i:s` (UTC) |
| `_option_*` | string | enum/option set value mapping |
| `_list_*` | string[] | array or comma-split string |
| `_geographic_address` | object | `components` hash with street/city/state/zip |
| `_file` | string | file URL, may need `https:` prefix |
| `authentication.email.email` | string | nested Bubble auth email |

## Timestamp Handling

Bubble stores **no timezone information**. All date values are epoch milliseconds (integers). The code assumes they represent `America/Los_Angeles` wall-clock time.

- **Date-only fields** (`date_of_birth`, experience dates, CPR expirations): `timestampToDate()` — extracts `Y-m-d` in LA timezone, no UTC conversion.
- **Datetime fields** (`start_datetime`, `end_datetime`, `confirmed_at`, `cancelled_at`): `timestampToDateTime()` — interprets as `America/Los_Angeles`, converts to UTC for storage.

---

## Users

### Table: `users`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `_id` (record ID) | `bubble_id` | direct |
| `authentication.email.email` | `email` | |
| `first_name_text` + `last_name_text` | `name` | Joined via `parseSourceNames()`; fallback: `firstnamelastname_text` split, then email prefix |
| `role_permissions_option_role` | `role` | One of `caregiver`, `client`, `caregiver_applicant` |
| `profile_photo_url_text` \| `profile_photo_file` | `profile_photo_url` | `parsePhotoUrl()` — prepends `https:` if starts with `//` |
| `temporary_password_text` | `password` | `Hash::make()`; default `'changeme123'` |
| — | `created_at` / `updated_at` | `now()` |

---

## Caregivers

### Table: `caregivers`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| _(via user)_ | `user_id` | FK to `users` |
| _(via user)_ | `bubble_id` | from `users.bubble_id` |
| `cg_status_option_cg_status_options` | `status` | `CaregiverStatus::tryFrom()`; default `'inactive'` |
| `first_name_text` + `last_name_text` | `first_name`, `last_name` | `parseSourceNames()` → `formatName()` (title case) |
| `Slug` | `slug` | Collision: appends `-{bubble_id_prefix}` |
| `phone_text` | `phone` | `Phone::normalizePhone()` → E.164 (`+1XXXXXXXXXX`) |
| `address_geographic_address` | `address_line1`, `address_city`, `address_state`, `address_zip` | Geographic components parsed |
| `date_of_birth_date` | `date_of_birth` | `timestampToDate()` |
| `bio_text` | `biography` | |
| `internal_notes_text` | `notes` | |
| `highest_level_education_text` | `education_level` | |
| `languages_text` | `languages` | JSON array; `"none"` → null |
| `cg_stripe_id_text` \| `stripe_account_id_text` | `stripe_account_id` | First non-null |
| `charges_enabled_boolean` | `stripe_charges_enabled` | |
| `service_areas_text` | `caregiver_locations` (pivot) | Parsed into `caregiver_locations` via `passCaregivers()` — split by `,`/`/`, fuzzy match to `locations.name`. First matched location is `is_preferred = true` |
| `cg_star_rating__rated_by_client__number` | `rating` | If > 0 |
| `5_star_boolean` | `admin_rating` | `5.0` if true, else null |

### Table: `caregiver_educations`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `high_school_name_text` | `school_name` | Where `education_type = 'high_school'` |
| `graduation_year_date` | `graduation_year` | `timestampToYear()` |
| `college_name_text` | `school_name` | Where `education_type = 'college'` |
| `college_graduation_year_text` | `graduation_year` | Text or timestamp |

### Table: `caregiver_experiences`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `childcare_experience_{1-3}_start_date_date` | `start_date` | `timestampToDate()` |
| `childcare_experience_{1-3}_end_date_date` | `end_date` | `timestampToDate()` |
| `childcare_experience_{1-3}_details_text` | `details` | |

### Table: `caregiver_references`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `previous_caregivers_list_text` | `reference_name` | List or comma-separated string |

### Table: `caregiver_sponsors`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `sponsor_first_name_text` | `first_name` | |
| `sponsor_last_name_text` | `last_name` | |
| `sponsor_email_text` | `email` | |

### Table: `caregiver_certifications` (pivot)

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `cpr_exp_date` + `first_aid_exp_date` | `expiration_date` | `timestampToDate()`, min of both. Links to `certification_types.name = 'CPR & First Aid'` |
| `background_check_exp_date` | `expiration_date` | `timestampToDate()`. Links to `certification_types.name = 'Background Check'` |
| `trustline__text` | _(pivot only)_ | `verified_at = now()` if `yes`/`true`/`1`. Links to `certification_types.name = 'Trustline'` |

### Table: `caregiver_specialties` (pivot)

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `baby_specialist_boolean` | _(existence trigger)_ | If true, links to `specialty_types.name = 'Babies'` |

### Table: `entity_attribute_values` (pivot, entity_type = 'caregiver')

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `care_com_boolean` | `value` | `'true'` / `'false'`. Links to `attribute_definitions.slug = 'care_com'` |

---

## Clients

### Table: `clients`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| _(via user)_ | `user_id` | FK to `users` |
| _(via user)_ | `bubble_id` | from `users.bubble_id` |
| `first_name_text` + `last_name_text` | `first_name`, `last_name` | `parseSourceNames()` → `formatName()` |
| `bio_text` + `house_notes_text` | `biography` | Concatenated if house notes exist |
| `phone_text` | `phone` | `Phone::normalizePhone()` → E.164 |
| `corporate__boolean` | `client_type` | `mapClientType()`: `'invoiced'` if corporate, `'vacationer'` if hotel address, else `'resident'` |
| `how_did_you_hear_about_us_text` | `how_did_you_hear` | |
| `internal_notes_text` | `notes` | |
| `StripeCustomerID` | `stripe_customer_id` | |

### Table: `client_addresses`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `address_geographic_address` | `line1`, `city`, `state`, `zip` | `is_primary = true`. Location type derived from `client_type` |
| `home_address_geographic_address` | `line1`, `city`, `state`, `zip` | Deduped against primary. `is_primary = false` |
| `address_book_list_text` | `line1`, `city`, `state`, `zip` | Comma-split parts. Deduped. `is_primary = false` |

### Table: `client_children`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `names_and_ages_of_kids_text` | `name`, `birth_year` | Parsed via `parseChildEntry()` — supports multiple text patterns (`"Name (5)"`, `"Name - 5"`, `"Name age 5"`, etc.). Birth year = current year minus parsed age |

### Table: `client_pets`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `pets_in_the_home_text` | `type`, `name`, `notes` | Keyword matching for dog/cat/other |

---

## Jobs (BookingGroup + Booking)

### Table: `booking_groups`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| _(auto)_ | `submitted_at` | `now()` |
| _(auto)_ | `submission_type` | `'import'` |
| _(auto)_ | `client_id` | FK resolved via `client_email_text` lookup |
| `client_first_name1_text` | `client_first_name` | `formatName()` (title case) |
| `client_last_name1_text` | `client_last_name` | `formatName()` |
| `client_email_text` | `client_email` | |
| `client_phone_text` | `client_phone` | `Phone::normalizePhone()` |
| `service1_option_services` | `service_type` | `mapServiceType()` — see table below |
| `address_is_hotel__option_list_of_hotels` | `location_type` | `mapLocationType()` — `'hotel'` if set, else `'private_home'` |
| `street_address_geographic_address` | `address_line1`, `address_city`, `address_state`, `address_zip` | Geographic components |
| `hotel_name_text` \| `address_is_hotel__option_list_of_hotels` | `hotel_name`, `hotel_id` | `findHotelId()` — fuzzy match via normalized name, levenshtein, contains |
| `cg_checkout_job_notes_text` \| `caregiver_notes_text` | `caregiver_notes` | First non-null |
| `notes_to_sw_admin_text` | `notes_to_sitterwise` | |
| `admin_notes_text` | `admin_notes` | |
| `corporate_job_id_text` | `corporate_id` | External corporate booking reference |
| `names_and_ages_of_children_text` + `__of_children_option_number_of_kids` | `children` | `parseChildren()` → `[{name}]` JSON array |
| `names_and_ages_of_children_text` | `children_notes` | Only for `group_childcare_invoiced` service type |
| `pets_text` | `pets` | `parsePets()` → `[{type, notes}]` JSON array |
| `special_considerations__new__list_option_special_considerations` + `special_considerations1_text` + `special_considerations_text` + `pets_text` | `special_considerations` | `mapSpecialConsiderations()` — see table below |

#### `mapServiceType()` Enum Mapping

| Bubble Value | App `service_type` |
|---|---|
| `'babysitting'` (default) | `'babysitter'` |
| `'corporate__invoiced_'` | `'corporate_invoiced'` |
| `'group_childcare'` | `'group_childcare_invoiced'` |
| `'petsitting'` | `'petsitter'` |
| `'comped'` | `'comped'` |
| `'companion_care'` | `'companion_care'` |

#### `mapSpecialConsiderations()` Logic

| Bubble Source | App Value |
|---|---|
| List item containing `infant_care` | `'infant_care'` |
| List item containing `special_needs` | `'special_needs_care'` |
| List item containing `swimming` | `'swimming_requested'` |
| List item containing `parent_will_be_present` | `'parent_will_be_present'` |
| Notes text containing `"infant"` | `'infant_care'` |
| Pets text containing `"dog"` | `'family_has_dogs_onsite'` |
| Pets text containing `"cat"` | `'family_has_cats_onsite'` |

### Table: `bookings`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `_id` (record ID) | `bubble_id` | |
| _(auto)_ | `booking_group_id` | FK to the created `BookingGroup` |
| _(auto)_ | `caregiver_id` | FK resolved via `cg_email_text` lookup |
| `start_date_date` | `start_datetime` | `timestampToDateTime()` → UTC |
| `end_date_date` | `end_datetime` | `timestampToDateTime()` → UTC |
| `confirmed_at_date` | `confirmed_at` | `timestampToDateTime()` → UTC; falls back to `now()` if caregiver exists |
| `cancellation_date_date` | `cancelled_at` | `timestampToDateTime()` → UTC |
| `cancellation_reason_text` | `cancellation_reason` | |
| — | `cancelled_by_id` | **Not populated by import** — Bubble has no "cancelled by" field |
| `job_status_option_job_status` | `status` | See enum mapping below |
| `total_hours_number` | `total_working_hour` | |
| `client_job_hourly_rate_number` | `charge_to_client_hourly` | |
| `job_cg_hourly_rate_number` | `paid_to_caregiver_hourly` | |
| `job_agency_hourly_rate_number` | `sitterwise_cut_hourly` | |
| `client_total_number` | `charge_to_client` | Bubble ground truth; falls back to `round(clientHourly × hours, 2)` |
| `caregiver_total_number` | `paid_to_caregiver` | Bubble ground truth; 0 for invoiced; falls back to `round(cgHourly × hours, 2)` |
| _(computed)_ | `sitterwise_cut` | `charge_to_client - paid_to_caregiver` (Bubble doesn't provide separate SW cut) |
| `cg_tip_number` | `tip` | |
| `bonus_number` | `bonus` | |
| `check_out_reimbursement_number` | `reimbursement` | |
| `check_out_reimbursement_description_text` | `reimbursement_description` | |
| `job_hotel_booking_fee_number` | `hotel_fee` | |
| `job_status_option_job_status == 'paid'` | `payment_status` | `'paid'` / `'unpaid'` |
| `payment_intent_id_text` | `stripe_payment_intent_id` | |
| `caregiver_total_number` | `paid_to_caregiver_total` | Bubble ground truth (includes bonus/reimbursement/tip) |
| _(computed)_ | `total_service_amount` | `charge_to_client - tip` |
| _(computed)_ | `total_amount` | `charge_to_client` |

#### Booking Status Enum Mapping

| Bubble `job_status_option_job_status` | App `status` |
|---|---|
| `'paid'` | `'paid'` |
| `'completed'` | `'completed'` |
| `'confirmed'` | `'confirmed'` |
| `'pending'` | `'pending'` |
| `'received'` (default) | `'received'` |
| `'cancelled'` | `'cancelled'` |

### Table: `caregiver_assignments`

Created per booking during import.

| Source | Column | Notes |
|---|---|---|
| `caregiver_id` (from booking) | `caregiver_id` | |
| booking id | `booking_id` | FK |
| `confirmed_at_date` or `created_at` | `assigned_at` | First non-null |
| booking `status` | `resolution` | `'cancelled'` → `'cancelled_by_sitterwise'`, else `'completed'` |
| `end_datetime` or `updated_at` | `resolution_at` | Depends on resolution |

---

## Ratings

### Table: `booking_ratings`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `_id` (record ID) | `bubble_id` | |
| _(lookup)_ | `booking_id` | FK — matched via client email + ±5 min of `start_datetime` |
| `client_email_text` | _(lookup)_ | Match against booking group's client |
| `cg_email_text` | _(lookup)_ | Optional — adds caregiver filter to booking lookup |
| `date_date` | _(lookup anchor)_ | `timestampToDateTime()` |
| `number_number` | `rating` | Skipped if ≤ 0 (placeholder values) |
| `feedback_notes_text` | `comment` | |
| `review_for_client_boolean` | `rater_id`, `ratable_id`, `ratable_type` | If true: rater = caregiver's user ID, ratable = client |
| `Created Date` | `created_at` | `timestampToDateTime()` |

---

## Transactions

### Table: `client_payments`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `_id` (record ID) | `bubble_id` | |
| _(auto)_ | `booking_id` | Matched via PI, stripe IDs + date, or amount + date |
| _(auto)_ | `client_id` | From matched booking |
| `payment_intent_id_text` | `provider_payment_id` | |
| `amount_number` | `amount` | |
| _(auto)_ | `status` | `'succeeded'` |
| _(auto)_ | `provider` | `'stripe'` |
| `date_date` \| `Created Date` | `paid_at` | `timestampToDateTime()` |

### Table: `caregiver_payouts`

| Bubble JSON Key | Column | Notes |
|---|---|---|
| `_id` (record ID) | `bubble_id` | |
| _(auto)_ | `booking_id` | Matched same as payments |
| _(auto)_ | `caregiver_id` | From matched booking |
| _(auto)_ | `caregiver_payout_method_id` | Via `caregiver_payout_methods` table |
| `caregiver_total_transfer_number` | `amount` | |
| _(auto)_ | `status` | `'paid'` |
| `date_date` \| `Created Date` | `payout_date` | `timestampToDateTime()` |

### Table: `caregiver_payout_methods`

Auto-created per caregiver if not exists.

| Source | Column | Value |
|---|---|---|
| Caregiver's `stripe_account_id` | `provider_method_id` | Or `'imported_from_bubble'` |
| _(auto)_ | `provider` | `'stripe'` |
| _(auto)_ | `account_type` | `'unknown'` |
| _(auto)_ | `bank_name` | `'Imported from Bubble'` |
| _(auto)_ | `last4` | `'0000'` |

---

## Unmapped Bubble Fields

These Bubble fields exist in the staging database but are **not** mapped to any application column. They are discarded during import.

System/Bubble-internal fields (`_id`, `_type`, `_version`, `_slug_text`, `Created By`, `Created Date`, `Modified Date`, `Slug`) are excluded from this list.

### User — Unmapped (16)

| Bubble Key | Notes |
|---|---|
| `__of_children_option_number_of_kids` | Number of children — only used in jobs context |
| `__of_companions_option___of_companions` | Not used |
| `__of_dogs_option___of_dogs` | Not used |
| `alcohol_or_drug_abuse___text` | Background/health info |
| `allergic__boolean` | Health info |
| `application__availability_text` | Caregiver availability |
| `caregiver_profile_link_text` | Duplicate of `profile_link_text` |
| `client_type_option_client_type` | Explicit client type — the import *infers* type from `corporate__boolean` / `address_is_hotel__boolean` instead |
| `dl_issuing_state_text` | Driver's license issuing state |
| `drink__text` | Background/health info |
| `driver_s_license_text` | Driver's license number |
| `felony__text` | Criminal history |
| `physical_limitations__text` | Physical limitations |
| `profile_link_text` | Profile URL |
| `ss__number` | ⚠️ **SSN** — sensitive data, not imported |
| `user_signed_up` | Signup date |

### Jobs — Unmapped (6)

| Bubble Key | Notes |
|---|---|
| `caregiver_name_text` | Denormalized caregiver full name |
| `cg_phone_text` | Caregiver phone on job record |
| `client_first_name_last_name_text` | Denormalized client full name |
| `job_sw_platform_fee_number` | Sitterwise platform fee amount — inconsistent margin relationship |
| `payment_date_date` | Payment processing date |
| `sw_total_number` | Sitterwise total earnings — inconsistent margin relationship |

### Rating — Unmapped (2)

| Bubble Key | Notes |
|---|---|
| `caregiver_name_text` | Denormalized caregiver name |
| `client_name_text` | Denormalized client name |

### Transactions — Unmapped (1)

| Bubble Key | Notes |
|---|---|---|
| `sw_total_number` | Sitterwise platform earnings |

### Notable Gaps

| Field | Type | Why It Matters |
|---|---|---|
| `ss__number` | user | ⚠️ Only 1 record has this field (placeholder value `1234567891`). Not useful for tax processing — tax data (W-9, legal name, EIN) would need to be collected fresh from caregivers via Stripe Connect onboarding or a dedicated flow |
| `job_sw_platform_fee_number` / `sw_total_number` | jobs, transactions | Analyzed across date range — formula relationship to client/caregiver totals is inconsistent. Not reliable enough to map. SW cut computed as `client_total_number - caregiver_total_number` instead |
| `alcohol_or_drug_abuse___text`, `felony__text`, `physical_limitations__text`, `drink__text`, `allergic__boolean` | user | Background/health info on caregivers — may be compliance-relevant or useful for matching with client needs |
| `application__availability_text` | user | Caregiver availability nuance — 165 records (164 caregivers, 1 client). 3 formats: free-text (121, e.g. "weekends and nights"), "open availability" (25), and tab-separated day slots (19, e.g. "9am\t3pm,9am\t6pm,..."). App stores structured availability in `caregiver_applications.data->availability->notes` — this field could map there as free-text nuance |

### Resolved Gaps

| Field | Type | Resolution |
|---|---|---|
| `client_total_number` | jobs | Now mapped to `bookings.charge_to_client` — Bubble ground truth, falls back to `round(clientHourly × hours, 2)` |
| `caregiver_total_number` | jobs | Now mapped to `bookings.paid_to_caregiver` and `bookings.paid_to_caregiver_total` — Bubble ground truth, falls back to `round(cgHourly × hours, 2)` (0 for invoiced) |
| `client_type_option_client_type` | user | Now checked first in `mapClientType()` — `'corporate'` → `'invoiced'` directly, falls back to boolean inference |
| `corporate_job_id_text` | jobs | Now mapped to `booking_groups.corporate_id` — forward-looking; 0 existing records populate this field in Bubble |
| `charges_enabled_boolean` | user | Now mapped to `caregivers.stripe_charges_enabled` — primarily caregiver field; clients almost always false |
| `service_areas_text` | user | Now mapped to `caregiver_locations` pivot via `passCaregivers()` — split by `,`/`/`, fuzzy-matched to `locations.name` |

---

## Pipeline Overview

```
Bubble Elasticsearch API
  │  raw source JSON
  ▼
ImportBubbleDatabase (scrape + cache)
  │  stores full JSON in staged_records.raw_json
  ▼
SQLite staging database (bubble_staging.sqlite)
  │  staged_records table: type, external_id, modified_at, raw_json
  ▼
ImportStagedData (orchestrator)
  │  delegates to ImportUserService
  ▼
ImportUserService (field mapping + DB insert)
  │  processUserHit() → users, caregivers, clients
  │  passJobs() → booking_groups, bookings, caregiver_assignments
  ▼
Application database (MySQL)
```

**Key locations:**
- Field mapping logic: `app/Services/ImportUserService.php`
- Scraping + staging: `app/Console/Commands/ImportBubbleDatabase.php`
- Staging orchestrator: `app/Console/Commands/ImportStagedData.php`
- Staging database: `storage/app/bubble_staging.sqlite`
