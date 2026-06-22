<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('unknown routes return 404', function () {
    $response = $this->get('/this-route-does-not-exist');

    $response->assertStatus(404);
});

test('invalid reference token returns 404', function () {
    $response = $this->get('/references/invalid-token-that-does-not-exist');

    $response->assertStatus(404);
});
