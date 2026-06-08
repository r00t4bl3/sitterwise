<?php

test('login page loads without JavaScript errors', function () {
    visit('/login')
        ->assertSee('Log in to your account')
        ->assertNoJavaScriptErrors();
});

test('forgot password page loads without JavaScript errors', function () {
    visit('/forgot-password')
        ->assertSee('Forgot password')
        ->assertNoJavaScriptErrors();
});
