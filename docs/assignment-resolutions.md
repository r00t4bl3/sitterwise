# Assignment Resolutions

Every `CaregiverAssignment` tracks the lifecycle of a caregiver's assignment to a booking. The `resolution` field is `null` while the assignment is active, and set to one of the following values when the assignment ends.

## Resolution Values

| Value | Label | Color | Triggered By | Meaning |
|-------|-------|-------|-------------|---------|
| `null` | — | — | — | Actively assigned. Caregiver is expected to work this booking. |
| `completed` | Completed | `#22C55E` (green) | System | Caregiver completed the job. Set automatically during the checkout/payment flow. |
| `backed_out` | Backed Out | `#EF4444` (red) | Caregiver | Caregiver voluntarily backed out via the back-out form. An email is sent to admins. |
| `backed_out_excused` | Backed Out (Excused) | `#F59E0B` (amber) | Admin | Admin excused a caregiver's backout retroactively (e.g., valid reason, emergency). Set via the Excuse button on the caregiver's Job History page. Records who excused it and when. |
| `reassigned` | Reassigned | `#0EA5E9` (teal) | Admin | Admin swapped the caregiver for another. Set automatically when the caregiver is changed via the edit sheet or Replace Caregiver flow. |
| `no_show` | No-Show | `#DC2626` (red) | Admin | Caregiver did not show up for the job. Set via the No-Show button on the caregiver's Job History page. Impacts reliability score. |
| `cancelled_by_sitterwise` | Cancelled by Sitterwise | `#6B7280` (gray) | Admin | Entire booking was cancelled by admin. Any unresolved assignment is resolved to this value. Financial fields are zeroed. |

## Lifecycle

```
                     ┌──────────┐
                     │ assigned │  resolution = null
                     │  (null)  │
                     └────┬─────┘
                          │
            ┌─────────────┼──────────────┬──────────────────┐
            ▼             ▼              ▼                  ▼
      ┌──────────┐ ┌──────────┐ ┌──────────┐      ┌─────────────────┐
      │ backed   │ │reassigned│ │ no_show  │      │   completed     │
      │ _out     │ │          │ │          │      │                 │
      └────┬─────┘ └──────────┘ └──────────┘      └─────────────────┘
           │
           ▼
    ┌──────────────┐
    │backed_out    │
    │_excused      │
    └──────────────┘
```

- **completed**: End of a successful job — reached via checkout/payment.
- **backed_out → backed_out_excused**: Caregiver backs out, admin may optionally excuse it from the Job History page.
- **reassigned**: Admin replaces the caregiver (via edit sheet or Replace Caregiver flow).
- **no_show**: Admin marks the caregiver as a no-show from the Job History page.
- **cancelled_by_sitterwise**: The entire booking is cancelled — supersedes all other resolutions.

### Admin Follow-Up Actions (from Job History)

After a caregiver backs out, the admin can take these actions from the caregiver's **Job History** page (`/caregivers/{id}/jobs`):

| Action | Endpoint | Affects | Reliability Impact |
|--------|----------|---------|-------------------|
| **Excuse** | `POST /assignments/{id}/excuse` | Resolution → `backed_out_excused`, sets `excused_by` + `excused_at` | Queues recalc |
| **No-Show** | `POST /assignments/{id}/no-show` | Resolution → `no_show`, optional note | Queues recalc |
| **Late Arrival** | `POST /assignments/{id}/late-arrival` | Sets `late_arrival_flag = true`, optional note | None |

These actions do **not** change the booking status or `caregiver_id`. They only affect the caregiver assignment resolution.

> **Note:** The caregiver backout itself **does** clear `booking.caregiver_id`. When a caregiver backs out, their assignment is resolved to `backed_out` AND the booking's `caregiver_id` is set to `null`. The booking status stays `confirmed` — it just needs reassignment. For multi-date groups, only the specific booking that was backed out is affected; sibling bookings keep their caregiver.

## Key Rules

- Only **unresolved** (`resolution IS NULL`) assignments can be resolved. Once resolved, an assignment cannot be re-resolved.
- The `backed_out_excused` resolution is set directly via `update()` (not `resolve()`) because it also sets `excused_by` and `excused_at`.
- `reassigned` is set automatically by `Booking::booted()` when `caregiver_id` changes, and will also be set by the explicit Replace Caregiver endpoint.
- `no_show` and `backed_out` both trigger a reliability recalculation via `app:recalculate-reliability`.
