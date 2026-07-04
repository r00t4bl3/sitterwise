<?php

use App\Http\Middleware\ApplyCaregiverSessionLifetime;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCaregiver;
use App\Http\Middleware\EnsureUserIsClient;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: [
            'appearance',
            'sidebar_state',
            ApplyCaregiverSessionLifetime::MARKER,
        ]);

        // Runs before StartSession so it can raise session.lifetime for
        // caregivers (long, rolling session) before the session is read.
        $middleware->web(prepend: [
            ApplyCaregiverSessionLifetime::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',
            'webhooks/twilio/status',
            'webhooks/twilio/inbound',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'caregiver' => EnsureUserIsCaregiver::class,
            'client' => EnsureUserIsClient::class,
            'super_admin' => EnsureUserIsSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            // Expired CSRF/session: bounce back so a fresh token is issued
            // rather than showing an error page.
            if ($status === 419) {
                return back()->with('error', 'The page expired, please try again.');
            }

            // Only take over the statuses we render friendly pages for.
            if (! in_array($status, [403, 404, 500, 503], true)) {
                return $response;
            }

            // Never hijack JSON/API/webhook responses (Inertia page GETs are
            // not expectsJson, so they still get the friendly page).
            if ($request->expectsJson()) {
                return $response;
            }

            // Keep native error semantics in local/testing so the existing test
            // suite is unaffected; render the branded page in production.
            if (app()->environment(['local', 'testing'])) {
                return $response;
            }

            return Inertia::render('errors/error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
