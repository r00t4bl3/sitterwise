<?php

use App\Http\Controllers\Settings\AvailabilityPreferencesController;
use App\Http\Controllers\Settings\CalendarSyncController;
use App\Http\Controllers\Settings\CaregiverLanguagesController;
use App\Http\Controllers\Settings\CaregiverPauseController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\PushTestController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('settings/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('settings/push-test', PushTestController::class)
        ->name('settings.push-test');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
    Route::inertia('settings/push-notifications', 'settings/push-notifications')
        ->name('push-notifications.edit');

    Route::get('settings/caregiver/availability', [AvailabilityPreferencesController::class, 'show'])->name('settings.caregiver.availability');
    Route::put('settings/caregiver/availability', [AvailabilityPreferencesController::class, 'update'])->name('settings.caregiver.availability.update');

    Route::get('settings/caregiver/languages', [CaregiverLanguagesController::class, 'show'])->name('settings.caregiver.languages');
    Route::put('settings/caregiver/languages', [CaregiverLanguagesController::class, 'update'])->name('settings.caregiver.languages.update');

    Route::get('settings/caregiver/pause', [CaregiverPauseController::class, 'show'])->name('settings.caregiver.pause');
    Route::post('settings/caregiver/pause', [CaregiverPauseController::class, 'pause'])->name('settings.caregiver.pause.store');
    Route::post('settings/caregiver/resume', [CaregiverPauseController::class, 'resume'])->name('settings.caregiver.resume');

    Route::get('settings/caregiver/calendar-sync', [CalendarSyncController::class, 'show'])->name('settings.caregiver.calendar-sync');
    Route::post('settings/caregiver/calendar-sync/regenerate', [CalendarSyncController::class, 'regenerate'])->name('settings.caregiver.calendar-sync.regenerate');
});
