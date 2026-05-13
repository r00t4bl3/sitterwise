<?php

use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Models\User;
use Illuminate\Http\Request;

test('allows admin user', function () {
    $user = User::factory()->make(['role' => 'admin']);
    $request = Request::create('/')->setUserResolver(fn () => $user);

    $middleware = new EnsureUserIsSuperAdmin;
    $response = $middleware->handle($request, fn ($req) => response('OK'));

    $this->assertEquals(200, $response->getStatusCode());
});

test('allows super admin user', function () {
    $user = User::factory()->make(['role' => 'super_admin']);
    $request = Request::create('/')->setUserResolver(fn () => $user);

    $middleware = new EnsureUserIsSuperAdmin;
    $response = $middleware->handle($request, fn ($req) => response('OK'));

    $this->assertEquals(200, $response->getStatusCode());
});

test('denies non super admin user', function () {
    $user = User::factory()->make(['role' => 'caregiver']);
    $request = Request::create('/')->setUserResolver(fn () => $user);

    $middleware = new EnsureUserIsSuperAdmin;
    $response = $middleware->handle($request, fn ($req) => response('OK'));

    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('Unauthorized', $response->getData(true)['message']);
});

test('denies unauthenticated user', function () {
    $request = Request::create('/')->setUserResolver(fn () => null);

    $middleware = new EnsureUserIsSuperAdmin;
    $response = $middleware->handle($request, fn ($req) => response('OK'));

    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('Unauthorized', $response->getData(true)['message']);
});
