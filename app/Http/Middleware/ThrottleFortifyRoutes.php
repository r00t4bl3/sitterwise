<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleFortifyRoutes
{
    protected array $map = [
        'register.store' => 'register',
        'password.email' => 'forgot-password',
        'password.update' => 'reset-password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (! $routeName || ! isset($this->map[$routeName])) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            return $next($request);
        }

        $limiter = RateLimiter::limiter($this->map[$routeName]);

        if (! $limiter) {
            return $next($request);
        }

        $limits = $limiter($request);
        $limits = is_array($limits) ? $limits : [$limits];

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit->key, $limit->maxAttempts)) {
                if ($limit->responseCallback) {
                    return call_user_func($limit->responseCallback, $request, []);
                }

                return back()->withErrors(['email' => 'Too many attempts. Please try again later.']);
            }
        }

        foreach ($limits as $limit) {
            RateLimiter::hit($limit->key, $limit->decaySeconds);
        }

        return $next($request);
    }
}
