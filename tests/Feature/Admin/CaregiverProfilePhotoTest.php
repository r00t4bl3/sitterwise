<?php

use App\Models\Caregiver;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CertificationTypeSeeder::class);
    $this->seed(SpecialtyTypeSeeder::class);
    $this->seed(LocationSeeder::class);
    $this->seed(AttributeDefinitionSeeder::class);
    $this->admin = User::factory()->create(['role' => 'admin']);
});

test('caregiver profile photo can be uploaded successfully', function () {
    Storage::fake('public');

    $caregiver = Caregiver::factory()->create();
    $file = UploadedFile::fake()->image('photo.jpg');

    actingAs($this->admin)
        ->post(route('caregivers.updateProfilePhoto', $caregiver), [
            'profile_photo' => $file,
        ])
        ->assertRedirect(route('caregivers.edit', $caregiver))
        ->assertSessionHas('success');

    $caregiver->user->refresh();

    expect($caregiver->user->profile_photo_path)->not->toBeNull();
    expect($caregiver->user->profile_photo_path)->not->toBe('0');

    Storage::disk('public')->assertExists($caregiver->user->profile_photo_path);
});

test('caregiver profile photo upload fails gracefully when storage write fails', function () {
    $diskMock = Mockery::mock(FilesystemAdapter::class);
    $diskMock->shouldReceive('putFileAs')->andReturn(false);

    $managerMock = Mockery::mock(FilesystemManager::class);
    $managerMock->shouldReceive('disk')->with('public')->andReturn($diskMock);

    $this->app->instance('filesystem', $managerMock);
    $this->app->instance(FilesystemManager::class, $managerMock);

    $user = User::factory()->create(['role' => 'caregiver', 'profile_photo_path' => null]);
    $caregiver = Caregiver::factory()->create(['user_id' => $user->id]);
    $file = UploadedFile::fake()->image('photo.jpg');

    actingAs($this->admin)
        ->post(route('caregivers.updateProfilePhoto', $caregiver), [
            'profile_photo' => $file,
        ])
        ->assertRedirect(route('caregivers.edit', $caregiver))
        ->assertSessionHas('error');

    $caregiver->user->refresh();

    expect($caregiver->user->profile_photo_path)->toBeNull();
});
