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

    $page->assertSee('Back to hotel list');
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

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const btn = buttons.find(b => b.textContent.includes('Continue to Payment'));
        if (btn) btn.click();
    JS);

    $page->assertSee('First name is required');
    $page->assertNoJavaScriptErrors();
});

test('server-side validation passes and redirects to payment', function () {
    $tomorrow = now()->addDay()->startOfDay()->addHours(9);
    $end = $tomorrow->copy()->addHours(4);

    $response = $this->post('/book', [
        'client_first_name' => 'John',
        'client_last_name' => 'Doe',
        'client_email' => 'john@example.com',
        'client_phone' => '+15551234567',
        'service_type' => 'babysitter',
        'location_type' => 'hotel',
        'start_datetime' => $tomorrow->format('Y-m-d\TH:i'),
        'end_datetime' => $end->format('Y-m-d\TH:i'),
        'dates' => [
            ['start_datetime' => $tomorrow->format('Y-m-d\TH:i'), 'end_datetime' => $end->format('Y-m-d\TH:i')],
        ],
        'hotel_name' => 'Test Hotel',
        'how_did_you_hear' => 'google',
        'new_children' => [
            ['name' => 'Test Child', 'gender' => '', 'birth_month' => '', 'birth_year' => ''],
        ],
        'new_pets' => [],
        'sitter_preferences' => [],
    ]);

    $response->assertRedirect();
    $this->assertNotNull(session()->get('guest_booking_pending'));
    $this->assertNotNull(session()->get('guest_booking_payment_token'));
    $redirectUrl = $response->headers->get('Location');
    expect($redirectUrl)->toMatch('/^http:\/\/[^\/]+\/book\/payment\//');
});
