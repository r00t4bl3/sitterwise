<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can log in', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('password'),
    ]);

    Client::factory()->create(['user_id' => $user->id]);

    $page = visit('/login');

    loginViaJs($page, 'john@example.com', 'password');

    $page->assertPathIs('/dashboard');
    $page->assertSee('Welcome back');

    $this->assertAuthenticated();
});

test('user sees error with wrong password', function () {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('password'),
    ]);

    $page = visit('/login');

    loginViaJs($page, 'john@example.com', 'wrong-password');

    $page->assertSee('These credentials do not match our records.');
});

test('user can navigate to forgot password', function () {
    $page = visit('/login');

    $page->script(<<<'JS'
        document.querySelector('a[href="/forgot-password"]').click();
    JS);

    $page->assertPathIs('/forgot-password');
});

test('user can navigate to register', function () {
    $page = visit('/login');

    $page->script(<<<'JS'
        document.querySelector('a[href="/register"]').click();
    JS);

    $page->assertPathIs('/register');
});
