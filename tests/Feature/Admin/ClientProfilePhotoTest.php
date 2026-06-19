<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

test('client profile photo can be uploaded successfully', function () {
    Storage::fake('public');

    $client = Client::factory()->create();
    $file = UploadedFile::fake()->image('photo.jpg');

    $this->actingAs($this->admin)
        ->post(route('clients.updateProfilePhoto', $client), [
            'profile_photo' => $file,
        ])
        ->assertRedirect(route('clients.edit', $client))
        ->assertSessionHas('success');

    $client->user->refresh();

    expect($client->user->profile_photo_path)->not->toBeNull();
    expect($client->user->profile_photo_path)->not->toBe('0');

    Storage::disk('public')->assertExists($client->user->profile_photo_path);
});

test('client profile photo upload fails gracefully when storage write fails', function () {
    $diskMock = Mockery::mock(FilesystemAdapter::class);
    $diskMock->shouldReceive('putFileAs')->andReturn(false);

    $managerMock = Mockery::mock(FilesystemManager::class);
    $managerMock->shouldReceive('disk')->with('public')->andReturn($diskMock);

    $this->app->instance('filesystem', $managerMock);
    $this->app->instance(FilesystemManager::class, $managerMock);

    $user = User::factory()->create(['role' => 'client', 'profile_photo_path' => null]);
    $client = Client::factory()->create(['user_id' => $user->id]);
    $file = UploadedFile::fake()->image('photo.jpg');

    $this->actingAs($this->admin)
        ->post(route('clients.updateProfilePhoto', $client), [
            'profile_photo' => $file,
        ])
        ->assertRedirect(route('clients.edit', $client))
        ->assertSessionHas('error');

    $client->user->refresh();

    expect($client->user->profile_photo_path)->toBeNull();
});
