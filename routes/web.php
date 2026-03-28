<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CaregiverController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Custom availability routes (must come before resource to take precedence)
    Route::post('/caregivers/{caregiver}/availability', [AvailabilityController::class, 'storeForCaregiver'])->name('caregivers.availability.store');
    Route::delete('/caregivers/{caregiver}/availability/{availability}', [AvailabilityController::class, 'destroyForCaregiver'])->name('caregivers.availability.destroy');

    Route::middleware('admin')->group(function () {
        Route::get('caregivers/search-suggestions', [CaregiverController::class, 'searchSuggestions'])->name('caregivers.searchSuggestions');
        Route::post('caregivers/{caregiver}/profile-photo', [CaregiverController::class, 'updateProfilePhoto'])->name('caregivers.updateProfilePhoto');
        Route::post('caregivers/{caregiver}/password', [CaregiverController::class, 'resetPassword'])->name('caregivers.resetPassword');
        Route::resource('caregivers', CaregiverController::class)->except(['destroy']);

        Route::get('/caregivers/{caregiver}/availability/manage', [AvailabilityController::class, 'manage'])->name('caregivers.availability.manage');

        Route::resource('availabilities', AvailabilityController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::get('/my-availability', [AvailabilityController::class, 'myAvailability'])->name('availabilities.my');
    Route::put('/my-availability', [AvailabilityController::class, 'updateMyAvailability'])->name('availabilities.updateMy');
    Route::put('/my-availability/availability', [AvailabilityController::class, 'upsertMyAvailability'])->name('availabilities.upsert');
    Route::delete('/my-availability/availability/{availability}', [AvailabilityController::class, 'destroyMyAvailability'])->name('availabilities.destroyMy');
});

require __DIR__.'/settings.php';
