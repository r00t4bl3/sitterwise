<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('an admin can upload a profile photo', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post('/settings/profile/photo', [
            'profile_photo' => UploadedFile::fake()->image('me.jpg'),
        ])
        ->assertRedirect(route('profile.edit'));

    $admin->refresh();
    expect($admin->profile_photo_path)->toStartWith('profile-photos/');
    expect($admin->profile_photo_url)->not->toBeNull();
    Storage::disk('public')->assertExists($admin->profile_photo_path);
});

test('a superadmin can upload a profile photo', function () {
    Storage::fake('public');
    $superadmin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($superadmin)
        ->post('/settings/profile/photo', [
            'profile_photo' => UploadedFile::fake()->image('x.png'),
        ])
        ->assertRedirect(route('profile.edit'));

    expect($superadmin->fresh()->profile_photo_path)->not->toBeNull();
});

test('uploading a new photo deletes the previous one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/old.jpg', 'stale');
    $admin = User::factory()->create([
        'role' => 'admin',
        'profile_photo_path' => 'profile-photos/old.jpg',
    ]);

    $this->actingAs($admin)
        ->post('/settings/profile/photo', [
            'profile_photo' => UploadedFile::fake()->image('new.jpg'),
        ])
        ->assertRedirect(route('profile.edit'));

    Storage::disk('public')->assertMissing('profile-photos/old.jpg');
    Storage::disk('public')->assertExists($admin->fresh()->profile_photo_path);
});

test('a non-admin cannot upload a profile photo', function () {
    Storage::fake('public');

    foreach (['client', 'caregiver'] as $role) {
        $user = User::factory()->create(['role' => $role]);
        $original = $user->profile_photo_path;

        $this->actingAs($user)
            ->post('/settings/profile/photo', [
                'profile_photo' => UploadedFile::fake()->image('x.jpg'),
            ])
            ->assertForbidden();

        expect($user->fresh()->profile_photo_path)->toBe($original);
    }
});

test('the photo must be a valid image within the size limit', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);
    $original = $admin->profile_photo_path;

    $this->actingAs($admin)
        ->post('/settings/profile/photo', [
            'profile_photo' => UploadedFile::fake()->create('notes.txt', 10),
        ])
        ->assertSessionHasErrors('profile_photo');

    $this->actingAs($admin)
        ->post('/settings/profile/photo', [
            'profile_photo' => UploadedFile::fake()->image('huge.jpg')->size(2048),
        ])
        ->assertSessionHasErrors('profile_photo');

    expect($admin->fresh()->profile_photo_path)->toBe($original);
});
