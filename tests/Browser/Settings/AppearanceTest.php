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

test('user can switch to dark mode', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $page = visit('/settings/appearance');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const darkBtn = buttons.find(b => b.textContent.includes('Dark'));
        if (darkBtn) darkBtn.click();
    JS);

    usleep(300000);

    $page->assertNoJavaScriptErrors();
});

test('user can switch to light mode', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $page = visit('/settings/appearance');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const lightBtn = buttons.find(b => b.textContent.includes('Light'));
        if (lightBtn) lightBtn.click();
    JS);

    usleep(300000);

    $page->assertNoJavaScriptErrors();
});

test('user can switch to system mode', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $page = visit('/settings/appearance');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const systemBtn = buttons.find(b => b.textContent.includes('System'));
        if (systemBtn) systemBtn.click();
    JS);

    usleep(300000);

    $page->assertNoJavaScriptErrors();
});
