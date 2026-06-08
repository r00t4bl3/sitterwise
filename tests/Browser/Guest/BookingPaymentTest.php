<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('payment page redirects to create without session data', function () {
    visit('/book/payment/invalid-token')
        ->assertSee("It's you!")
        ->assertNoJavaScriptErrors();
});
