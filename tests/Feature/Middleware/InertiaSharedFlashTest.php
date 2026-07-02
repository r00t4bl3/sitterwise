<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Inertia shared flash', function () {
    test('shares warning and info flash to the frontend', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([
                'warning' => 'Payment charged, but caregiver payout failed.',
                'info' => 'Heads up.',
            ])
            ->get('/dashboard')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('flash.warning', 'Payment charged, but caregiver payout failed.')
                ->where('flash.info', 'Heads up.')
            );
    });

    test('still shares success and error flash', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([
                'success' => 'Payment processed and charged successfully.',
                'error' => 'Booking details saved, but payment failed: Card declined',
            ])
            ->get('/dashboard')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('flash.success', 'Payment processed and charged successfully.')
                ->where('flash.error', 'Booking details saved, but payment failed: Card declined')
            );
    });
});
