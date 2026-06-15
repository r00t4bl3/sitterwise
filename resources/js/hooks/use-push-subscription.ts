import { useCallback, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { store as storePushSubscription } from '@/routes/push-subscriptions';

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN\s*=\s*([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

export function usePushSubscription() {
    const { props } = usePage<{
        vapid_public_key?: string;
        supports_push?: boolean;
    }>();
    const [subscription, setSubscription] = useState<PushSubscription | null>(null);
    const [permission, setPermission] = useState<NotificationPermission>(
        typeof Notification !== 'undefined' ? Notification.permission : 'default'
    );
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const loadingRef = useRef(false);

    const isSupported =
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        !!props.supports_push &&
        (location.protocol === 'https:' || location.hostname === 'localhost');

    useEffect(() => {
        if (!isSupported) return;
        navigator.serviceWorker.ready
            .then((reg) => reg.pushManager.getSubscription())
            .then((sub) => setSubscription(sub))
            .catch(console.warn);
    }, [isSupported]);

    const serviceWorkerReady = useCallback(async (timeoutMs = 8000) => {
        const timeout = new Promise<ServiceWorkerRegistration>(
            (_, reject) => setTimeout(() => reject(new Error('Service worker timed out. Push notifications require HTTPS or localhost.')), timeoutMs)
        );
        return Promise.race([navigator.serviceWorker.ready, timeout]);
    }, []);

    const subscribe = async () => {
        if (!isSupported) {
            setError('Push not supported');
            return false;
        }
        if (loadingRef.current) return false;
        loadingRef.current = true;
        setLoading(true);
        setError(null);

        try {
            const perm = await Notification.requestPermission();
            setPermission(perm);
            if (perm !== 'granted') throw new Error('Permission denied');

            const reg = await serviceWorkerReady();
            const existing = await reg.pushManager.getSubscription();
            if (existing) {
                setSubscription(existing);
                return true;
            }

            const newSub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(
                    props.vapid_public_key!
                ) as BufferSource,
            });

            const res = await fetch(storePushSubscription().url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(JSON.parse(JSON.stringify(newSub))),
            });

            if (!res.ok) throw new Error('Failed to save subscription');

            setSubscription(newSub);

            return true;
        } catch (err) {
            setError((err as Error).message);
            return false;
        } finally {
            loadingRef.current = false;
            setLoading(false);
        }
    };

    return { isSupported, permission, subscription, loading, error, subscribe };
}
