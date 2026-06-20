<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user menu opens and shows settings and logout', function () {
    $user = createClientUser();

    $this->actingAs($user);

    $page = visit('/bookings');

    $page->script(<<<'JS'
        const el = document.querySelector('[data-test="sidebar-menu-button"]');
        if (el) {
            el.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, cancelable: true }));
        }
    JS);

    $page->waitForText('Settings', 5)
        ->assertSee('Log out')
        ->assertNoJavaScriptErrors();
});
