<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function registerViaJs($page, string $firstName, string $lastName, string $phone, string $email, string $password): void
{
    $page->script(<<<JS
        const setNativeValue = (el, val) => {
            const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
            setter.call(el, val);
            el.dispatchEvent(new Event('input', { bubbles: true }));
        };

        setNativeValue(document.querySelector('#first_name'), '{$firstName}');
        setNativeValue(document.querySelector('#last_name'), '{$lastName}');
        setNativeValue(document.querySelector('input[type="tel"]'), '{$phone}');
        setNativeValue(document.querySelector('#email'), '{$email}');
        setNativeValue(document.querySelector('#password'), '{$password}');
        setNativeValue(document.querySelector('#password_confirmation'), '{$password}');
    JS);

    $page->script(<<<'JS'
        document.querySelector('button[data-test="register-user-button"]').click();
    JS);
}

test('registration page can be viewed', function () {
    visit('/register')
        ->assertSee('Create an account')
        ->assertNoJavaScriptErrors();
});

test('new user can register with valid data', function () {
    $page = visit('/register');

    registerViaJs($page, 'John', 'Doe', '5551234567', 'john@example.com', 'password');

    $page->assertPathIs('/dashboard');
    $page->assertSee('Welcome back');

    $this->assertAuthenticated();

    $user = User::where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe('client');
});

test('register with missing required fields shows validation errors', function () {
    $page = visit('/register');

    $page->script(<<<'JS'
        const form = document.querySelector('form');
        if (form) {
            form.noValidate = true;
            form.requestSubmit();
        }
    JS);

    $page->assertSee('first name field is required');
    $page->assertSee('last name field is required');
    $page->assertSee('email field is required');
});

test('register with mismatched passwords shows validation error', function () {
    $page = visit('/register');

    $page->script(<<<'JS'
        const setNativeValue = (el, val) => {
            const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
            setter.call(el, val);
            el.dispatchEvent(new Event('input', { bubbles: true }));
        };

        setNativeValue(document.querySelector('#first_name'), 'John');
        setNativeValue(document.querySelector('#last_name'), 'Doe');
        setNativeValue(document.querySelector('input[type="tel"]'), '5551234567');
        setNativeValue(document.querySelector('#email'), 'john@example.com');
        setNativeValue(document.querySelector('#password'), 'password123');
        setNativeValue(document.querySelector('#password_confirmation'), 'different');
    JS);

    $page->script(<<<'JS'
        document.querySelector('button[data-test="register-user-button"]').click();
    JS);

    $page->assertSee('password field confirmation does not match');
});

test('register with duplicate email shows validation error', function () {
    User::factory()->create(['email' => 'john@example.com']);

    $page = visit('/register');

    registerViaJs($page, 'John', 'Doe', '5551234567', 'john@example.com', 'password');

    $page->assertSee('has already been taken');
});
