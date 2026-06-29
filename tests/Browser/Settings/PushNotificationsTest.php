<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('push notifications page can be viewed', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    visit('/settings/push-notifications')
        ->assertSee('Push Notifications')
        ->assertNoJavaScriptErrors();
});
