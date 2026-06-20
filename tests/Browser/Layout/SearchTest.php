<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('global search input can be typed into', function () {
    $user = createClientUser();

    $this->actingAs($user);

    $page = visit('/bookings');

    fillField($page, 'input[placeholder*="Search bookings"]', 'test');

    $page->assertNoJavaScriptErrors();
});
