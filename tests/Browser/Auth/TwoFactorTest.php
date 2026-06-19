<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('two-factor challenge page renders after login with 2FA enabled', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('password'),
    ]);

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $page = visit('/login');

    loginViaJs($page, 'john@example.com', 'password');

    $page->assertPathIs('/two-factor-challenge');
    $page->assertSee('Authentication code');
});

test('two-factor challenge can toggle to recovery code mode', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('password'),
    ]);

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $page = visit('/login');

    loginViaJs($page, 'john@example.com', 'password');

    $page->assertPathIs('/two-factor-challenge');

    $page->script(<<<'JS'
        document.querySelector('button[type="button"]').click();
    JS);

    $page->assertSee('Recovery code');
});

test('two-factor challenge shows error with incorrect recovery code', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('password'),
    ]);

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $page = visit('/login');

    loginViaJs($page, 'john@example.com', 'password');

    $page->assertPathIs('/two-factor-challenge');

    $page->script(<<<'JS'
        document.querySelector('button[type="button"]').click();
    JS);

    $page->assertSee('Recovery code');

    fillField($page, 'input[name="recovery_code"]', 'wrong-recovery-code');

    $page->script(<<<'JS'
        document.querySelector('button[type="submit"]').click();
    JS);

    $page->assertSee('two factor recovery code was invalid');
});
