<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('jobs index page can be viewed', function () {
    $user = createCaregiver();

    $this->actingAs($user);

    visit('/jobs')
        ->assertSee('My Jobs')
        ->assertSee('No jobs found')
        ->assertNoJavaScriptErrors();
});
