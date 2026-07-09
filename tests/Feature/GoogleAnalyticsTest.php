<?php

use App\Models\User;

beforeEach(function () {
    config(['services.google_analytics.measurement_id' => 'G-TESTONLY']);
});

test('the GA4 tag is injected for guests on public pages in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/login')
        ->assertOk()
        ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TESTONLY', false)
        ->assertSee("gtag('config', 'G-TESTONLY')", false);
});

test('the GA4 tag is not injected outside production', function () {
    $this->get('/login')
        ->assertOk()
        ->assertDontSee('googletagmanager.com/gtag/js');
});

test('the GA4 tag is not injected for authenticated staff when public_only', function () {
    config(['services.google_analytics.public_only' => true]);
    app()->detectEnvironment(fn () => 'production');

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get('/settings/profile')
        ->assertOk()
        ->assertDontSee('googletagmanager.com/gtag/js');
});

test('disabling public_only tracks authenticated users too', function () {
    config(['services.google_analytics.public_only' => false]);
    app()->detectEnvironment(fn () => 'production');

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get('/settings/profile')
        ->assertOk()
        ->assertSee('gtag/js?id=G-TESTONLY', false);
});

test('a measurement id is configured', function () {
    expect(config('services.google_analytics.measurement_id'))->not->toBeEmpty();
});
