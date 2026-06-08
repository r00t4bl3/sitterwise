<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('appearance settings page can be viewed', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    visit('/settings/appearance')
        ->assertSee('Appearance settings')
        ->assertSee('Update your account\'s appearance settings')
        ->assertNoJavaScriptErrors();
});
