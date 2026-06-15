import { useEffect, useState } from 'react';

export function usePWAInstall() {
    const [deferredPrompt, setDeferredPrompt] = useState<any>(null);
    const [isInstallable, setIsInstallable] = useState(false);
    const [isIOS, setIsIOS] = useState(false);
    const [isStandalone, setIsStandalone] = useState(false);

    useEffect(() => {
        const ios = /iphone|ipad|ipod/.test(
            window.navigator.userAgent.toLowerCase()
        );
        setIsIOS(ios);

        const standalone =
            window.matchMedia('(display-mode: standalone)').matches ||
            (window.navigator as any).standalone === true;
        setIsStandalone(standalone);

        const handler = (e: Event) => {
            e.preventDefault();
            setDeferredPrompt(e);
            setIsInstallable(true);
        };
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
