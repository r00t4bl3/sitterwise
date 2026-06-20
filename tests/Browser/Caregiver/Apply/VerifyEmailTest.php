<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('verify email page renders', function () {
    $page = visit('/caregiver/apply/verify-email');

    $page->assertSee('Verify Your Email')
        ->assertSee('Email address')
        ->assertSee('Send Verification Code')
        ->assertNoJavaScriptErrors();
});

test('enter email and send OTP', function () {
    $email = 'applicant-'.time().'@example.com';

    $page = visit('/caregiver/apply/verify-email');

    fillField($page, 'input#email', $email);

    $page->script(<<<'JS'
        document.querySelector('button[type="submit"]').click();
    JS);

    $page->waitForText('Enter Verification Code')
        ->assertSee('We sent a code to')
        ->assertNoJavaScriptErrors();
});

test('verify with correct OTP bypass', function () {
    $email = 'applicant-'.time().'@example.com';

    $page = visit('/caregiver/apply/verify-email');

    fillField($page, 'input#email', $email);

    $page->script(<<<'JS'
        document.querySelector('button[type="submit"]').click();
    JS);

    $page->waitForText('Enter Verification Code');

    fillField($page, 'input#otp', '000000');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const verifyBtn = buttons.find(b => b.textContent.includes('Verify & Continue'));
        if (verifyBtn) verifyBtn.click();
    JS);

    $page->waitForText('Join the Sitterwise Team');
    $page->assertPathIs('/caregiver/apply');
});

test('submit incorrect OTP shows error', function () {
    $email = 'applicant-'.time().'@example.com';

    $page = visit('/caregiver/apply/verify-email');

    fillField($page, 'input#email', $email);

    $page->script(<<<'JS'
        document.querySelector('button[type="submit"]').click();
    JS);

    $page->waitForText('Enter Verification Code');

    fillField($page, 'input#otp', '123456');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const verifyBtn = buttons.find(b => b.textContent.includes('Verify & Continue'));
        if (verifyBtn) verifyBtn.click();
    JS);

    $page->assertSee('Invalid verification code')
        ->assertNoJavaScriptErrors();
});
