<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\SettingsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

describe('Settings store', function () {
    test('get returns typed values and a default for missing keys', function () {
        expect(Settings::get('lifesaver.hours_unclaimed'))->toBe(10);
        expect(Settings::get('lifesaver.short_notice_hours'))->toBe(18);
        expect(Settings::get('lifesaver.bonus'))->toBe(15.0);
        expect(Settings::get('missing.key', 'fallback'))->toBe('fallback');
    });

    test('the minimum booking hours setting seeds as an int defaulting to 4', function () {
        expect(Settings::get('bookings.minimum_hours'))->toBe(4);
    });

    test('update accepts an integer for the minimum booking hours setting', function () {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->put('/app-settings', ['settings' => ['bookings.minimum_hours' => 3]])
            ->assertSessionHasNoErrors();

        expect(Settings::get('bookings.minimum_hours'))->toBe(3);
    });

    test('update rejects a non-integer for the minimum booking hours setting', function () {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->put('/app-settings', ['settings' => ['bookings.minimum_hours' => 'abc']])
            ->assertSessionHasErrors('settings.bookings.minimum_hours');

        expect(Settings::get('bookings.minimum_hours'))->toBe(4);
    });

    test('tier-1 config-migrated settings are seeded from config', function () {
        expect(Settings::get('trustline.jobs_threshold'))->toBe((int) config('trustline.jobs_threshold'));
        expect(Settings::get('trustline.reward_amount'))->toBe((int) config('trustline.reward_amount'));
        expect(Settings::get('caregiver.buffer_minutes'))->toBe((int) config('caregiver.buffer_minutes'));
    });

    test('set persists a new value and busts the cache', function () {
        Settings::get('lifesaver.hours_unclaimed'); // warm the cache first

        Settings::set('lifesaver.hours_unclaimed', 8);

        expect(Settings::get('lifesaver.hours_unclaimed'))->toBe(8);
        expect(Setting::where('key', 'lifesaver.hours_unclaimed')->value('value'))->toBe('8');
    });

    test('re-seeding is idempotent and preserves edited values', function () {
        Settings::set('lifesaver.hours_unclaimed', 7);
        $countBefore = Setting::count();

        $this->seed(SettingsSeeder::class);

        expect(Settings::get('lifesaver.hours_unclaimed'))->toBe(7);
        expect(Setting::count())->toBe($countBefore);
    });
});

describe('Settings page (super-admin only)', function () {
    test('super admin can view the settings page grouped', function () {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->get(route('app-settings.index'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('superadmin/settings/index')
                ->has('settingGroups.lifesaver', 3)
            );
    });

    test('non-super-admins are forbidden', function (string $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('app-settings.index'))
            ->assertForbidden();
    })->with(['admin', 'caregiver', 'client']);

    test('super admin can update a setting', function () {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->put(route('app-settings.update'), [
                'settings' => ['lifesaver.hours_unclaimed' => 6],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        expect(Settings::get('lifesaver.hours_unclaimed'))->toBe(6);
    });

    test('update rejects a non-integer for an int setting', function () {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->put(route('app-settings.update'), [
                'settings' => ['lifesaver.hours_unclaimed' => 'abc'],
            ])
            ->assertSessionHasErrors('settings.lifesaver.hours_unclaimed');

        expect(Settings::get('lifesaver.hours_unclaimed'))->toBe(10);
    });
});
