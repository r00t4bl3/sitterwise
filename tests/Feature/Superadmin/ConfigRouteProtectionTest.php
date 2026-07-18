<?php

use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Hotel;
use App\Models\PricingRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Regression tests for the platform-configuration routes. These previously
 * sat in the plain auth group, so any logged-in client or caregiver could
 * edit the pricing rules driving their own charges and payouts.
 */
describe('Config route protection', function () {
    function clientUser(): User
    {
        $user = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        Client::factory()->for($user)->create();

        return $user;
    }

    function caregiverRoleUser(): User
    {
        return User::factory()->create(['role' => 'caregiver', 'email_verified_at' => now()]);
    }

    $mutations = [
        ['post', '/pricing-rules'],
        ['post', '/hotels'],
        ['post', '/certifications'],
        ['post', '/specialties'],
        ['post', '/locations'],
        ['post', '/attributes'],
        ['post', '/quick-links'],
        ['post', '/zip-codes'],
    ];

    test('a client cannot reach any config mutation endpoint', function () use ($mutations) {
        $user = clientUser();

        foreach ($mutations as [$method, $uri]) {
            $this->actingAs($user)->{$method}($uri, [])->assertForbidden();
        }
    });

    test('a caregiver cannot reach any config mutation endpoint', function () use ($mutations) {
        $user = caregiverRoleUser();

        foreach ($mutations as [$method, $uri]) {
            $this->actingAs($user)->{$method}($uri, [])->assertForbidden();
        }
    });

    test('a client cannot update or delete an existing pricing rule or hotel', function () {
        $user = clientUser();
        $rule = PricingRule::factory()->create(['service_type' => 'Babysitter', 'number_of_children' => 1, 'is_for_pets' => false]);
        $hotel = Hotel::factory()->create();

        $this->actingAs($user)->put("/pricing-rules/{$rule->id}", [])->assertForbidden();
        $this->actingAs($user)->delete("/pricing-rules/{$rule->id}")->assertForbidden();
        $this->actingAs($user)->put("/hotels/{$hotel->id}", [])->assertForbidden();
        $this->actingAs($user)->delete("/hotels/{$hotel->id}")->assertForbidden();
    });

    test('a client cannot view the pricing rule index or simulator', function () {
        $user = clientUser();

        $this->actingAs($user)->get('/pricing-rules')->assertForbidden();
        $this->actingAs($user)->get('/pricing-rules/simulator')->assertForbidden();
    });

    test('an admin can still manage pricing rules', function () {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get('/pricing-rules')->assertOk();
    });

    test('a super admin can still manage pricing rules', function () {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);

        $this->actingAs($superAdmin)->get('/pricing-rules')->assertOk();
    });
});
