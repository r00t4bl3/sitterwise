<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login page renders on mobile without JavaScript errors', function () {
    visit('/login')->on()->mobile()
        ->assertSee('Log in to your account')
        ->assertNoJavaScriptErrors();
});

test('booking create page renders on mobile without JavaScript errors', function () {
    visit('/book')->on()->mobile()
        ->assertSee("It's you!")
        ->assertNoJavaScriptErrors();
});

test('authenticated page renders on mobile without JavaScript errors', function () {
    $user = createClientUser();

    $this->actingAs($user);

    visit('/dashboard')->on()->mobile()
        ->assertNoJavaScriptErrors();
});
