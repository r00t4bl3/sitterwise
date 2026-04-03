<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Homepage
test('home page loads successfully', function () {
    $response = $this->get('/');
    $response->assertSuccessful();
});

// Auth pages
test('login page loads for guests', function () {
    $response = $this->get('/login');
    $response->assertSuccessful();
});

test('register page loads for guests', function () {
    $response = $this->get('/register');
    $response->assertSuccessful();
});

// Authenticated routes redirect to login when not authenticated
test('dashboard redirects to login when not authenticated', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('settings redirects to login when not authenticated', function () {
    $response = $this->get('/settings/profile');
    $response->assertRedirect('/login');
});

test('admin routes redirect to login when not authenticated', function () {
    $response = $this->get('/clients');
    $response->assertRedirect('/login');
});

// Authenticated routes work for valid users
test('authenticated user can access dashboard', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertSuccessful();
});

test('authenticated user can access settings', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get('/settings/profile');
    $response->assertSuccessful();
});

// Admin routes are protected
test('admin routes are protected from non-admin users', function () {
    $user = User::factory()->create(['role' => 'client']);
    $response = $this->actingAs($user)->get('/clients');
    $response->assertForbidden();
});

// API health check
test('health check endpoint is accessible', function () {
    $response = $this->get('/up');
    $response->assertSuccessful();
});

// 404 handling
test('non-existent routes return 404', function () {
    $response = $this->get('/this-route-does-not-exist');
    $response->assertNotFound();
});
