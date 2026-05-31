<?php

test('appearance middleware applies dark class and script when cookie is dark', function () {
    $response = $this->withUnencryptedCookie('appearance', 'dark')->get('/login');

    $response->assertSuccessful();
    $response->assertSee('class="dark"', false);
    $response->assertSee("appearance = 'dark'", false);
});

test('appearance middleware defaults to system when no cookie is set', function () {
    $response = $this->get('/login');

    $response->assertSuccessful();
    $response->assertDontSee('class="dark"', false);
    $response->assertSee("appearance = 'system'", false);
});

test('appearance middleware applies light mode when cookie is light', function () {
    $response = $this->withUnencryptedCookie('appearance', 'light')->get('/login');

    $response->assertSuccessful();
    $response->assertDontSee('class="dark"', false);
    $response->assertSee("appearance = 'light'", false);
});
