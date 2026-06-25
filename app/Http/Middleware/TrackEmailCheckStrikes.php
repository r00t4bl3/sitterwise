<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackEmailCheckStrikes
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $ip = $request->ip();
        $key = "email-check-strikes:{$ip}";

        if ($response->getStatusCode() === 429) {
            Cache::increment($key, 1, now()->addDay());
        } else {
            $strikes = (int) Cache::get($key, 0);
            if ($strikes > 0) {
                Cache::decrement($key);
            }
        }

        return $response;
    }
}
