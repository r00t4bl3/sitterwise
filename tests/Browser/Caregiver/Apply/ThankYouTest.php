<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('thank you page renders', function () {
    $page = visit('/caregiver/apply/thank-you');

    $page->assertSee('Application Submitted!')
        ->assertSee('Thank you for applying')
        ->assertSee('Return to Home')
        ->assertNoJavaScriptErrors();
});

test('thank you page has whats next section', function () {
    $page = visit('/caregiver/apply/thank-you');

    $page->assertSee("What's Next?")
        ->assertSee("We'll verify your email")
        ->assertSee('contact your references')
        ->assertSee('3-5 business days')
        ->assertNoJavaScriptErrors();
});
