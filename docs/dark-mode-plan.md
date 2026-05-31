# Sitterwise: Dark Mode Implementation Guide

This guide outlines the detailed plan and step-by-step instructions to implement dark-mode theme support throughout the Sitterwise application. The instructions are written to be clear, actionable, and ready for immediate execution by any developer.

---

## 1. Architectural Context (Read First!)

Before writing any styles, it is crucial to understand that **the state-management and theme-toggling infrastructure is already fully implemented**. You do not need to build any theme state providers, database tables, or cookies yourself.

### How the Theme Engine Works:
1. **State Persistence**: The client-side theme is managed by the `useAppearance` hook (`resources/js/hooks/use-appearance.tsx`). When a user selects a theme (Light, Dark, or System), it stores the choice in `localStorage` (for client persistence) and in a cookie named `appearance` (for server rendering).
2. **Anti-Flicker (FOUC) Mechanism**: The `HandleAppearance` middleware (`app/Http/Middleware/HandleAppearance.php`) intercepts every request and shares the cookie value with the root Blade view.
3. **Server-Side Injection**: In `resources/views/app.blade.php`, the server reads the `appearance` value and instantly injects the `.dark` class onto the `<html>` element *before* React compiles or renders. This completely prevents the white "flash of light theme" upon page load.
4. **Theme Targeting**: Tailwind v4 uses `@custom-variant dark (&:is(.dark *));` (configured at the top of `app.css`). This activates standard Tailwind dark utility classes like `dark:bg-slate-900` and `dark:text-white`.

---

## 2. Step-by-Step Implementation Plan

### Step 1: Define Dark Mode CSS Variables in `app.css`
*Target File:* [resources/css/app.css](file:///home/adjie/Documents/Project/Portfolio/sitterwise/resources/css/app.css)

Tailwind CSS v4 is **CSS-first** (no `tailwind.config.js` exists). All variables are set up inside the `@theme` directive, and default values are defined in `:root`.

To implement dark mode, add a `.dark` CSS class block directly underneath the `:root` block to override the design tokens when the `dark` class is present:

```css
/* Add this block directly under the existing :root {} block in resources/css/app.css */
.dark {
    /* Core Layout Colors */
    --background: #0d1520;           /* Sleek, deep-navy background */
    --foreground: #f3f4f6;           /* Soft off-white for body text */
    --card: #152232;                 /* Slightly lighter navy for cards & content areas */
    --card-foreground: #f3f4f6;
    --popover: #152232;
    --popover-foreground: #f3f4f6;

    /* Theme Accent Colors (adjusted for ideal contrast in dark backgrounds) */
    --primary: #f69fa5;              /* Signature coral adjusted for dark contrast */
    --primary-foreground: #0d1520;
    --secondary: #1a2a3e;            /* Dark teal/navy for secondary elements */
    --secondary-foreground: #e5e7eb;
    --muted: #1e3146;                /* Dark muted background for tags/badges */
    --muted-foreground: #9ca3af;     /* Muted gray text */
    --accent: #9ce2e4;               /* Signature soft teal */
    --accent-foreground: #0d1520;
    --destructive: #f87171;          /* Alert red */
    --destructive-foreground: #ffffff;
    
    /* Interactive Elements & Borders */
    --border: #233549;               /* Subtle dark border */
    --input: #233549;                /* Form inputs border */
    --ring: #9ce2e4;

    /* Sidebar Theme Variables */
    --sidebar: #0f1a26;
    --sidebar-foreground: #f3f4f6;
    --sidebar-primary: #f69fa5;
    --sidebar-primary-foreground: #0d1520;
    --sidebar-accent: #1a2a3e;
    --sidebar-accent-foreground: #f3f4f6;
    --sidebar-border: #233549;
    --sidebar-ring: #9ce2e4;

    /* Custom Sitterwise Helper Overrides */
    --color-navy: #f3f4f6;
    --color-navy-light: #e5e7eb;
    --color-sittergray: #9ca3af;
    --color-sittergray-light: #6b7280;
    --color-teal-bg: #1a2a3e;
    --color-blush: #261f21;
    --color-border-teal: #233549;
}
```

> **Note about `--color-blush` and `--color-teal-bg`**: These custom colors are redefined in `.dark` so existing `bg-blush` and `bg-teal-bg` utility classes adapt automatically. No need to replace them with different classes.

---

### Step 2: Refactor Custom CSS Classes in `app.css`
*Target File:* [resources/css/app.css](file:///home/adjie/Documents/Project/Portfolio/sitterwise/resources/css/app.css) (Lines 131+)

Several custom CSS classes use hardcoded light-theme colors. Refactor them to use semantic CSS variables.

#### 1. Primary Buttons (`.btn-primary` + hover)
*   **Before:**
    ```css
    .btn-primary {
        background: #f48a91;
        color: #ffffff;
    }
    .btn-primary:hover {
        background: #e6747c;
    }
    ```
*   **After (Refactored to variables):**
    ```css
    .btn-primary {
        background: var(--primary);
        color: var(--primary-foreground);
    }
    .btn-primary:hover {
        background: var(--primary);
        filter: brightness(0.9);
    }
    ```
    Using `filter: brightness(0.9)` avoids hardcoding a hover variant — it works for both light and dark themes automatically.

#### 2. Secondary Buttons (`.btn-secondary`)
*   **Before:**
    ```css
    .btn-secondary {
        background: #ffffff;
        color: #5a6b73;
        border: 1px solid #c6e7e7;
    }
    ```
*   **After (Refactored to variables):**
    ```css
    .btn-secondary {
        background: var(--card);
        color: var(--muted-foreground);
        border: 1px solid var(--border);
    }
    ```

#### 3. Clickable Selection Chips (`.chip-label`)
*   **Before:**
    ```css
    .chip-label {
        color: #1b3a5c;
        background: #fafbfc;
        border: 1px solid #e0e8ec;
    }
    .chip-label:hover {
        border-color: #84d0d2;
        background: #e8f5f5;
    }
    .chip-label.selected {
        background: #e8f5f5;
        border-color: #84d0d2;
        color: #1b3a5c;
    }
    ```
*   **After (Refactored to variables):**
    ```css
    .chip-label {
        color: var(--foreground);
        background: var(--background);
        border: 1px solid var(--border);
    }
    .chip-label:hover {
        border-color: var(--ring);
        background: var(--secondary);
    }
    .chip-label.selected {
        background: var(--secondary);
        border-color: var(--ring);
        color: var(--foreground);
    }
    ```

#### 4. Legacy Global Form Inputs (`.form-input`)
*   **Before:**
    ```css
    .form-input {
        color: #1b3a5c;
        border: 1px solid #c6e7e7;
        background: #ffffff;
    }
    ```
*   **After:**
    ```css
    .form-input {
        color: var(--foreground);
        border: 1px solid var(--border);
        background: var(--background);
    }
    ```
*   Also update `:focus` and `::placeholder`:
    ```css
    .form-input:focus {
        border-color: var(--ring);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--ring) 20%, transparent);
    }
    .form-input::placeholder {
        color: var(--muted-foreground);
    }
    ```

---

### Step 3: Make Shared UI Input Adaptable
*Target File:* [resources/js/components/ui/input.tsx](file:///home/adjie/Documents/Project/Portfolio/sitterwise/resources/js/components/ui/input.tsx)

The standard text input component has a hardcoded `bg-white` class which stays bright white even in dark mode. 

*   **Before:**
    ```tsx
    className={cn(
      "border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex h-11 w-full min-w-0 rounded-[3px] border bg-white px-3 py-[9px] text-sm shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50",
      ...
    )}
    ```
*   **After:**
    Change `bg-white` to `bg-background`:
    ```diff
    - ...rounded-[3px] border bg-white px-3...
    + ...rounded-[3px] border bg-background px-3...
    ```

---

### Step 4: Audit & Patch Layout Containers
Search the codebase for hardcoded `bg-white` classes in layouts and card components and replace them with semantic theme-aware classes. A `rg 'bg-white' resources/js/ --include '*.tsx'` search reveals ~30 occurrences across 16+ files. The critical ones are listed below.

#### 4.1 Guest Layout Container
*Target File:* `resources/js/layouts/guest-layout.tsx`
*   The main area uses `bg-blush` — this adapts automatically since the `.dark` block redefines `--color-blush`. No change needed.
*   Other containers already use `bg-background` and `bg-card` — no changes needed.

#### 4.2 Booking Wizard Page
*Target File:* `resources/js/pages/guest/bookings/create.tsx`
*   Replace hardcoded colors with theme variables:
    | Current | Replacement | Lines |
    |---|---|---|
    | `bg-white` | `bg-card` | 984 |
    | `bg-[#FDFCFA]` | `bg-card` | 895 |
    | `text-navy` | `text-foreground` | ~20 occurrences |
    | `text-sittergray` | `text-muted-foreground` | ~15 occurrences |
    | `border-border-teal` | `border-border` | ~6 occurrences |
    | `text-coral` | `text-primary` | ~15 occurrences |
*   The `bg-blush` and `bg-teal-bg` areas adapt automatically via `.dark` variable overrides — keep them as-is.

#### 4.3 Application Status Page
*Target File:* `resources/js/pages/public/caregiver-apply/application-status.tsx`
*   Replace hardcoded card wrappers:
    - `bg-white shadow rounded-lg` → `bg-card text-card-foreground border border-border shadow-xs rounded-lg`
    - `bg-gray-50` (page background) → `bg-background`
    - `text-gray-900` → `text-foreground`
    - `text-gray-500`, `text-gray-600` → `text-muted-foreground`
    - `border-gray-200` → `border-border`

#### 4.4 Sidebar Components
Four sidebar component files exist but were built with `--sidebar-*` CSS variables already in mind:
- `resources/js/components/app-sidebar.tsx`
- `resources/js/components/app-sidebar-header.tsx`
- `resources/js/layouts/app/app-sidebar-layout.tsx`
- `resources/js/components/ui/sidebar.tsx`

These should work automatically once the `.dark` block defines the `--sidebar-*` variables (Step 1). Verify they render correctly in dark mode rather than proactively refactoring.

#### 4.5 Remaining Files (Broader Audit)
The following files also contain `bg-white` and should be patched if they render in dark-mode contexts:

| File | Occurrences |
|---|---|
| `pages/admin/clients/edit.tsx` | 4 |
| `pages/welcome.tsx` | 4 |
| `pages/guest/bookings/payment.tsx` | 2 |
| `pages/public/references/submit.tsx` | 2 |
| `pages/public/caregiver-bio.tsx` | 1 |
| `pages/admin/bookings/index.tsx` | 1 |
| `pages/public/caregiver-apply/wizard.tsx` | 1 |
| `pages/public/caregiver-apply/thank-you.tsx` | 1 |
| `pages/public/references/submitted.tsx` | 1 |
| `components/app-header.tsx` | 1 |
| `components/booking-progress.tsx` | 1 |
| `components/availability-calendar.tsx` | 1 |
| `components/two-factor-setup-modal.tsx` | 1 |
| `components/appearance-tabs.tsx` | 1 |

For each, replace `bg-white` with `bg-card` (card containers) or `bg-background` (page backgrounds) and add `border border-border` where a visual boundary is needed.

---

### Step 5: Verify Sidebar Components Use Theme Variables
*Target Files:* Sidebar component files listed in 4.4

Open each sidebar file and check that `--sidebar-*` variable names are used for colors (e.g., `bg-sidebar`, `text-sidebar-foreground`, `border-sidebar-border`). If any hardcoded `bg-white` or `text-navy` classes exist, replace them with the sidebar-specific tokens. This step is verification only — the components likely already use the correct tokens since they were generated with theme support.

---

### Step 6: Add Pest Feature Test for `HandleAppearance` Middleware
*Target File:* `tests/Feature/HandleAppearanceTest.php`

This test verifies the middleware correctly reads the `appearance` cookie and shares it with the Blade view (which controls the `.dark` class injection and the inline script value).

Create the file:
```php
<?php

test('appearance middleware applies dark class and script when cookie is dark', function () {
    $response = $this->withCookie('appearance', 'dark')->get('/login');

    $response->assertSuccessful();
    $response->assertSee('class="dark"', false);
    $response->assertSee("appearance = 'dark'", false);
});

test('appearance middleware defaults to system when no cookie is set', function () {
    $response = $this->get('/login');

    $response->assertSuccessful();
    $response->assertDontSee('class="dark"', false);
    $response->assertSee("appearance = 'system'", false);
});

test('appearance middleware applies light mode when cookie is light', function () {
    $response = $this->withCookie('appearance', 'light')->get('/login');

    $response->assertSuccessful();
    $response->assertDontSee('class="dark"', false);
    $response->assertSee("appearance = 'light'", false);
});
```

Run the test to confirm it passes:
```bash
php artisan test --compact --filter=HandleAppearance
```

---

## 3. How to Run, Test, and Verify

### Automated Tests
After implementing, run all tests to ensure nothing is broken:
```bash
php artisan test --compact
```

Run just the appearance middleware test for quick feedback during development:
```bash
php artisan test --compact --filter=HandleAppearance
```

### Manual Verification
1. **Run the Vite Development Server**:
   ```bash
   npm run dev
   ```
2. **Access the Theme Controls**:
   * Open the application in your browser.
   * Navigate to the **Appearance Settings** page: `/settings/appearance`.
   * Click **Dark** or **System** (with your OS theme set to Dark).
3. **Verify the Theme Transition**:
   * Confirm that the `.dark` class has been added to the root `<html>` element in the Inspector.
   * Verify that all background areas transition to the deep navy color scheme, borders remain legible, and all text is readable.
4. **Toggle Between Themes**:
   * Switch between Light, Dark, and System modes.
   * Verify the transition is smooth (no white flash).
   * Refresh the page in each mode to confirm the anti-flicker (FOUC) mechanism works.
5. **Check Specific Components**:
   * Verify `.btn-primary` and `.btn-secondary` buttons look correct in both themes.
   * Verify form inputs (`Input` component and `.form-input` class) are readable.
   * Check that sidebar components render correctly with `--sidebar-*` variables.
6. **Edge Cases**:
   * Test without a cookie (first visit) — should default to System.
   * Test with an invalid cookie value — should fall back gracefully.
