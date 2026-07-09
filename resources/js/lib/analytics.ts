// Thin wrapper around Google Analytics (gtag.js). gtag is only present in
// production (injected in app.blade.php), so every call no-ops safely otherwise.

declare global {
    interface Window {
        gtag?: (...args: unknown[]) => void;
    }
}

export function trackEvent(
    name: string,
    params: Record<string, unknown> = {},
): void {
    if (typeof window !== 'undefined' && typeof window.gtag === 'function') {
        window.gtag('event', name, params);
    }
}

/**
 * Send a GA4 page_view for the current URL. GA auto-sends the first pageview on
 * full load; this covers client-side (Inertia) navigations.
 */
export function trackPageView(): void {
    if (typeof window === 'undefined') {
        return;
    }

    trackEvent('page_view', {
        page_location: window.location.href,
        page_path: window.location.pathname + window.location.search,
        page_title: document.title,
    });
}
