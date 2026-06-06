<?php

use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('denies admin user', function () {
    $user = User::factory()->make(['role' => 'admin']);
    $request = Request::create('/')->setUserResolver(fn () => $user);

    $middleware = new EnsureUserIsSuperAdmin;

    try {
        $middleware->handle($request, fn ($req) => response('OK'));
        $this->fail('Expected HttpException');
    } catch (HttpException $e) {
        $this->assertEquals(403, $e->getStatusCode());
    }
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

    try {
        $middleware->handle($request, fn ($req) => response('OK'));
        $this->fail('Expected HttpException');
    } catch (HttpException $e) {
        $this->assertEquals(403, $e->getStatusCode());
    }
});

test('denies unauthenticated user', function () {
    $request = Request::create('/')->setUserResolver(fn () => null);

    $middleware = new EnsureUserIsSuperAdmin;

    try {
        $middleware->handle($request, fn ($req) => response('OK'));
        $this->fail('Expected HttpException');
    } catch (HttpException $e) {
        $this->assertEquals(403, $e->getStatusCode());
    }
});
