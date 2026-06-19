<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('payments index page loads', function () {
    $user = createClientUser();

    $this->actingAs($user);

    visit('/payments')
        ->assertSee('Payment')
        ->assertNoJavaScriptErrors();
});
