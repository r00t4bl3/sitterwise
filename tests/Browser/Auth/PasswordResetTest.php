<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

function fillEmailViaJs($page, string $email): void
{
    $page->script(<<<JS
        const setNativeValue = (el, val) => {
            const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
            setter.call(el, val);
            el.dispatchEvent(new Event('input', { bubbles: true }));
        };

        setNativeValue(document.querySelector('#email'), '{$email}');
    JS);

    $page->script(<<<'JS'
        document.querySelector('button[data-test="email-password-reset-link-button"]').click();
    JS);
}

test('forgot password page can be viewed', function () {
    visit('/forgot-password')
        ->assertSee('Forgot password')
        ->assertNoJavaScriptErrors();
});

test('forgot password sends reset link for valid email', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
    ]);

    $page = visit('/forgot-password');

    fillEmailViaJs($page, 'john@example.com');

    $page->assertSee('We have emailed your password reset link.');
});

test('forgot password shows error for non-existent email', function () {
    $page = visit('/forgot-password');

    fillEmailViaJs($page, 'nonexistent@example.com');

    $page->assertSee("We can't find a user with that email address.");
});

test('user can navigate back to login from forgot password', function () {
    $page = visit('/forgot-password');

    $page->script(<<<'JS'
        document.querySelector('a[href="/login"]').click();
    JS);

    $page->assertPathIs('/login');
});
