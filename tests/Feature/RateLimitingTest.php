<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

it('returns redirect when rate limited with response callback', function () {
    Route::post('_test/with-cb', fn () => response('ok'))
        ->middleware('throttle:test-with-cb');

    RateLimiter::for('test-with-cb', fn (Request $request) => [
        Limit::perMinute(1)->by('test-key-cb')
            ->response(fn () => back()->with('error', 'Too fast. Please wait.')),
    ]);

    $this->post('_test/with-cb'); // 1st — ok
    $response = $this->post('_test/with-cb'); // 2nd — blocked

    $response->assertStatus(302);
});

it('returns 429 when rate limited without response callback', function () {
    Route::post('_test/without-cb', fn () => response('ok'))
        ->middleware('throttle:test-without-cb');

    RateLimiter::for('test-without-cb', fn (Request $request) => [
        Limit::perMinute(1)->by('test-key-no-cb'),
    ]);

    $this->post('_test/without-cb'); // 1st — ok
    $response = $this->post('_test/without-cb'); // 2nd — blocked

    $response->assertStatus(429);
});

it('applies multiple limits and returns the first exceeded limit response', function () {
    Route::post('_test/multi', fn () => response('ok'))
        ->middleware('throttle:test-multi');

    RateLimiter::for('test-multi', fn (Request $request) => [
        Limit::perMinute(2)->by('test-multi-ip')
            ->response(fn () => back()->with('error', 'Too many per minute.')),
        Limit::perMinutes(5, 1)->by('test-multi-email')
            ->response(fn () => back()->with('error', 'Too many. Try again later.')),
    ]);

    // Exhaust the per-minute limit (2 requests)
    $this->post('_test/multi');
    $this->post('_test/multi');

    // 3rd request — hits the per-minute limit, which has a callback
    $response = $this->post('_test/multi');

    $response->assertStatus(302);
});
