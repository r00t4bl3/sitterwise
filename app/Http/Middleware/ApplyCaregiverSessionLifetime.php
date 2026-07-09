<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives caregivers a long, rolling session so they aren't logged out between
 * infrequent visits (they mainly log in to set availability). Admins and clients
 * are untouched and keep the default session lifetime.
 *
 * How it works: a caregiver's session is marked with a lightweight cookie. On
 * every request that carries the marker we raise session.lifetime BEFORE the
 * session is read, so the longer window governs both the cookie expiry and the
 * server-side idle check. The marker is re-issued on each caregiver response, so
 * the 30-day window rolls with activity; it's dropped as soon as the request is
 * no longer a caregiver (e.g. after logout).
 *
 * Registered ahead of StartSession in the web group, and excluded from cookie
 * encryption so its presence can be read before cookies are decrypted.
 */
class ApplyCaregiverSessionLifetime
{
    public const MARKER = 'sw_long_session';

    /**
     * The rolling caregiver session length, in minutes. Driven by the editable
     * setting caregiver.session_lifetime_days (default 30 days).
     */
    private function lifetimeMinutes(): int
    {
        return (int) Settings::get('caregiver.session_lifetime_days', 30) * 24 * 60;
    }

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $lifetimeMinutes = $this->lifetimeMinutes();

        if ($request->cookies->has(self::MARKER)) {
            config(['session.lifetime' => $lifetimeMinutes]);
        }

        $response = $next($request);

        $user = $request->user();

        if ($user && $user->role === 'caregiver') {
            $response->headers->setCookie(
                cookie(self::MARKER, '1', $lifetimeMinutes)
            );
        } elseif ($request->cookies->has(self::MARKER)) {
            $response->headers->setCookie(cookie()->forget(self::MARKER));
        }

        return $response;
    }
}
