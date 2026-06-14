# Application Certification Verification — ✅ Completed

Add a verify/unverify toggle to certifications on the admin applications show page, with bidirectional sync to the onboarding checklist.

## Problem

The applications show page (`admin/applications/show.tsx`) displays certifications with a read-only `Verified`/`Unverified` badge — no action to change it. The only way to mark a certification as verified is through the onboarding checklist (`toggleChecklistItem`), which is:

- Gated behind `hired_onboarding` application status
- Limited to `cpr_uploaded` → "CPR & First Aid" and `trustline_submitted` → "Trustline"
- Indirect (side effect of completing an onboarding step, not an explicit verify action)

An admin viewing an uploaded certification file at any stage has no inline way to verify it.

## Solution

Add a verify/unverify toggle to each certification card in the applications show page sidebar, backed by a new endpoint. Both paths (checklist toggle & direct toggle) sync with each other to stay consistent.

## Files to modify (3)

| File | Change |
|------|--------|
| `routes/web.php` | Add `POST /applications/{application}/certifications/{certType}/verify` in admin middleware group |
| `app/Http/Controllers/ApplicationController.php` | New `toggleCertificationVerification()` method |
| `resources/js/pages/admin/applications/show.tsx` | Replace read-only badge with toggle + confirmation dialog |

## Backend

### New route

```php
Route::post('/applications/{application}/certifications/{certType}/verify', [ApplicationController::class, 'toggleCertificationVerification'])
    ->name('admin.applications.certifications.verify');
```

### New method

```php
public function toggleCertificationVerification(
    CaregiverApplication $application,
    CertificationType $certType,
    ApplicationActionRequest $request
) {
    $caregiver = $application->caregiver;

    $pivot = $caregiver->certifications()->where('certification_type_id', $certType->id)->first();

    abort_unless($pivot, 422, 'The caregiver does not have this certification type.');

    $wasVerified = $pivot->pivot->verified_at !== null;
    $newValue = $wasVerified ? null : now();

    $caregiver->certifications()->updateExistingPivot($certType->id, [
        'verified_at' => $newValue,
    ]);

    // Sync checklist items (bidirectional consistency)
    $certificationMap = [
        'CPR & First Aid' => 'cpr_uploaded',
        'Trustline' => 'trustline_submitted',
    ];

    if (isset($certificationMap[$certType->name])) {
        $itemKey = $certificationMap[$certType->name];
        $item = $caregiver->onboardingChecklistItems()
            ->where('item_key', $itemKey)
            ->first();

        if ($item) {
            $item->update(['completed_at' => $newValue]);
        }
    }

    return back();
}
```

### Sync logic

Both paths keep each other in sync:

| Action | Direct toggle effect | Checklist sync effect |
|--------|---------------------|----------------------|
| Toggle cert → verified | `verified_at = now()` | `completed_at = now()` on matching item |
| Toggle cert → unverified | `verified_at = null` | `completed_at = null` on matching item |
| Checklist item checked (exist.) | `verified_at = now()` (unchanged) | `completed_at = now()` |
| Checklist item unchecked (exist.) | `verified_at = null` (unchanged) | `completed_at = null` |

The existing `toggleChecklistItem` method stays as-is — it already sets/clears `verified_at`. The new method mirrors that but in the opposite direction (cert → checklist instead of checklist → cert).

## Frontend

### Current (read-only badge)

```
[CPR & First Aid]           [Unverified]
  Expires: June 12, 2027
  View Attachment
```

### Proposed (toggle button + confirmation)

```
[CPR & First Aid]           [✓ Verify]
  Expires: June 12, 2027
  View Attachment
```

When clicked:
1. Confirmation dialog appears: "Mark [Cert Name] as verified?"
2. On confirm, POST to the new endpoint
3. Badge updates to reflect new state
4. Relevant checklist item toggles in sync

After verifying:

```
[CPR & First Aid]         [✓ Verified  |  Unverify]
  Expires: June 12, 2027
  View Attachment
```

- "Unverify" button shows a confirmation: "Unverify [Cert Name]?"
- Works at any application status (not just `hired_onboarding`)
- All certification types can be toggled (not just CPR/Trustline)

## Edge cases

| Case | Handling |
|------|----------|
| Caregiver lacks this cert type | Return 422 with message |
| Verify when already verified | Button shows "Unverify" — noop if clicked verify |
| Unverify when not verified | Button shows "Verify" — noop if clicked unverify |
| Both checklist and direct toggle called | Idempotent — both set same `verified_at` value |
| Certification has no matching checklist item | Toggle still works — just doesn't sync checklist (e.g. Food Handler) |

## Tests

**File:** `tests/Feature/ApplicationCertificationVerificationTest.php`

| Test | Expectation |
|------|-------------|
| Verify an unverified certification | `verified_at` is set, returns 302 |
| Unverify a verified certification | `verified_at` is cleared, returns 302 |
| Verify nonexistent cert type on caregiver | Returns 422 |
| Guest user | Returns 302 (login redirect) |
| Non-admin user | Returns 403 |
| Syncs checklist item when toggled | Matching checklist item `completed_at` matches `verified_at` |
| Does not sync when no matching checklist item | No error, just cert toggles |
