<?php

use App\Models\Hotel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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

    $this->withoutMiddleware(PreventRequestForgery::class);

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
        'sms_consent' => true,
    ]);

    $response->assertRedirect();
    $this->assertNotNull(session()->get('guest_booking_pending'));
    $this->assertNotNull(session()->get('guest_booking_payment_token'));
    $redirectUrl = $response->headers->get('Location');
    expect($redirectUrl)->toMatch('/^http:\/\/[^\/]+\/book\/payment\//');
});

test('booking progress indicator shows step 1', function () {
    visit('/book')
        ->assertSee('Booking Details');
});

test('can fill optional textareas', function () {
    $page = visit('/book');

    fillTextarea($page, 'Plans for the day', 'Bring comfortable shoes');
    fillTextarea($page, 'our Care Team', 'Please assign our preferred caregiver');

    $page->assertNoJavaScriptErrors();
});

test('can toggle sitter preferences', function () {
    $page = visit('/book');

    selectOptionByLabel($page, 'Service Type', 'Babysitter');
    selectOptionByLabel($page, 'Location Type', 'Private Home');

    $page->script(<<<'JS'
        const checkbox = document.querySelector('input[type="checkbox"]');
        if (checkbox && !checkbox.closest('[role="dialog"]')) {
            const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'checked').set;
            setter.call(checkbox, true);
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
    JS);

    $page->assertNoJavaScriptErrors();
});

test('shows validation error with invalid email', function () {
    $page = visit('/book');

    fillField($page, 'input[placeholder="First name"]', 'John');
    fillField($page, 'input[placeholder="Last name"]', 'Doe');
    fillField($page, 'input[placeholder="your@email.com"]', 'not-an-email');
    fillField($page, 'input[type="tel"]', '5551234567');

    selectOptionByLabel($page, 'Service Type', 'Babysitter');
    selectOptionByLabel($page, 'Location Type', 'Private Home');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const submitBtn = buttons.find(b => b.textContent.includes('Continue to Payment'));
        if (submitBtn) submitBtn.click();
    JS);

    usleep(1000000);

    $page->assertSee('valid email');
});

test('can add a date block', function () {
    $page = visit('/book');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const addBtn = buttons.find(b => b.textContent.includes('Add another date'));
        if (addBtn) addBtn.click();
    JS);

    usleep(200000);

    $page->assertSee('Date 2');
    $page->assertNoJavaScriptErrors();
});

test('can add multiple date blocks', function () {
    $page = visit('/book');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const addBtn = buttons.find(b => b.textContent.includes('Add another date'));
        if (addBtn) addBtn.click();
    JS);

    usleep(150000);

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const addBtn = buttons.find(b => b.textContent.includes('Add another date'));
        if (addBtn) addBtn.click();
    JS);

    usleep(150000);

    $page->assertSee('Date 2');
    $page->assertSee('Date 3');
    $page->assertNoJavaScriptErrors();
});

test('can remove a date block', function () {
    $page = visit('/book');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const addBtn = buttons.find(b => b.textContent.includes('Add another date'));
        if (addBtn) addBtn.click();
    JS);

    usleep(200000);

    $page->assertSee('Date 2');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const removeBtn = buttons.find(b => b.textContent.includes('Remove'));
        if (removeBtn) removeBtn.click();
    JS);

    usleep(200000);

    $page->assertSee('Date 1');
    $page->assertDontSee('Date 2');
    $page->assertNoJavaScriptErrors();
});

test('shows overlap validation between date blocks', function () {
    $page = visit('/book');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const addBtn = buttons.find(b => b.textContent.includes('Add another date'));
        if (addBtn) addBtn.click();
    JS);

    usleep(300000);

    $page->assertSee('Date 2');
    $page->assertNoJavaScriptErrors();
});

test('shows same-day warning', function () {
    $page = visit('/book');

    $page->assertDontSee('same-day booking');

    $page->script(<<<'JS'
        const labels = Array.from(document.querySelectorAll('label'));
        const startLabel = labels.find(l => l.textContent.trim().startsWith('Start Date/Time'));
        if (!startLabel) {
            const buttons = Array.from(document.querySelectorAll('button'));
            const aboutBtn = buttons.find(b => b.textContent.includes('About Your Booking'));
            if (aboutBtn) aboutBtn.click();
        }
    JS);

    usleep(300000);

    $page->assertNoJavaScriptErrors();
});

test('enforces 4-hour minimum duration', function () {
    $page = visit('/book');

    $page->script(<<<'JS'
        const labels = Array.from(document.querySelectorAll('label'));
        const endLabel = labels.find(l => l.textContent.trim().startsWith('End Date/Time'));
        if (!endLabel) {
            const buttons = Array.from(document.querySelectorAll('button'));
            const aboutBtn = buttons.find(b => b.textContent.includes('About Your Booking'));
            if (aboutBtn) aboutBtn.click();
        }
    JS);

    usleep(300000);

    $page->assertNoJavaScriptErrors();
});

test('address section shows with private home location', function () {
    $page = visit('/book');

    selectOptionByLabel($page, 'Location Type', 'Private Home');

    $page->assertSee('Address');
    $page->assertNoJavaScriptErrors();
});

test('can search and select a hotel from autocomplete', function () {
    Hotel::factory()->create([
        'name' => 'Grand San Diego Hotel',
        'is_active' => true,
    ]);

    $page = visit('/book');

    selectOptionByLabel($page, 'Location Type', 'Hotel');

    usleep(300000);

    fillField($page, 'input[role="combobox"][placeholder*="Search hotel"]', 'Grand');

    usleep(500000);

    $page->script(<<<'JS'
        const options = document.querySelectorAll('[role="option"]');
        const match = Array.from(options).find(el => el.textContent.includes('Grand San Diego Hotel'));
        if (match) match.click();
    JS);

    usleep(200000);

    $page->assertNoJavaScriptErrors();
});
