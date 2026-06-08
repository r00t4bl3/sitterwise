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
