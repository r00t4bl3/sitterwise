<?php

namespace App\Http\Middleware;

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

    /** 30 days, in minutes. Rolling — reset by any request the caregiver makes. */
    private const LIFETIME_MINUTES = 30 * 24 * 60;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->cookies->has(self::MARKER)) {
            config(['session.lifetime' => self::LIFETIME_MINUTES]);
        }

        $response = $next($request);

        $user = $request->user();

        if ($user && $user->role === 'caregiver') {
            $response->headers->setCookie(
                cookie(self::MARKER, '1', self::LIFETIME_MINUTES)
            );
        } elseif ($request->cookies->has(self::MARKER)) {
            $response->headers->setCookie(cookie()->forget(self::MARKER));
        }

        return $response;
    }
}
