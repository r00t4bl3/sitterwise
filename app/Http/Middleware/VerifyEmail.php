<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmail
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow bypass in local environment for testing
        if (app()->environment('local')) {
            // Set a test email if not already set
            if (! $request->session()->has('verified_email')) {
                $request->session()->put('verified_email', 'test-applicant@example.com');
                $request->session()->put('verified_at', now());
            }

            return $next($request);
        }

        $verifiedAt = $request->session()->get('verified_at');

        if (! $request->session()->has('verified_email') || ! $verifiedAt) {
            return redirect()->route('caregiver.apply.verify');
        }

        // Check if verification is still valid (30 minutes)
        if (now()->diffInMinutes($verifiedAt) > 30) {
            $request->session()->forget(['verified_email', 'verified_at']);

            return redirect()->route('caregiver.apply.verify')
                ->withErrors(['email' => 'Your email verification has expired. Please verify again.']);
        }

        return $next($request);
    }
}
