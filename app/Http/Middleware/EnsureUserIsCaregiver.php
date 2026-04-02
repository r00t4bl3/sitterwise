<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCaregiver
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->role !== 'caregiver') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}
