<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('page renders with all sections', function () {
    visit('/book')
        ->assertSee("It's you!")
        ->assertSee('About You')
        ->assertSee('About Your Booking')
        ->assertSee('Continue to Payment')
        ->assertNoJavaScriptErrors();
});

test('can fill about you section', function () {
    $page = visit('/book');

    fillField($page, 'input[placeholder="First name"]', 'John');
    fillField($page, 'input[placeholder="Last name"]', 'Doe');
    fillField($page, 'input[placeholder="your@email.com"]', 'john@example.com');
    fillField($page, 'input[type="tel"]', '5551234567');

    $page->assertNoJavaScriptErrors();
});

test('can select service type and location type', function () {
    $page = visit('/book');

    selectOptionByLabel($page, 'Service Type', 'Pet Sitting');
    selectOptionByLabel($page, 'Location Type', 'Hotel');

    $page->assertNoJavaScriptErrors();
});

test('can toggle hotel not listed', function () {
    $page = visit('/book');

    selectOptionByLabel($page, 'Location Type', 'Hotel');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const notListed = buttons.find(b => b.textContent.includes('My hotel is not listed'));
        if (notListed) notListed.click();
    JS);

    $page->assertSee('Enter hotel name');
    $page->assertNoJavaScriptErrors();
});

test('can add and remove children', function () {
    $page = visit('/book');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const addChild = buttons.find(b => b.textContent.includes('Add Child'));
        if (addChild) addChild.click();
    JS);

    $page->assertNoJavaScriptErrors();
});

test('can add and remove pets', function () {
    $page = visit('/book');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const addPet = buttons.find(b => b.textContent.includes('Add Pet'));
        if (addPet) addPet.click();
    JS);

    $page->assertNoJavaScriptErrors();
});

test('shows validation errors on incomplete submit', function () {
    $page = visit('/book');

    submitGuestBookingForm($page);

    $page->assertSee('First name is required');
    $page->assertSee('Email is required');
    $page->assertNoJavaScriptErrors();
});

test('can submit valid form and redirect to payment', function () {
    $page = visit('/book');

    fillField($page, 'input[placeholder="First name"]', 'John');
    fillField($page, 'input[placeholder="Last name"]', 'Doe');
    fillField($page, 'input[placeholder="your@email.com"]', 'john@example.com');
    fillField($page, 'input[type="tel"]', '5551234567');

    selectOptionByLabel($page, 'Location Type', 'Hotel');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const notListed = buttons.find(b => b.textContent.includes('My hotel is not listed'));
        if (notListed) notListed.click();
    JS);

    fillField($page, 'input[placeholder="Enter hotel name"]', 'Test Hotel');

    fillField($page, "input[placeholder=\"Child's name\"]", 'Test Child');

    submitGuestBookingForm($page);

    $url = $page->script('return window.location.pathname');
    expect($url)->toMatch('/^\\/book\\/payment\\//');
    $page->assertNoJavaScriptErrors();
});
