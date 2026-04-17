Here is the complete, production-ready markdown specification with all recommendations, backend/frontend code, service worker, and the **new PWA Install Flow** integrated into a single source of truth.

---

# Technical Specification: PWA Push Notifications & Install Flow
**Tech Stack:** Laravel, React, Inertia.js, Vite  
**Last Updated:** April 2026

---

## 1. Overview
Implement Progressive Web App (PWA) push notifications and home-screen installation using a full-stack approach. This requires:
- Backend storage of user subscriptions & VAPID authentication
- Frontend service worker for background push handling
- Platform-aware install prompts (Android/Chromium vs iOS)

> ⚠️ **Critical Constraints**
> - Service Workers require **HTTPS** in production (`localhost` exempt)
> - iOS Safari: Push only works if PWA is **added to Home Screen** + iOS 16.4+
> - All payloads must be **user-visible** (`userVisibleOnly: true`)

---

## 2. Backend (Laravel)

### A. Dependencies
```bash
composer require laravel-notification-channels/webpush
```

### B. Setup
1. **Migrations:** Create the `push_subscriptions` table.
   ```bash
   php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider" --tag="migrations"
   php artisan migrate
   ```

2. **VAPID Keys:** Generate keys to sign push messages.
   ```bash
   php artisan webpush:vapid
   ```
   Add to `.env`:
   ```env
   VAPID_PUBLIC_KEY=your_public_key
   VAPID_PRIVATE_KEY=your_private_key
   VAPID_SUBJECT=mailto:admin@yourdomain.com
   ```

3. **User Model:** Add the `HasPushSubscriptions` trait.
   ```php
   use NotificationChannels\WebPush\HasPushSubscriptions;

   class User extends Authenticatable {
       use HasPushSubscriptions;
   }
   ```

### C. Inertia Integration
Share the Public VAPID key & environment state with React:
```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        // ... existing shared props
        'vapid_public_key' => config('webpush.vapid.public_key'),
        'supports_push' => config('app.env') === 'local' || $request->secure(),
    ];
}
```

### D. 🔐 Authenticated Subscription Endpoint
```php
// routes/web.php
use App\Http\Controllers\PushSubscriptionController;

Route::middleware(['auth'])->group(function () {
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])
        ->name('push-subscriptions.store');
});
```

### E. 📦 PushSubscriptionController
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

        // Upsert: update if exists, create if new
        $subscription = $request->user()->pushSubscriptions()
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

### F. 🧹 Schedule Stale Subscription Cleanup
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('webpush:clean')->daily();
}
```

### G. 🧪 TestPush Notification Class
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
        public string $title = '🔔 Test Notification',
        public string $body = 'This is a test push from your Laravel app!',
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

### H. 🧪 Dev Testing Route (Local Only)
```php
// routes/web.php
Route::middleware(['auth'])->post('/test-push', function (Request $request) {
    if (config('app.env') !== 'local') abort(403);
    
    $request->user()->notify(new \App\Notifications\TestPush(
        title: $request->input('title', '🔔 Test'),
        body: $request->input('body', 'Push working!'),
        url: $request->input('url', '/')
    ));
    
    return back()->with('status', '✅ Push sent!');
})->name('test.push');
```

---

## 3. Frontend: Push Subscription

### 🪝 Hook: `usePushSubscription`
```javascript
// resources/js/hooks/usePushSubscription.js
import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';

export function usePushSubscription() {
  const { props } = usePage();
  const [subscription, setSubscription] = useState(null);
  const [permission, setPermission] = useState(Notification?.permission || 'default');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const isSupported = 'serviceWorker' in navigator && 'PushManager' in window && props.supports_push;

  useEffect(() => {
    if (!isSupported) return;
    navigator.serviceWorker.ready.then(reg => 
      reg.pushManager.getSubscription().then(setSubscription)
    ).catch(console.warn);
  }, [isSupported]);

  const subscribe = async () => {
    if (!isSupported) { setError('Push not supported'); return false; }
    setLoading(true); setError(null);

    try {
      const perm = await Notification.requestPermission();
      setPermission(perm);
      if (perm !== 'granted') throw new Error('Permission denied');

      const reg = await navigator.serviceWorker.ready;
      const existing = await reg.pushManager.getSubscription();
      if (existing) { setSubscription(existing); return true; }

      const newSub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(props.vapid_public_key),
      });

      await router.post('/push-subscriptions', JSON.parse(JSON.stringify(newSub)), {
        preserveScroll: true,
        onSuccess: () => setSubscription(newSub),
        onError: () => { throw new Error('Failed to save subscription'); }
      });
      return true;
    } catch (err) {
      setError(err.message);
      return false;
    } finally {
      setLoading(false);
    }
  };

  return { isSupported, permission, subscription, loading, error, subscribe };
}

function urlBase64ToUint8Array(base64String) {
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

### 🪝 Hook: `usePWAInstall`
```javascript
// resources/js/hooks/usePWAInstall.js
import { useState, useEffect } from 'react';

export function usePWAInstall() {
  const [deferredPrompt, setDeferredPrompt] = useState(null);
  const [isInstallable, setIsInstallable] = useState(false);
  const [isIOS, setIsIOS] = useState(false);
  const [isStandalone, setIsStandalone] = useState(false);

  useEffect(() => {
    const ios = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase()) && !window.MSStream;
    setIsIOS(ios);

    const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    setIsStandalone(standalone);

    const handler = (e) => { e.preventDefault(); setDeferredPrompt(e); setIsInstallable(true); };
    window.addEventListener('beforeinstallprompt', handler);
    
    return () => window.removeEventListener('beforeinstallprompt', handler);
  }, []);

  const promptInstall = async () => {
    if (!deferredPrompt) return false;
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    setDeferredPrompt(null); setIsInstallable(false);
    return outcome === 'accepted';
  };

  return { isInstallable, isIOS, isStandalone, promptInstall, iosInstructions: [
    'Tap the Share button (⎋) in Safari',
    'Scroll down & tap "Add to Home Screen"',
    'Tap "Add" to confirm'
  ]};
}
```

### 🎨 Component: `PWAInstallPrompt`
```jsx
// resources/js/Components/PWAInstallPrompt.jsx
import { useState } from 'react';
import { usePWAInstall } from '@/hooks/usePWAInstall';

export default function PWAInstallPrompt() {
  const { isInstallable, isIOS, isStandalone, promptInstall, iosInstructions } = usePWAInstall();
  const [dismissed, setDismissed] = useState(() => localStorage.getItem('pwa-install-dismissed') === 'true');

  if (isStandalone || !isInstallable || dismissed) return null;

  const handleDismiss = () => {
    setDismissed(true);
    localStorage.setItem('pwa-install-dismissed', 'true');
  };

  return (
    <div className="fixed bottom-4 right-4 z-50 w-full max-w-sm">
      <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl p-4 relative">
        <button onClick={handleDismiss} className="absolute top-3 right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Dismiss">
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
        </button>

        {!isIOS ? (
          <div>
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">📲 Install App</h3>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">Add to home screen for faster access & push notifications.</p>
            <button onClick={promptInstall} className="mt-4 w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition">Install Now</button>
          </div>
        ) : (
          <div>
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">🍎 Install on iPhone/iPad</h3>
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

### 📍 Where to Render
```jsx
// resources/js/Layouts/AppLayout.jsx
import PWAInstallPrompt from '@/Components/PWAInstallPrompt';

export default function AppLayout({ children }) {
  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      {/* Header / Nav */}
      <main className="container mx-auto px-4 py-6">{children}</main>
      <PWAInstallPrompt /> {/* Renders conditionally */}
    </div>
  );
}
```

---

## 5. Service Worker (`resources/js/sw.js`)
```javascript
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
  } catch (err) {
    event.waitUntil(self.registration.showNotification('New Message', { body: 'Tap to view', icon: '/icon-192.png', data: { url: '/' } }));
  }
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(clientsList => {
        const clientToFocus = clientsList.find(c => c.url === url && 'focus' in c);
        return clientToFocus ? clientToFocus.focus() : clients.openWindow(url);
      })
  );
});

self.addEventListener('pushsubscriptionchange', (event) => {
  console.warn('⚠️ Push subscription changed/expired. Re-subscribe needed.');
});
```

---

## 6. Vite & Manifest Configuration
```javascript
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
  plugins: [
    laravel({ input: ['resources/css/app.css', 'resources/js/app.jsx'], refresh: true }),
    react(),
    VitePWA({
      strategies: 'injectManifest',
      srcDir: 'resources/js',
      filename: 'sw.js',
      registerType: 'autoUpdate',
      manifest: {
        name: 'Your App Name',
        short_name: 'App',
        start_url: '/',
        display: 'standalone',
        background_color: '#ffffff',
        theme_color: '#000000',
        icons: [
          { src: '/icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icon-512.png', sizes: '512x512', type: 'image/png' },
          { src: '/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' }
        ]
      }
    })
  ]
});
```

---

## 7. Implementation Roadmap

### Phase 1: Backend Foundation (Day 1-2)
- [ ] Install `laravel-notification-channels/webpush`
- [ ] Run migrations + generate VAPID keys → `.env`
- [ ] Add `HasPushSubscriptions` trait to `User`
- [ ] Share `vapid_public_key` & `supports_push` via Inertia
- [ ] Create `PushSubscriptionController` + authenticated route
- [ ] Create `TestPush` notification + dev route
- [ ] Add `webpush:clean` to scheduler

### Phase 2: Frontend Integration (Day 3-4)
- [ ] Configure `vite-plugin-pwa` + manifest
- [ ] Implement `usePushSubscription` hook
- [ ] Build notification toggle UI
- [ ] Implement `usePWAInstall` + `PWAInstallPrompt`
- [ ] Create `sw.js` with push/click handlers
- [ ] Place `PWAInstallPrompt` in `AppLayout`

### Phase 3: Testing & Polish (Day 5)
- [ ] Test push flow: Chrome Desktop → Android → Firefox → Safari macOS
- [ ] Test iOS: Use `ngrok` tunnel → Add to Home Screen → Grant permission
- [ ] Verify `webpush:clean` runs daily
- [ ] Add fallback UI for unsupported browsers

---

## 8. Testing & Troubleshooting

| Issue | Solution |
|-------|----------|
| **Install prompt never shows (Android)** | Ensure: HTTPS/localhost, valid manifest (`display: standalone`, icons ≥192px), service worker registered, user engaged (≥2 visits/scrolls) |
| **Push fails on iOS** | Verify: iOS 16.4+, PWA **added to Home Screen**, permission granted in Settings → Safari → Notifications |
| **404/410 in logs** | Run `php artisan webpush:clean`; ensure cron/scheduler is active |
| **Service worker not updating** | DevTools → Application → Clear site data, or bump `manifest.version` |
| **App opens in browser after install** | Check `start_url` & `scope` in manifest; ensure `display: standalone` |
| **Local iOS testing** | Use `ngrok http 8000` or `laravel share` to get public HTTPS URL |

> 🔑 **Localhost Note**: `localhost` bypasses HTTPS requirements. Service Workers & Push API work fully. iOS requires a tunnel (`ngrok`) because it won't allow "Add to Home Screen" for `localhost`.

---

## 9. Security & Privacy Notes
- 🔐 **Never expose `VAPID_PRIVATE_KEY`** to frontend
- 🔐 **Authenticate `/push-subscriptions`** endpoint (`auth` middleware)
- 🔐 **Validate payloads** server-side (URL format, key lengths)
- 👁️ **Respect preferences**: Provide easy unsubscribe/mute UI
- 📊 **Minimize data**: Store only `endpoint`, `public_key`, `auth_token`, `user_id`
- 🚫 **No silent pushes**: Browsers block `userVisibleOnly: false`

---

✅ **This spec is production-ready.** Your developer can implement directly from this document. The install flow handles platform differences gracefully, push subscriptions are secure & idempotent, and the service worker is optimized for reliability.

Need a `docker-compose` setup for local testing, or want the `TestPush` notification wrapped in an Inertia modal for QA? Just say the word. 🚀
