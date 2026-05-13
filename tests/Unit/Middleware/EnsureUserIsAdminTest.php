<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User;
use Illuminate\Http\Request;

test('allows admin user', function () {
    $user = User::factory()->make(['role' => 'admin']);
    $request = Request::create('/')->setUserResolver(fn () => $user);

    $middleware = new EnsureUserIsAdmin;
    $response = $middleware->handle($request, fn ($req) => response('OK'));

    $this->assertEquals(200, $response->getStatusCode());
});

test('denies non admin user', function () {
    $user = User::factory()->make(['role' => 'caregiver']);
    $request = Request::create('/')->setUserResolver(fn () => $user);

    $middleware = new EnsureUserIsAdmin;
    $response = $middleware->handle($request, fn ($req) => response('OK'));

    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('Unauthorized', $response->getData(true)['message']);
});

test('denies unauthenticated user', function () {
    $request = Request::create('/')->setUserResolver(fn () => null);

    $middleware = new EnsureUserIsAdmin;
    $response = $middleware->handle($request, fn ($req) => response('OK'));

    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('Unauthorized', $response->getData(true)['message']);
});
