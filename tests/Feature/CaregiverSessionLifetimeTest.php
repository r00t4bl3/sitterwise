<?php

use App\Http\Middleware\ApplyCaregiverSessionLifetime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('issues the long-session marker cookie for a caregiver', function () {
    $caregiver = User::factory()->create([
        'role' => 'caregiver',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($caregiver)->get(route('dashboard'));

    $response->assertSuccessful();
    // Marker is excluded from encryption, so assert its plaintext value.
    $response->assertCookie(ApplyCaregiverSessionLifetime::MARKER, '1', false);
});

it('raises the session lifetime to 30 days when the marker is present', function () {
    $caregiver = User::factory()->create([
        'role' => 'caregiver',
        'email_verified_at' => now(),
    ]);

    expect(config('session.lifetime'))->toBe(120);

    $this->withUnencryptedCookie(ApplyCaregiverSessionLifetime::MARKER, '1')
        ->actingAs($caregiver)
        ->get(route('dashboard'))
        ->assertSuccessful();

    expect(config('session.lifetime'))->toBe(43200);
});

it('does not give admins the long-session marker or a raised lifetime', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertCookieMissing(ApplyCaregiverSessionLifetime::MARKER);
    expect(config('session.lifetime'))->toBe(120);
});

it('drops the marker when the request is no longer a caregiver', function () {
    // A guest carrying a stale marker (e.g. after logout) should have it cleared.
    $response = $this->withUnencryptedCookie(ApplyCaregiverSessionLifetime::MARKER, '1')
        ->get(route('login'));

    $response->assertCookieExpired(ApplyCaregiverSessionLifetime::MARKER);
});
