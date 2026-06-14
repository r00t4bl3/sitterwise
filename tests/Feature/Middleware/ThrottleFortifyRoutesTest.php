<?php

use App\Http\Middleware\ThrottleFortifyRoutes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

test('middleware skips non-target routes', function () {
    $request = Request::create('/login', 'POST', ['email' => 'test@example.com', 'password' => 'password']);
    $request->setRouteResolver(fn () => (object) ['getName' => fn () => 'login.store']);

    $middleware = new ThrottleFortifyRoutes;

    $response = $middleware->handle($request, fn ($req) => response('next'));

    expect($response->getContent())->toBe('next');
});

test('middleware skips GET requests', function () {
    $request = Request::create('/register', 'GET');
    $request->setRouteResolver(fn () => (object) ['getName' => fn () => 'register.store']);

    $middleware = new ThrottleFortifyRoutes;

    $response = $middleware->handle($request, fn ($req) => response('next'));

    expect($response->getContent())->toBe('next');
});

test('middleware identifies register route and applies limiter', function () {
    $request = Request::create('/register', 'POST', [
        'name' => 'Test User', 'email' => 'test@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ]);
    $request->setRouteResolver(fn () => (object) ['getName' => fn () => 'register.store']);

    $middleware = new ThrottleFortifyRoutes;

    $limiter = RateLimiter::limiter('register');
    expect($limiter)->not->toBeNull();

    $response = $middleware->handle($request, fn ($req) => response('next'));

    expect($response->getContent())->toBe('next');
});

test('middleware identifies forgot-password route and applies limiter', function () {
    $request = Request::create('/forgot-password', 'POST', ['email' => 'test@example.com']);
    $request->setRouteResolver(fn () => (object) ['getName' => fn () => 'password.email']);

    $middleware = new ThrottleFortifyRoutes;

    $limiter = RateLimiter::limiter('forgot-password');
    expect($limiter)->not->toBeNull();

    $response = $middleware->handle($request, fn ($req) => response('next'));

    expect($response->getContent())->toBe('next');
});

test('middleware identifies reset-password route and applies limiter', function () {
    $request = Request::create('/reset-password', 'POST', [
        'email' => 'test@example.com', 'token' => 'token', 'password' => 'password', 'password_confirmation' => 'password',
    ]);
    $request->setRouteResolver(fn () => (object) ['getName' => fn () => 'password.update']);

    $middleware = new ThrottleFortifyRoutes;

    $limiter = RateLimiter::limiter('reset-password');
    expect($limiter)->not->toBeNull();

    $response = $middleware->handle($request, fn ($req) => response('next'));

    expect($response->getContent())->toBe('next');
});

test('all three rate limiters are registered', function () {
    expect(RateLimiter::limiter('register'))->not->toBeNull();
    expect(RateLimiter::limiter('forgot-password'))->not->toBeNull();
    expect(RateLimiter::limiter('reset-password'))->not->toBeNull();
});
