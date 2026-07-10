<?php

use App\Enums\CaregiverStatus;
use App\Mail\CaregiverArchiveWarningMail;
use App\Mail\CaregiverOnHoldCheckinMail;
use App\Models\Caregiver;
use App\Models\CaregiverPause;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

function makeCaregiver(array $overrides = []): Caregiver
{
    $user = User::factory()->create(['role' => 'caregiver']);

    $data = array_merge([
        'user_id' => $user->id,
        'first_name' => 'Test',
        'last_name' => 'Caregiver',
        'slug' => 'test-caregiver-'.uniqid(),
        'phone' => '619-555-0100',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'date_of_birth' => '2000-01-01',
    ], $overrides);

    return Caregiver::create($data);
}

describe('pause', function () {
    it('allows an active caregiver to pause their account', function () {
        $caregiver = makeCaregiver(['status' => CaregiverStatus::Active]);
        $user = $caregiver->user;

        actingAs($user)
            ->post(route('settings.caregiver.pause.store'), [
                'resume_by' => now()->addMonth()->format('Y-m-d'),
                'pause_reason' => 'Going on vacation',
            ])
            ->assertRedirect(route('settings.caregiver.pause'))
            ->assertSessionHas('success');

        $caregiver->refresh();
        expect($caregiver->status)->toBe(CaregiverStatus::OnHold);

        $pause = CaregiverPause::active()->where('caregiver_id', $caregiver->id)->first();
        expect($pause)->not->toBeNull();
        expect($pause->pause_reason)->toBe('Going on vacation');
        expect($pause->resume_by->format('Y-m-d'))->toBe(now()->addMonth()->format('Y-m-d'));
    });

    it('allows pause without optional fields', function () {
        $caregiver = makeCaregiver(['status' => CaregiverStatus::Active]);
        $user = $caregiver->user;

        actingAs($user)
            ->post(route('settings.caregiver.pause.store'))
            ->assertSessionHas('success');

        expect($caregiver->fresh()->status)->toBe(CaregiverStatus::OnHold);
    });

    it('does not allow non-active caregivers to pause', function () {
        $caregiver = makeCaregiver(['status' => CaregiverStatus::OnHold]);
        $user = $caregiver->user;

        actingAs($user)
            ->post(route('settings.caregiver.pause.store'))
            ->assertSessionHas('error');

        expect($caregiver->fresh()->status)->toBe(CaregiverStatus::OnHold);
    });

    it('does not allow non-caregiver users to view the pause page', function () {
        $user = User::factory()->create(['role' => 'admin']);

        actingAs($user)
            ->get(route('settings.caregiver.pause'))
            ->assertRedirect(route('profile.edit'));
    });
});

describe('resume', function () {
    it('allows an on-hold caregiver to resume', function () {
        $caregiver = makeCaregiver(['status' => CaregiverStatus::OnHold]);
        $user = $caregiver->user;

        CaregiverPause::create([
            'caregiver_id' => $caregiver->id,
            'paused_at' => now()->subDays(10),
        ]);

        actingAs($user)
            ->post(route('settings.caregiver.resume'))
            ->assertRedirect(route('settings.caregiver.pause'))
            ->assertSessionHas('success');

        expect($caregiver->fresh()->status)->toBe(CaregiverStatus::Active);

        $pause = CaregiverPause::where('caregiver_id', $caregiver->id)->first();
        expect($pause->resumed_at)->not->toBeNull();
    });

    it('does not allow non-hold caregivers to resume', function () {
        $caregiver = makeCaregiver(['status' => CaregiverStatus::Active]);
        $user = $caregiver->user;

        actingAs($user)
            ->post(route('settings.caregiver.resume'))
            ->assertSessionHas('error');
    });
});

describe('commands', function () {
    it('sends check-in emails at 30 day threshold', function () {
        Mail::fake();

        $caregiver = makeCaregiver(['status' => CaregiverStatus::OnHold]);

        CaregiverPause::create([
            'caregiver_id' => $caregiver->id,
            'paused_at' => now()->subDays(30),
        ]);

        artisan('app:check-in-on-hold-caregivers')->assertSuccessful();

        Mail::assertQueued(CaregiverOnHoldCheckinMail::class, fn ($mail) => $mail->tier === 'checkin');
    });

    it('sends archive warning at 166 days on hold', function () {
        Mail::fake();

        $caregiver = makeCaregiver(['status' => CaregiverStatus::OnHold]);

        CaregiverPause::create([
            'caregiver_id' => $caregiver->id,
            'paused_at' => now()->subDays(170),
        ]);

        artisan('app:archive-long-term-inactive')->assertSuccessful();

        Mail::assertQueued(CaregiverArchiveWarningMail::class);
    });

    it('archives caregiver to inactive at 180 days on hold', function () {
        $caregiver = makeCaregiver(['status' => CaregiverStatus::OnHold]);

        CaregiverPause::create([
            'caregiver_id' => $caregiver->id,
            'paused_at' => now()->subDays(185),
        ]);

        artisan('app:archive-long-term-inactive')->assertSuccessful();

        expect($caregiver->fresh()->status)->toBe(CaregiverStatus::Inactive);
    });

    it('does not re-inactivate a reactivated caregiver with a dangling pause, and clears the stale pause', function () {
        // Admin set her Active without resolving the old pause (not via the
        // Resume flow), leaving a 185-day-old active pause behind.
        $caregiver = makeCaregiver(['status' => CaregiverStatus::Active]);

        $pause = CaregiverPause::create([
            'caregiver_id' => $caregiver->id,
            'paused_at' => now()->subDays(185),
        ]);

        artisan('app:archive-long-term-inactive')->assertSuccessful();

        expect($caregiver->fresh()->status)->toBe(CaregiverStatus::Active)
            ->and($pause->fresh()->resumed_at)->not->toBeNull();
    });

    it('does not send an archive warning to a reactivated caregiver', function () {
        Mail::fake();

        $caregiver = makeCaregiver(['status' => CaregiverStatus::Active]);

        $pause = CaregiverPause::create([
            'caregiver_id' => $caregiver->id,
            'paused_at' => now()->subDays(170),
        ]);

        artisan('app:archive-long-term-inactive')->assertSuccessful();

        Mail::assertNotQueued(CaregiverArchiveWarningMail::class);
        expect($caregiver->fresh()->status)->toBe(CaregiverStatus::Active)
            ->and($pause->fresh()->resumed_at)->not->toBeNull();
    });
});

describe('admin resume', function () {
    it('allows an admin to resume a paused caregiver', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = makeCaregiver(['status' => CaregiverStatus::OnHold]);

        CaregiverPause::create([
            'caregiver_id' => $caregiver->id,
            'paused_at' => now()->subDays(10),
            'resume_by' => now()->addDays(20),
            'pause_reason' => 'Personal leave',
        ]);

        actingAs($admin)
            ->post(route('caregivers.resume', $caregiver->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        expect($caregiver->fresh()->status)->toBe(CaregiverStatus::Active);

        $pause = CaregiverPause::where('caregiver_id', $caregiver->id)->first();
        expect($pause->resumed_at)->not->toBeNull();
    });

    it('returns error when caregiver has no active pause', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = makeCaregiver(['status' => CaregiverStatus::Active]);

        actingAs($admin)
            ->post(route('caregivers.resume', $caregiver->id))
            ->assertRedirect()
            ->assertSessionHas('error');
    });

    it('does not allow a non-admin to resume a caregiver', function () {
        $caregiver = makeCaregiver(['status' => CaregiverStatus::OnHold]);
        $user = $caregiver->user;

        CaregiverPause::create([
            'caregiver_id' => $caregiver->id,
            'paused_at' => now()->subDays(10),
        ]);

        actingAs($user)
            ->post(route('caregivers.resume', $caregiver->id))
            ->assertForbidden();
    });
});

describe('admin status change resolves pauses', function () {
    it('resolves an active pause when an admin sets the caregiver to Active via the status update', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = makeCaregiver(['status' => CaregiverStatus::OnHold]);

        $pause = CaregiverPause::create([
            'caregiver_id' => $caregiver->id,
            'paused_at' => now()->subDays(30),
        ]);

        actingAs($admin)
            ->put(route('caregivers.update', $caregiver->id), ['status' => CaregiverStatus::Active->value])
            ->assertRedirect();

        expect($caregiver->fresh()->status)->toBe(CaregiverStatus::Active)
            ->and($pause->fresh()->resumed_at)->not->toBeNull();
    });

    it('leaves an active pause untouched when an admin sets a non-Active status', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = makeCaregiver(['status' => CaregiverStatus::OnHold]);

        $pause = CaregiverPause::create([
            'caregiver_id' => $caregiver->id,
            'paused_at' => now()->subDays(30),
        ]);

        actingAs($admin)
            ->put(route('caregivers.update', $caregiver->id), ['status' => CaregiverStatus::Inactive->value])
            ->assertRedirect();

        expect($caregiver->fresh()->status)->toBe(CaregiverStatus::Inactive)
            ->and($pause->fresh()->resumed_at)->toBeNull();
    });
});
