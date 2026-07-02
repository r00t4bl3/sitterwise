<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

describe('Friendly Inertia error pages (#149)', function () {
    test('renders the inertia error page for a 404 in production', function () {
        $this->app['env'] = 'production';

        $this->get('/this-route-does-not-exist')
            ->assertStatus(404)
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/error')
                ->where('status', 404)
            );
    });

    test('renders the inertia error page for a 403 in production', function () {
        $this->app['env'] = 'production';
        $user = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('bookings.export'))
            ->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/error')
                ->where('status', 403)
            );
    });

    test('does not hijack JSON requests', function () {
        $this->app['env'] = 'production';

        $this->getJson('/this-route-does-not-exist')
            ->assertStatus(404)
            ->assertDontSee('errors/error');
    });

    test('leaves native error responses untouched in the testing environment', function () {
        $this->get('/this-route-does-not-exist')
            ->assertStatus(404)
            ->assertDontSee('errors/error');
    });
});
