# Browser Testing Guide

## Form Interactions

Playwright's `click()` and `hover()` methods time out on this application — the actionability checks (stability, pointer-interception) never complete on Inertia React pages served through the Amp HTTP server. `fill()` and `clear()` work fine.

**Use the `script()`-based helpers** from `tests/Browser/helpers.php` for all form interactions:

```php
// Fill an input field
fillField($page, '#email', 'user@example.com');

// Click a button or link
clickElement($page, 'button[data-test="submit-button"]');

// Full login sequence
loginViaJs($page, 'user@example.com', 'password');
```

The `setNativeValue` pattern sets the raw `value` property on HTMLInputElement and dispatches an `input` event, which both controlled (React `value`+`onChange`) and uncontrolled (`defaultValue`+`name`) inputs respond to:

```php
$page->script(<<<JS
    const el = document.querySelector('#email');
    const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
    setter.call(el, 'user@example.com');
    el.dispatchEvent(new Event('input', { bubbles: true }));
JS);
```

## Root Cause: Playwright Actionability Checks

`click()` and `hover()` time out because Playwright's actionability checks (element stability, pointer interception) never complete. `fill()` works because it uses a different internal mechanism (dipatches `input` events on the element without requiring it to be "clickable").

The fix is adding `'force' => true` to `Locator::click()` in the vendor plugin, but this gets wiped on `composer update`. The `script()` workaround is more reliable.

## URL Assertions

Use `assertPathIs()` — NOT `assertUrlIs()`:

```php
// ✅ Correct
$page->assertPathIs('/dashboard');

// ❌ Bug: compares full URL (http://127.0.0.1:PORT/path) against path-only regex
$page->assertPathIs('/dashboard');
```

`assertUrlIs()` reconstructs the full URL then compares it against a path-only RegExp pattern, causing false failures.

## Password Confirmation Middleware

`/settings/security` (and other routes behind Fortify's `password.confirm` middleware) require the password to be recently confirmed. Bypass for tests:

```php
$user = User::factory()->create();
$this->actingAs($user);
session()->put('auth.password_confirmed_at', time());

visit('/settings/security')->assertSee('Update password');
```

Without this, the page redirects to `/user/confirm-password` and expected text won't be found.

## Model Requirements

Many pages require the corresponding Eloquent model alongside the User. Without these, the page may 500 or render empty:

| User Role | Model Required | Pages Affected |
|-----------|---------------|----------------|
| `client` | `Client` | `/dashboard`, `/bookings`, `/bookings/create` |
| `caregiver` | `Caregiver` | `/dashboard`, `/jobs`, `/bookings` |

Use the shared helpers — they create both User and the required model:

```php
$client = createClientUser();    // User + Client
$caregiver = createCaregiver();  // User + Caregiver
```

For admin/super_admin, just set the role directly:

```php
$admin = User::factory()->create(['role' => 'admin']);
$superAdmin = User::factory()->create(['role' => 'super_admin']);
```

## Shared Helpers (`tests/Browser/helpers.php`)

Available globally in all Browser test files:

| Function | Purpose |
|----------|---------|
| `fillField($page, $selector, $value)` | Fill an input via `setNativeValue` |
| `clickElement($page, $selector)` | Click an element via `script()` |
| `loginViaJs($page, $email, $password)` | Login form fill + submit |
| `createClientUser()` | Create User (role=client) + Client model |
| `createCaregiver()` | Create User (role=caregiver) + Caregiver model |
| `setNativeValueJs()` | Returns the JS snippet for manual use |

Auto-loaded via `require_once` in `tests/Pest.php`.

## Playwright Version

The installed Playwright version must be compatible with the pest-plugin-browser.

| Version | Status |
|---------|--------|
| 1.60.0 | ✅ Works (with `script()` workaround) |
| 1.54.1 | ❌ Hangs the test process (plugin's target version) |

## Database Isolation

Browser tests use SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` from `phpunit.xml`). If `php artisan config:cache` or `php artisan optimize` was run in the dev environment, the cached config contains the `.env` database settings (MySQL). The browser tests would then run against **and truncate** the dev database.

**Always run `php artisan optimize:clear` or `php artisan config:clear` before running tests** after any deployment or optimization command.

## Full Suite Stability

Running all 39+ browser tests sequentially can cause intermittent failures from Playwright server process buildup. Symptoms:

- Tests that pass individually fail when run in the full suite
- Playwright actions take abnormally long (6-12s instead of 1-2s)
- Different tests fail on different runs

**Mitigations:**

1. Kill leftover processes before each run:
   ```bash
   pkill -f "playwright run-server"
   pkill -f "chromium"
   ```

2. Run tests in smaller batches for CI:
   ```bash
   php artisan test --testsuite=Browser --filter='Auth'
   php artisan test --testsuite=Browser --filter='Settings'
   php artisan test --testsuite=Browser --filter='Smoke'
   ```

3. Consider test sharding in CI pipelines to isolate each test in its own process.
