# Push Notifications Plan

**Tech Stack:** Laravel, React, Inertia.js, Vite, `laravel-notification-channels/webpush`
**Last Updated:** June 2026

## Overview

Implement PWA push notifications using the standard Web Push protocol (VAPID). The `laravel-notification-channels/webpush` package provides a native Laravel notification channel that plugs directly into existing notification `via()` arrays.

The plan also includes a **small admin test feature** — a "Test Push" button on the Settings > Profile page so admins can verify push delivery to their own device.

### Critical Constraints

- Service Workers require **HTTPS** in production (`localhost` exempt)
- iOS Safari: Push only works if PWA is **added to Home Screen** + iOS 16.4+
- All payloads must be **user-visible** (`userVisibleOnly: true`)
- `VAPID_PRIVATE_KEY` must never be exposed to the frontend

---

## 1. Backend: Package & Infrastructure

### A. Install Dependencies

```bash
composer require laravel-notification-channels/webpush
npm i vite-plugin-pwa
```

### B. Publish Migration & Config

```bash
php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider" --tag="migrations"
php artisan migrate
```

Creates `push_subscriptions` table (columns: `id`, `user_id`, `endpoint`, `public_key`, `auth_token`, `timestamps`).

### C. Generate VAPID Keys

```bash
php artisan webpush:vapid
```

Add to `.env`:

```env
VAPID_PUBLIC_KEY=your_public_key
VAPID_PRIVATE_KEY=your_private_key
VAPID_SUBJECT=mailto:admin@yourdomain.com
```

### D. Add Trait to User Model

```php
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use HasPushSubscriptions;
    // ...
}
```

### E. Inertia Shared Props

In `app/Http/Middleware/HandleInertiaRequests.php`:

```php
public function share(Request $request): array
{
    return [
        // ... existing shares
        'vapid_public_key' => config('webpush.vapid.public_key'),
        'supports_push' => config('app.env') === 'local' || $request->secure(),
    ];
}
```

### F. PushSubscriptionController + Route

```php
// app/Http/Controllers/PushSubscriptionController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => 'required|url',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        $request->user()->pushSubscriptions()
            ->updateOrCreate(
                ['endpoint' => $validated['endpoint']],
                [
                    'public_key' => $validated['keys']['p256dh'],
                    'auth_token' => $validated['keys']['auth'],
                ]
            );

        return response()->json(['status' => 'success'], 200);
    }
}
```

```php
// routes/web.php
use App\Http\Controllers\PushSubscriptionController;

Route::middleware(['auth'])->group(function () {
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])
        ->name('push-subscriptions.store');
});
```

### G. Scheduled Cleanup

In `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('webpush:clean')->daily();
```

---

## 2. Admin Test Push Feature

A simple card on the existing Settings > Profile page where admins can subscribe to push and send a test notification to themselves.

### A. TestPush Notification Class

```php
// app/Notifications/TestPush.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class TestPush extends Notification
{
    use Queueable;

    public function __construct(
        public string $title = 'Test Notification',
        public string $body = 'This is a test push from Sitterwise!',
        public string $url = '/'
    ) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return WebPushMessage::create()
            ->title($this->title)
            ->body($this->body)
            ->icon('/icon-192.png')
            ->badge('/icon-72.png')
            ->data(['url' => $this->url])
            ->options(['TTL' => 300, 'urgency' => 'normal']);
    }
}
```

### B. Controller

Create a lightweight controller for the settings test-push action:

```php
// app/Http/Controllers/Settings/PushTestController.php
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Notifications\TestPush;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PushTestController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->pushSubscriptions()->count() === 0) {
            return back()->with('push_test_error', 'No push subscription found. Subscribe to push notifications first.');
        }

        $user->notify(new TestPush(
            title: 'Test Notification',
            body: 'Push notifications are working!',
            url: '/settings/profile',
        ));

        return back()->with('push_test_sent', true);
    }
}
```

### C. Route

Add to `routes/settings.php`:

```php
use App\Http\Controllers\Settings\PushTestController;

Route::post('settings/push-test', PushTestController::class)
    ->name('settings.push-test');
```

### D. Frontend Component

Add a new section on `resources/js/pages/settings/profile.tsx`. The component:

1. Reads `supports_push` and `vapid_public_key` from Inertia shared props
2. Shows a card with:
   - Push support status (supported / not supported)
   - Subscription status (subscribed / not subscribed)
   - A "Subscribe to Push" button (calls the `usePushSubscription` hook)
   - A "Send Test Push" button (POSTs to `/settings/push-test`)
   - Success/error feedback from flash messages

```tsx
// Inside settings/profile.tsx, after the profile form section
{/* Push Notification Test */}
<div className="space-y-4">
    <Heading
        variant="small"
        title="Push Notifications"
        description="Test push notification delivery to your device"
    />

    {auth.user.push_subscribed && (
        <form
            onSubmit={async (e) => {
                e.preventDefault();
                const form = e.currentTarget;
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': (window as any).csrfToken },
                });
                if (response.ok) {
                    // reload to show flash message
                    window.location.reload();
                }
            }}
            action={route('settings.push-test')}
        >
            <Button type="submit">Send Test Push</Button>
        </form>
    )}

    {!auth.user.push_subscribed && props.supports_push && (
        <Button onClick={subscribeToPush}>
            Enable Push Notifications
        </Button>
    )}
</div>
```

A cleaner approach: create a dedicated `PushTestCard` component that encapsulates all the logic:

```tsx
// resources/js/components/push-test-card.tsx
import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import Heading from '@/components/heading';
import { usePushSubscription } from '@/hooks/use-push-subscription';

export default function PushTestCard() {
    const { props } = usePage();
    const { isSupported, subscription, loading, subscribe } = usePushSubscription();
    const [testSending, setTestSending] = useState(false);

    if (!isSupported) return null;

    const handleSendTest = async () => {
        setTestSending(true);
        router.post('/settings/push-test', {}, {
            preserveScroll: true,
            onFinish: () => setTestSending(false),
        });
    };

    return (
        <div className="space-y-4">
            <Heading
                variant="small"
                title="Push Notifications"
                description="Test push notification delivery to your device"
            />

            {subscription ? (
                <Button onClick={handleSendTest} disabled={testSending}>
                    {testSending ? 'Sending...' : 'Send Test Push'}
                </Button>
            ) : (
                <Button onClick={subscribe} disabled={loading}>
                    {loading ? 'Enabling...' : 'Enable Push Notifications'}
                </Button>
            )}
        </div>
    );
}
```

Render in `settings/profile.tsx`:

```tsx
// After the profile form section, before DeleteUser
<PushTestCard />
```

### E. Subscription State on User

Expose subscription state via Inertia. Add an accessor or query to `HandleInertiaRequests::share()`:

```php
'push_subscribed' => $request->user() && $request->user()->pushSubscriptions()->count() > 0,
```

Or add it to the `auth.user` share. The simpler approach: `handleInertiaRequests.php` already shares `auth.user`. Add a `push_subscribed` attribute to the `User` resource or append as a computed prop:

```php
// HandleInertiaRequests.php
return [
    // ... existing shares
    'push_subscribed' => $request->user()?->pushSubscriptions()->count() > 0,
];
```

---

## 3. Frontend: Push Subscription Hook

```tsx
// resources/js/hooks/use-push-subscription.ts
import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';

export function usePushSubscription() {
    const { props } = usePage<{ vapid_public_key?: string; supports_push?: boolean }>();
    const [subscription, setSubscription] = useState<PushSubscription | null>(null);
    const [permission, setPermission] = useState<NotificationPermission>(Notification?.permission || 'default');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const isSupported = 'serviceWorker' in navigator && 'PushManager' in window && !!props.supports_push;

    useEffect(() => {
        if (!isSupported) return;
        navigator.serviceWorker.ready
            .then(reg => reg.pushManager.getSubscription())
            .then(setSubscription)
            .catch(console.warn);
    }, [isSupported]);

    const subscribe = async () => {
        if (!isSupported) { setError('Push not supported'); return false; }
        setLoading(true);
        setError(null);

        try {
            const perm = await Notification.requestPermission();
            setPermission(perm);
            if (perm !== 'granted') throw new Error('Permission denied');

            const reg = await navigator.serviceWorker.ready;
            const existing = await reg.pushManager.getSubscription();
            if (existing) { setSubscription(existing); return true; }

            const newSub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(props.vapid_public_key!),
            });

            await router.post('/push-subscriptions', JSON.parse(JSON.stringify(newSub)), {
                preserveScroll: true,
                onSuccess: () => setSubscription(newSub),
                onError: () => { throw new Error('Failed to save subscription'); },
            });

            return true;
        } catch (err) {
            setError((err as Error).message);
            return false;
        } finally {
            setLoading(false);
        }
    };

    return { isSupported, permission, subscription, loading, error, subscribe };
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
    return outputArray;
}
```

---

## 4. Frontend: PWA Install Flow

### A. Hook

```tsx
// resources/js/hooks/use-pwa-install.ts
import { useState, useEffect } from 'react';

export function usePWAInstall() {
    const [deferredPrompt, setDeferredPrompt] = useState<any>(null);
    const [isInstallable, setIsInstallable] = useState(false);
    const [isIOS, setIsIOS] = useState(false);
    const [isStandalone, setIsStandalone] = useState(false);

    useEffect(() => {
        const ios = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
        setIsIOS(ios);

        const standalone = window.matchMedia('(display-mode: standalone)').matches || (window.navigator as any).standalone === true;
        setIsStandalone(standalone);

        const handler = (e: Event) => { e.preventDefault(); setDeferredPrompt(e); setIsInstallable(true); };
        window.addEventListener('beforeinstallprompt', handler);

        return () => window.removeEventListener('beforeinstallprompt', handler);
    }, []);

    const promptInstall = async () => {
        if (!deferredPrompt) return false;
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        setDeferredPrompt(null);
        setIsInstallable(false);
        return outcome === 'accepted';
    };

    return {
        isInstallable,
        isIOS,
        isStandalone,
        promptInstall,
        iosInstructions: [
            'Tap the Share button in Safari',
            'Scroll down & tap "Add to Home Screen"',
            'Tap "Add" to confirm',
        ],
    };
}
```

### B. Component

```tsx
// resources/js/components/pwa-install-prompt.tsx
import { useState } from 'react';
import { usePWAInstall } from '@/hooks/use-pwa-install';

export default function PWAInstallPrompt() {
    const { isInstallable, isIOS, isStandalone, promptInstall, iosInstructions } = usePWAInstall();
    const [dismissed, setDismissed] = useState(() => localStorage.getItem('pwa-install-dismissed') === 'true');

    if (isStandalone || !isInstallable || dismissed) return null;

    return (
        <div className="fixed bottom-4 right-4 z-50 w-full max-w-sm">
            <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl p-4 relative">
                <button onClick={() => { setDismissed(true); localStorage.setItem('pwa-install-dismissed', 'true'); }}
                    className="absolute top-3 right-3 text-gray-400 hover:text-gray-600" aria-label="Dismiss">
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                {!isIOS ? (
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Install App</h3>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Add to home screen for faster access and push notifications.
                        </p>
                        <button onClick={promptInstall}
                            className="mt-4 w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition">
                            Install Now
                        </button>
                    </div>
                ) : (
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Install on iPhone/iPad</h3>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">To enable push notifications:</p>
                        <ol className="mt-2 text-sm text-gray-600 dark:text-gray-300 space-y-1 list-decimal list-inside">
                            {iosInstructions.map((step, i) => <li key={i}>{step}</li>)}
                        </ol>
                    </div>
                )}
            </div>
        </div>
    );
}
```

### C. Placement

Render `PWAInstallPrompt` in the main app layout (`resources/js/layouts/app-sidebar-layout.tsx`) as a floating banner. It only renders when the browser supports installation and the user hasn't dismissed it.

---

## 5. Service Worker

```javascript
// resources/js/sw.js
self.addEventListener('push', (event) => {
    if (!event.data) return;
    try {
        const data = event.data.json();
        event.waitUntil(
            self.registration.showNotification(data.title || 'New Notification', {
                body: data.body || '',
                icon: data.icon || '/icon-192.png',
                badge: data.badge || '/icon-72.png',
                data: { url: data.url || '/', ...data.data },
                actions: data.actions || [],
                tag: data.tag || 'default',
                renotify: !!data.renotify,
            })
        );
    } catch {
        event.waitUntil(
            self.registration.showNotification('New Message', {
                body: 'Tap to view',
                icon: '/icon-192.png',
                data: { url: '/' },
            })
        );
    }
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clientsList => {
                const client = clientsList.find(c => c.url === url && 'focus' in c);
                return client ? client.focus() : clients.openWindow(url);
            })
    );
});

self.addEventListener('pushsubscriptionchange', () => {
    console.warn('Push subscription changed/expired. Re-subscribe needed.');
});
```

---

## 6. Vite & Manifest Configuration

In `vite.config.ts`:

```typescript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.tsx'], refresh: true }),
        react(),
        tailwindcss(),
        wayfinder(),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.js',
            registerType: 'autoUpdate',
            manifest: {
                name: 'Sitterwise',
                short_name: 'Sitterwise',
                start_url: '/',
                display: 'standalone',
                background_color: '#ffffff',
                theme_color: '#000000',
                icons: [
                    { src: '/icon-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/icon-512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
        }),
    ],
});
```

### Icon Assets Needed

Generate and place in `public/`:
- `public/icon-72.png` (badge icon)
- `public/icon-192.png` (home screen icon)
- `public/icon-512.png` (splash screen icon)

Source from the existing `public/sitterwise.png` logo, resized to the required dimensions.

---

## 7. Implementation Roadmap

### Phase 1: Infrastructure (3-4h)
- [ ] `composer require laravel-notification-channels/webpush`
- [ ] `npm i vite-plugin-pwa`
- [ ] Publish migration + config, run `php artisan migrate`
- [ ] Generate VAPID keys, add to `.env`
- [ ] Add `HasPushSubscriptions` trait to `User` model
- [ ] Create `PushSubscriptionController` + auth route
- [ ] Share `vapid_public_key`, `supports_push`, `push_subscribed` via Inertia
- [ ] Configure `VitePWA` plugin in `vite.config.ts`
- [ ] Create `resources/js/sw.js`
- [ ] Add `webpush:clean` to scheduler
- [ ] Generate and place 3 icon PNGs in `public/`

### Phase 2: Frontend Hooks & Components (2-3h)
- [ ] Create `hooks/use-push-subscription.ts`
- [ ] Create `hooks/use-pwa-install.ts`
- [ ] Create `components/pwa-install-prompt.tsx`
- [ ] Render `PWAInstallPrompt` in app sidebar layout

### Phase 3: Admin Test Push Feature (1-2h)
- [ ] Create `app/Notifications/TestPush.php`
- [ ] Create `app/Http/Controllers/Settings/PushTestController.php`
- [ ] Add `POST /settings/push-test` route to `routes/settings.php`
- [ ] Create `components/push-test-card.tsx`
- [ ] Render `PushTestCard` on `settings/profile.tsx`

### Phase 4: Testing & Integration (2-3h)
- [ ] Test push on Chrome Desktop (localhost)
- [ ] Test push on Android (via tunnel for HTTPS)
- [ ] Test push on iOS 16.4+ (add to Home Screen + allow notifications)
- [ ] Test install prompt (Chrome, Samsung Internet)
- [ ] Verify `webpush:clean` runs on schedule
- [ ] Add fallback UI for unsupported browsers
- [ ] Run full test suite: `php artisan test --compact`

---

## 8. Estimated Effort

| Phase | Items | Hours |
|---|---|---|
| Infrastructure | 11 items | 3-4h |
| Frontend components | 4 items | 2-3h |
| Admin test push feature | 5 items | 1-2h |
| Testing & integration | 7 items | 2-3h |
| **Total** | **27 items** | **8-12h** (~1.5 days) |

---

## 9. Notifications That Should Use Push

Once the infrastructure is in place, add `WebPushChannel::class` to existing notification `via()` methods. These are the most impactful candidates:

| Notification | Current Channels | Add Push? | Priority |
|---|---|---|---|
| `BookingAcceptedNotification` (client) | database, mail, sms | Yes | High |
| `BookingInvitationNotification` (caregiver) | database, mail | Yes | High |
| `BookingReminderNotification` (caregiver) | database, mail | Yes | High |
| `BookingCreatedNotification` (client) | database, mail | Yes | Medium |
| `BookingCancelledNotification` | mail | Yes | Medium |
| `BookingReceiptNotification` | database, mail | Yes | Low |
| `AdminNewApplicationNotification` | mail | Yes | Low |

This is intentionally left as a separate step — the infrastructure must be in place and tested before wiring it into production notifications.

---

## 10. Testing & Troubleshooting

| Issue | Solution |
|---|---|
| Install prompt never shows (Android) | HTTPS/localhost, valid manifest (`display: standalone`, icons >=192px), service worker registered, user engaged (>=2 visits) |
| Push fails on iOS | iOS 16.4+, PWA added to Home Screen, permission granted in Settings > Safari > Notifications |
| 404/410 in logs | Run `php artisan webpush:clean`, ensure scheduler is active |
| Service worker not updating | DevTools > Application > Clear site data, or bump `manifest.version` |
| App opens in browser after install | Check `start_url` and `scope` in manifest, ensure `display: standalone` |
| Local iOS testing | Use `ngrok http 8000` or `laravel share` for public HTTPS URL |

### Notes

- `localhost` bypasses HTTPS requirements. Service Workers and Push API work fully on localhost.
- iOS requires a tunnel (`ngrok`) because it won't allow "Add to Home Screen" for `localhost`.
- The `TestPush` notification class can be used for any future developer testing, not just the admin dashboard feature.

---

## 11. Security & Privacy

- Never expose `VAPID_PRIVATE_KEY` to frontend
- Authenticate `/push-subscriptions` endpoint (`auth` middleware)
- Validate payloads server-side (URL format, key lengths)
- Respect preferences: provide easy unsubscribe UI
- Minimize data stored: only `endpoint`, `public_key`, `auth_token`, `user_id`
- No silent pushes: browsers block `userVisibleOnly: false`
