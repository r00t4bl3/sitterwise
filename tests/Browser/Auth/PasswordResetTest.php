<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

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

test('reset password page renders with valid token', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
    ]);

    $token = Password::broker('users')->createToken($user);

    $page = visit("/reset-password/{$token}?email=john@example.com");

    $page->assertSee('Reset password')
        ->assertNoJavaScriptErrors();
});

test('user can reset password with valid token', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
    ]);

    $token = Password::broker('users')->createToken($user);

    $page = visit("/reset-password/{$token}?email=john@example.com");

    fillField($page, '#password', 'new-password-123');
    fillField($page, '#password_confirmation', 'new-password-123');

    clickElement($page, 'button[data-test="reset-password-button"]');

    $page->assertPathIs('/login');
    $page->assertSee('password has been reset');
});

test('user sees error with invalid token', function () {
    User::factory()->create([
        'email' => 'john@example.com',
    ]);

    $page = visit('/reset-password/invalid-token?email=john@example.com');

    fillField($page, '#password', 'new-password-123');
    fillField($page, '#password_confirmation', 'new-password-123');

    clickElement($page, 'button[data-test="reset-password-button"]');

    $page->assertSee('password reset token is invalid');
});
