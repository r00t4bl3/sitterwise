# Wizard Frontend Validation + CPR Card Enforcement Plan

## Problem

The application wizard (`resources/js/pages/public/caregiver-apply/wizard.tsx`) has gaps in frontend validation:

1. **Step navigation bypasses validation** — `goToStep()` allows clicking any step number without validating the current step
2. **Missing CPR field enforcement** — `cpr_expiration` and `cpr_card` are not required when `cpr_certified === 'yes'`, either in frontend or backend
3. **Missing `*` markers** — Some backend-required fields lack visual required indicators
4. **No inline errors** — Most steps don't show per-field error messages

## Scope

- **8-step wizard** — Add `validateStep()` blocking forward navigation (Next button + step number clicks)
- **Backward navigation** unrestricted (Back button + clicking earlier steps)
- **Inline error messages** beneath each validated field
- **Backend `required_if` rules** for CPR fields
- **`*` markers** on mandatory fields that currently lack them

## Files to Modify

| File | Changes |
|------|---------|
| `resources/js/pages/public/caregiver-apply/wizard.tsx` | Add `validateStep()`, modify `nextStep()`/`goToStep()`, add inline errors, add `*` markers |
| `app/Http/Requests/StoreCaregiverApplicationRequest.php` | Add `required_if:cpr_certified,yes` to `cpr_expiration` and `cpr_card` |

## `*` Markers to Add

| Step | Location | Label | Reason |
|------|----------|-------|--------|
| 2 | Position group | "What are you applying for? Check all that apply." | Backend enforces at least one |
| 3 | Ages Served | "Ages Served" (checkbox group) | Backend `required\|array\|min:1` |
| 6 | Location group | "Where are you willing to work?" | Frontend will require at least one |

All other `*` markers already exist in the UI.

## `validateStep(step: number): boolean`

### Signature

```typescript
function validateStep(step: number): boolean {
    form.clearErrors();
    const data = form.data;
    let hasError = false;
    // per-step checks...
    if (hasError) return false;
    return true;
}
```

### Per-Step Validation Rules

#### Step 1 — Sponsor & Personal Information

| Field | Condition | Error Message |
|-------|-----------|---------------|
| `sponsor.first_name` | empty | "Sponsor first name is required." |
| `sponsor.last_name` | empty | "Sponsor last name is required." |
| `sponsor.email` | empty | "Sponsor email is required." |
| `personal.first_name` | empty | "First name is required." |
| `personal.last_name` | empty | "Last name is required." |
| `personal.email` | empty | "Email is required." |
| `personal.phone` | empty | "Phone number is required." |
| `personal.dob` | empty | "Date of birth is required." |
| `personal.address_line1` | empty | "Address is required." |

#### Step 2 — Position, Availability & Education

| Field | Condition | Error Message |
|-------|-----------|---------------|
| `position` (group) | `!babysitting && !petsitting && !group_events` | "Please select at least one position." |

`education.level` defaults to `'bachelor'` — always non-empty, no validation needed.

#### Step 3 — Employment & Experience

| Field | Condition | Error Message |
|-------|-----------|---------------|
| `employment_status` | empty | "Employment status is required." |
| `current_employer` | `employment_status` is `full_time` or `part_time` AND empty | "Current employer is required." |
| `experiences[*].start_date` | missing month or year | "Start date is required." |
| `experiences[*].description` | empty | "Description is required." |
| `experiences[*].ages_served` | empty array | "Please select at least one age group." |

#### Step 4 — Screening Questions (existing + new)

Existing (unchanged):
- `allergic_to_pets === 'yes'` && `allergic_to_what` empty → "Please select which pet you are allergic to."
- `visible_tattoos === 'yes'` && `tattoo_description` empty → "Please describe your tattoos..."
- `has_children === 'yes'` && `children_ages` empty → "Please enter your children's ages."

**New:**
- `cpr_certified === 'yes'` && `cpr_expiration` empty → "CPR expiration date is required."
- `cpr_certified === 'yes'` && `cpr_card` is null → "CPR card upload is required."

**Hard gate (existing):** `authorized_to_work === 'no'` → Next button disabled.

#### Step 5 — References

Each reference (3 total):

| Field | Condition | Error Message |
|-------|-----------|---------------|
| `references[*].first_name` | empty | "Reference first name is required." |
| `references[*].last_name` | empty | "Reference last name is required." |
| `references[*].email` | empty | "Reference email is required." |
| `references[*].phone` | empty | "Reference phone is required." |
| `references[*].relationship` | empty | "Relationship is required." |
| `references[*].years_known` | empty | "Years known is required." |

#### Step 6 — Location & Age Groups

| Field | Condition | Error Message |
|-------|-----------|---------------|
| `location` (group) | `!north_county && !south_east_county && !flexible` | "Please select at least one location." |

Age groups are intentionally not validated (petsitting-only applicants).

#### Step 7 — Qualifications, Activities & Bio

| Field | Condition | Error Message |
|-------|-----------|---------------|
| `bio` | empty | "Bio is required." |

#### Step 8 — Agreements

| Field | Condition | Error Message |
|-------|-----------|---------------|
| `verification.signature` | empty | "Signature is required." |
| `verification.agree` | false | "You must agree to proceed." |
| `agreement.signature` | empty | "Signature is required." |
| `agreement.agree` | false | "You must agree to proceed." |

### Modified Functions

```typescript
const nextStep = () => {
    if (!validateStep(currentStep)) return;
    saveDraft();
    setCurrentStep((prev) => Math.min(prev + 1, 8));
};

const goToStep = (step: number) => {
    if (step > currentStep && !validateStep(currentStep)) return;
    saveDraft();
    setCurrentStep(step);
};

const prevStep = () => {
    saveDraft();
    setCurrentStep((prev) => Math.max(prev - 1, 1));
};
```

### Inline Error Placement

Each validated field gets an inline error below it following the existing pattern:

```tsx
{form.errors['sponsor.first_name'] && (
    <p className="text-sm text-destructive">{form.errors['sponsor.first_name']}</p>
)}
```

Full placement map:

| Step | Field | Placement |
|------|-------|-----------|
| 1 | `sponsor.first_name`, `.last_name`, `.email` | After each Input |
| 1 | `personal.first_name`, `.last_name`, `.email`, `.dob` | After each Input/DatePicker |
| 1 | `personal.phone` | Already handled by PhoneInput `error` prop |
| 1 | `personal.address_line1` | Already handled by AddressAutocomplete |
| 2 | `position` (group) | Below the position checkbox group |
| 3 | `employment_status` | Below the Select |
| 3 | `current_employer` | Below the Input (conditional) |
| 3 | `experiences.*.start_date` | Below the month/year Select pair |
| 3 | `experiences.*.description` | Below the Textarea |
| 3 | `experiences.*.ages_served` | Below the checkbox grid |
| 4 | `cpr_expiration` | Below the DatePicker (new) |
| 4 | `cpr_card` | Below the file Input (new) |
| 5 | `references.*.first_name`, `.last_name`, `.email`, `.relationship` | After each Input |
| 5 | `references.*.phone` | Already handled by PhoneInput `error` prop |
| 5 | `references.*.years_known` | Below the Select |
| 6 | `location` (group) | Below the location checkbox group |
| 7 | `bio` | Below the Textarea |
| 8 | `verification.signature`, `agreement.signature` | After each Input |
| 8 | `verification.agree`, `agreement.agree` | Below each checkbox |

## Backend Changes

### `StoreCaregiverApplicationRequest.php`

```php
// Before (line 76-77):
'cpr_expiration' => 'nullable|date',
'cpr_card' => 'nullable|file|mimes:pdf,jpeg,jpg,png|max:10240',

// After:
'cpr_expiration' => 'nullable|required_if:cpr_certified,yes|date',
'cpr_card' => 'nullable|required_if:cpr_certified,yes|file|mimes:pdf,jpeg,jpg,png|max:10240',
```

## Test Impact

- `StoreCaregiverApplicationRequest` tests that submit with `cpr_certified = 'yes'` but without `cpr_expiration` or `cpr_card` will now fail validation.
- Need to check existing test payloads and add CPR fields where `cpr_certified = 'yes'`.

## Implementation Order

1. Add `validateStep()` function to `wizard.tsx`
2. Modify `goToStep()` and `nextStep()` to call it
3. Add inline error `<p>` tags beneath all validated fields
4. Add `*` markers to Step 2 position, Step 3 ages_served, Step 6 location
5. Update backend validation rules in `StoreCaregiverApplicationRequest.php`
6. Fix any failing tests
7. Run full test suite
