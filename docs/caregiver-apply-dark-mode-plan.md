# Dark Mode Fix: `public/caregiver-apply/` Pages

## Problem

All four pages in `resources/js/pages/public/caregiver-apply/` use hardcoded light-only Tailwind classes (e.g. `bg-gray-50`, `bg-white`, `text-gray-600`, `text-gray-900`) that don't respond to the `.dark` class on `<html>`. When dark mode is active, the pages render with white backgrounds and dark-gray text on a dark page, making them unreadable or visually broken.

## Approach

Replace hardcoded color classes with the project's **existing semantic tokens** (`bg-background`, `text-foreground`, `bg-card`, `border-border`, `bg-muted`, `text-muted-foreground`, etc.). These tokens are defined as CSS custom properties in `resources/css/app.css` and automatically flip under `.dark` — no `dark:` variant classes needed.

This matches the pattern used by other well-implemented pages like `guest/bookings/create.tsx`.

## Token Mapping Reference

| Hardcoded Class | Semantic Replacement | Notes |
|---|---|---|
| `bg-gray-50` | `bg-background` | Page background |
| `bg-white` | `bg-card` | Card/form surfaces |
| `text-gray-900` | `text-foreground` | Primary text |
| `text-gray-700` | `text-foreground` | Labels, body text |
| `text-gray-600` | `text-muted-foreground` | Descriptions, hints |
| `bg-gray-200` | `bg-muted` | Progress bar track, inactive indicators |
| `bg-gray-300` | `bg-muted` | Inactive step circles |
| `text-gray-600` (on steps) | `text-muted-foreground` | Inactive step text |
| `border-gray-300` | `border-border` | Borders on form sections |
| `shadow` | `shadow-xs` | Consistent with other cards |
| `bg-red-50` / `border-red-200` / `text-red-700` | `bg-destructive/10 border-destructive text-destructive` | Error states |
| `text-red-500` (asterisks) | `text-destructive` | Required field markers |
| `bg-teal-50` | `bg-secondary` | Checked/active checkbox labels |
| `border-teal-500` | `border-accent` | Checked/active checkbox borders |
| `bg-gray-100` | `bg-muted` | Disabled "Present" field |
| `bg-green-50` / `border-green-200` / `text-green-700` | `bg-accent/10 border-accent text-accent-foreground` or keep with `dark:` overrides | Success/completed states |
| `text-amber-600` | `text-yellow-600 dark:text-yellow-400` | Signature mismatch warning |
| `bg-coral` / `text-coral` | Already semantic — keep as-is | Brand color, dark-mapped |

## File-by-File Changes

### 1. `wizard.tsx` (2785 lines) — **Major**

This is the largest file and has the most hardcoded classes. Changes span the entire file.

**Page shell:**
- `bg-gray-50` → `bg-background`

**Header area:**
- `text-navy` → `text-foreground` (navy maps to foreground in dark mode already, but `text-foreground` is the canonical semantic)
- `text-gray-600` (subtitle) → `text-muted-foreground`

**Progress bar:**
- Step circle inactive: `bg-gray-300 text-gray-600` → `bg-muted text-muted-foreground`
- Progress track: `bg-gray-200` → `bg-muted`
- Step counter text: `text-gray-600` → `text-muted-foreground`

**Form card:**
- `bg-white` → `bg-card`
- Add `border border-border` to match the card pattern used elsewhere
- `shadow` → `shadow-xs`

**Error banner:**
- `border-red-200 bg-red-50 text-red-700` → `border-destructive bg-destructive/10 text-destructive`

**Section descriptions (throughout all steps):**
- `text-gray-600` → `text-muted-foreground`

**Checkbox labels (position, availability, qualifications, agreements):**
- Unchecked: `bg-gray-50` → `bg-background` (or `bg-card`)
- Checked: `border-teal-500 bg-teal-50` → `border-accent bg-secondary`

**Experience cards (step 3):**
- `border-gray-300` → `border-border`
- `bg-gray-100` (disabled "Present" field) → `bg-muted`

**Reference cards (step 5):**
- `border-gray-300` (on `rounded border p-4`) → `border-border`

**Qualification cards (step 7):**
- Same checkbox pattern as above

**Agreement sections (step 8):**
- Same checkbox pattern as above
- `text-amber-600` (signature mismatch) → keep or add `dark:text-yellow-400`

**Navigation buttons:**
- `bg-coral text-white` — already semantic, keep as-is

### 2. `application-status.tsx` — **Minor**

Already mostly correct (uses `bg-background`, `text-foreground`, `bg-card`, `border-border`, `text-muted-foreground`). Remaining issues:

- `border-green-200 bg-green-50` (completed checklist items & references) → `border-accent bg-secondary` or add `dark:border-green-800 dark:bg-green-950`
- `bg-green-100 text-green-700` (completed badges) → keep with `dark:bg-green-900 dark:text-green-300`
- `border-green-500 bg-green-500` (checkmark circle) — keep as-is, green on green works in both modes
- `bg-teal-bg border-border-teal` (help section) — already semantic, keep as-is

### 3. `thank-you.tsx` — **Medium**

- `bg-gray-50` → `bg-background`
- `bg-white` + `shadow` → `bg-card shadow-xs border border-border`
- `text-gray-900` → `text-foreground`
- `text-gray-600` → `text-muted-foreground`
- `bg-green-100` (checkmark circle) — keep or add `dark:bg-green-900`
- `text-green-600` — keep or add `dark:text-green-400`
- `bg-coral` button — keep as-is

### 4. `verify-email.tsx` — **Medium**

- `bg-gray-50` → `bg-background`
- `text-gray-900` → `text-foreground`
- `text-gray-600` → `text-muted-foreground`
- `text-gray-700` (labels) → `text-foreground`
- `text-red-600` (error messages) → `text-destructive`
- `text-green-600` (success message) — keep or add `dark:text-green-400`

## Order of Work

1. **`verify-email.tsx`** — smallest file, quick win, good test of the approach
2. **`thank-you.tsx`** — small static page, quick win
3. **`application-status.tsx`** — mostly done, just green-state fixes
4. **`wizard.tsx`** — largest file, do last

## Verification

After all changes:
1. Toggle dark mode via `/settings/appearance` (or manually add `.dark` to `<html>`)
2. Visually verify each page in both light and dark mode
3. Run `npm run build` to ensure no build errors
4. Run `php artisan test --compact` to ensure no regressions
