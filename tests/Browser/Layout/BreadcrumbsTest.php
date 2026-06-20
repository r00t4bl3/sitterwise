<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('breadcrumbs appear on nested settings page', function () {
    $user = createClientUser();

    $this->actingAs($user);

    visit('/settings/profile')
        ->assertSee('Profile information')
        ->assertSee('Dashboard')
        ->assertSee('Settings')
        ->assertSee('Profile')
        ->assertNoJavaScriptErrors();
});
