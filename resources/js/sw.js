/* global clients */

// eslint-disable-next-line @typescript-eslint/no-unused-expressions
self.__WB_MANIFEST;

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    try {
        const data = event.data.json();
        event.waitUntil(
            self.registration.showNotification(
                data.title || 'New Notification',
                {
                    body: data.body || '',
                    icon: data.icon || '/icon-192.png',
                    badge: data.badge || '/icon-72.png',
                    data: { url: data.url || '/', ...data.data },
                    actions: data.actions || [],
                    tag: data.tag || 'default',
                    renotify: !!data.renotify,
                },
            ),
        );
    } catch {
        event.waitUntil(
            self.registration.showNotification('New Message', {
                body: 'Tap to view',
                icon: '/icon-192.png',
                data: { url: '/' },
            }),
        );
    }
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(
        clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientsList) => {
                const client = clientsList.find(
                    (c) => c.url === url && 'focus' in c,
                );

                return client ? client.focus() : clients.openWindow(url);
            }),
    );
});

self.addEventListener('pushsubscriptionchange', () => {
    console.warn('Push subscription changed/expired. Re-subscribe needed.');
});
