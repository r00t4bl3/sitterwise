<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('payouts page loads for caregiver', function () {
    $user = createCaregiver();

    $this->actingAs($user);

    visit('/payouts')
        ->assertSee('Payouts')
        ->assertNoJavaScriptErrors();
});
