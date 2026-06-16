import { useState } from 'react';
import { usePWAInstall } from '@/hooks/use-pwa-install';

export default function PWAInstallPrompt() {
    const {
        isInstallable,
        isIOS,
        isStandalone,
        promptInstall,
        iosInstructions,
    } = usePWAInstall();
    const [dismissed, setDismissed] = useState(
        () => localStorage.getItem('pwa-install-dismissed') === 'true',
    );

    if (isStandalone || !isInstallable || dismissed) {
        return null;
    }

    return (
        <div className="fixed right-4 bottom-4 z-50 w-full max-w-sm">
            <div className="relative rounded-xl border border-neutral-200 bg-white p-4 shadow-xl dark:border-neutral-700 dark:bg-neutral-800">
                <button
                    onClick={() => {
                        setDismissed(true);
                        localStorage.setItem('pwa-install-dismissed', 'true');
                    }}
                    className="absolute top-3 right-3 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
                    aria-label="Dismiss"
                >
                    <svg
                        className="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>

                {!isIOS ? (
                    <div>
                        <h3 className="text-lg font-semibold text-neutral-900 dark:text-white">
                            Install App
                        </h3>
                        <p className="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                            Add to home screen for faster access and push
                            notifications.
                        </p>
                        <button
                            onClick={promptInstall}
                            className="mt-4 w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition hover:bg-blue-700"
                        >
                            Install Now
                        </button>
                    </div>
                ) : (
                    <div>
                        <h3 className="text-lg font-semibold text-neutral-900 dark:text-white">
                            Install on iPhone/iPad
                        </h3>
                        <p className="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                            To enable push notifications:
                        </p>
                        <ol className="mt-2 list-inside list-decimal space-y-1 text-sm text-neutral-600 dark:text-neutral-300">
                            {iosInstructions.map((step, i) => (
                                <li key={i}>{step}</li>
                            ))}
                        </ol>
                    </div>
                )}
            </div>
        </div>
    );
}
