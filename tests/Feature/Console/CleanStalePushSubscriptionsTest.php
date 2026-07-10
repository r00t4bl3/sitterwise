<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\PushSubscription;

uses(RefreshDatabase::class);

describe('app:clean-push-subscriptions', function () {
    test('deletes subscriptions whose owner was hard-deleted but keeps live ones', function () {
        $alive = User::factory()->create();
        $gone = User::factory()->create();

        $alive->updatePushSubscription('https://push/alive', 'k', 't');
        $gone->updatePushSubscription('https://push/gone', 'k', 't');

        // Hard-delete the owner — its subscription row is now orphaned.
        $gone->forceDelete();

        expect(PushSubscription::count())->toBe(2);

        $this->artisan('app:clean-push-subscriptions')
            ->expectsOutputToContain('Push subscriptions cleaned: 1 orphaned.')
            ->assertSuccessful();

        expect(PushSubscription::where('endpoint', 'https://push/alive')->exists())->toBeTrue()
            ->and(PushSubscription::where('endpoint', 'https://push/gone')->exists())->toBeFalse();
    });

    test('preserves subscriptions whose owner is only soft-deleted', function () {
        $user = User::factory()->create();
        $user->updatePushSubscription('https://push/soft', 'k', 't');

        $user->delete(); // soft delete — the owner row still exists

        $this->artisan('app:clean-push-subscriptions')
            ->expectsOutputToContain('0 orphaned')
            ->assertSuccessful();

        expect(PushSubscription::where('endpoint', 'https://push/soft')->exists())->toBeTrue();
    });

    test('with --days it prunes long-unrefreshed subscriptions and keeps recent ones', function () {
        $user = User::factory()->create();

        $stale = $user->updatePushSubscription('https://push/stale', 'k', 't');
        $stale->timestamps = false;
        $stale->updated_at = now()->subDays(90);
        $stale->save();

        $user->updatePushSubscription('https://push/fresh', 'k', 't');

        $this->artisan('app:clean-push-subscriptions', ['--days' => 30])
            ->expectsOutputToContain('1 unrefreshed')
            ->assertSuccessful();

        expect(PushSubscription::where('endpoint', 'https://push/stale')->exists())->toBeFalse()
            ->and(PushSubscription::where('endpoint', 'https://push/fresh')->exists())->toBeTrue();
    });

    test('without --days, a long-idle but valid subscription is left untouched', function () {
        $user = User::factory()->create();
        $stale = $user->updatePushSubscription('https://push/idle', 'k', 't');
        $stale->timestamps = false;
        $stale->update(['updated_at' => now()->subDays(365)]);

        $this->artisan('app:clean-push-subscriptions')->assertSuccessful();

        expect(PushSubscription::where('endpoint', 'https://push/idle')->exists())->toBeTrue();
    });
});
