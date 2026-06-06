<?php

use App\Http\Middleware\EnsureUserIsCaregiver;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('allows caregiver user', function () {
    $user = User::factory()->make(['role' => 'caregiver']);
    $request = Request::create('/')->setUserResolver(fn () => $user);

    $middleware = new EnsureUserIsCaregiver;
    $response = $middleware->handle($request, fn ($req) => response('OK'));

    $this->assertEquals(200, $response->getStatusCode());
});

test('denies non caregiver user', function () {
    $user = User::factory()->make(['role' => 'admin']);
    $request = Request::create('/')->setUserResolver(fn () => $user);

    $middleware = new EnsureUserIsCaregiver;

    try {
        $middleware->handle($request, fn ($req) => response('OK'));
        $this->fail('Expected HttpException');
    } catch (HttpException $e) {
        $this->assertEquals(403, $e->getStatusCode());
    }
});

test('denies unauthenticated user', function () {
    $request = Request::create('/')->setUserResolver(fn () => null);

    $middleware = new EnsureUserIsCaregiver;

    try {
        $middleware->handle($request, fn ($req) => response('OK'));
        $this->fail('Expected HttpException');
    } catch (HttpException $e) {
        $this->assertEquals(403, $e->getStatusCode());
    }
});
