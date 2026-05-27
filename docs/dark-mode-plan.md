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

---

### Step 2: Refactor Custom CSS Classes in `app.css`
*Target File:* [resources/css/app.css](file:///home/adjie/Documents/Project/Portfolio/sitterwise/resources/css/app.css) (Lines 131+)

Currently, several custom CSS classes use hardcoded light-theme colors (like `#ffffff`, `#1b3a5c`, and `#c6e7e7`). To make them theme-aware, refactor their properties to use semantic CSS variables.

#### 1. Secondary Buttons (`.btn-secondary`)
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

#### 2. Clickable Selection Chips (`.chip-label`)
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

#### 3. Segmented Control Selection Pills (`.radio-pill`)
*   **Before:**
    ```css
    .radio-pill {
        background: #ffffff;
        color: #5a6b73;
        border-right: 1px solid #c6e7e7;
    }
    .radio-pill.selected {
        background: #1b3a5c;
        color: #ffffff;
    }
    ```
*   **After (Refactored to variables):**
    ```css
    .radio-pill {
        background: var(--card);
        color: var(--muted-foreground);
        border-right: 1px solid var(--border);
    }
    .radio-pill.selected {
        background: var(--sidebar-primary);
        color: var(--primary-foreground);
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
*   **After (Refactored to variables):**
    ```css
    .form-input {
        color: var(--foreground);
        border: 1px solid var(--border);
        background: var(--background);
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
    Change `bg-white` to `bg-background` (which dynamically adjusts using our css variables):
    ```diff
    - "border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex h-11 w-full min-w-0 rounded-[3px] border bg-white px-3 py-[9px] text-sm shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50",
    + "border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex h-11 w-full min-w-0 rounded-[3px] border bg-background px-3 py-[9px] text-sm shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50",
    ```

---

### Step 4: Audit & Patch Layout Containers
To avoid blinding white sections when switching to dark mode, search the codebase for hardcoded `bg-white` classes in layouts and card components and replace them with semantic theme-aware classes.

1. **Guest Layout Container** (`resources/js/layouts/guest-layout.tsx`)
   * Ensure that the central wrapper card uses `bg-card` instead of `bg-white` to automatically adapt.
2. **Booking Wizard Page** (`resources/js/pages/guest/bookings/create.tsx`)
   * Identify hardcoded `bg-white`, `bg-blush`, and `text-navy` wrappers.
   * Replace them with theme tokens:
     - `bg-white` -> `bg-card`
     - `text-navy` -> `text-foreground`
     - `bg-blush` -> `bg-muted` or `bg-secondary`
3. **Application Wizard Status** (`resources/js/pages/public/caregiver-apply/application-status.tsx`)
   * Replace hardcoded card wrappers:
     - `bg-white shadow` -> `bg-card text-card-foreground border border-border shadow-xs`

---

## 3. How to Run, Test, and Verify

To verify your changes are working perfectly:

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
4. **Run Backend Tests**:
   Ensure no controllers or page rendering endpoints are broken by the UI updates:
   ```bash
   php artisan test --compact
   ```
