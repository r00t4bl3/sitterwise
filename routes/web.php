<?php

use App\Http\Controllers\Admin\AttributeDefinitionController;
use App\Http\Controllers\Admin\CertificationTypeController;
use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\SpecialtyTypeController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CaregiverController;
use App\Http\Controllers\ClientController;
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
        Route::get('clients/search-suggestions', [ClientController::class, 'searchSuggestions'])->name('clients.searchSuggestions');
        Route::get('caregivers/search-suggestions', [CaregiverController::class, 'searchSuggestions'])->name('caregivers.searchSuggestions');
        Route::resource('clients', ClientController::class)->except(['destroy']);
        Route::post('clients/{client}/profile-photo', [ClientController::class, 'updateProfilePhoto'])->name('clients.updateProfilePhoto');
        Route::post('clients/{client}/password', [ClientController::class, 'resetPassword'])->name('clients.resetPassword');
        Route::post('caregivers/{caregiver}/profile-photo', [CaregiverController::class, 'updateProfilePhoto'])->name('caregivers.updateProfilePhoto');
        Route::post('caregivers/{caregiver}/password', [CaregiverController::class, 'resetPassword'])->name('caregivers.resetPassword');
        Route::resource('caregivers', CaregiverController::class)->except(['destroy']);

        Route::get('/availabilities/{caregiver}/show', [AvailabilityController::class, 'manage'])->name('availabilities.show');

        Route::resource('availabilities', AvailabilityController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('super_admin')->group(function () {
        Route::resource('admin/certifications', CertificationTypeController::class)->except(['show', 'create', 'edit'])->name('index', 'admin.certifications.index');
        Route::resource('admin/specialties', SpecialtyTypeController::class)->except(['show', 'create', 'edit'])->name('index', 'admin.specialties.index');
        Route::resource('admin/locations', LocationController::class)->except(['show', 'create', 'edit'])->name('index', 'admin.locations.index');
        Route::resource('admin/attributes', AttributeDefinitionController::class)->except(['show', 'create', 'edit'])->name('index', 'admin.attributes.index');
        Route::resource('admin/hotels', HotelController::class)->except(['show', 'create', 'edit'])->name('index', 'admin.hotels.index');
    });

    Route::get('/my-availability', [AvailabilityController::class, 'myAvailability'])->name('availabilities.my');
    Route::put('/my-availability', [AvailabilityController::class, 'updateMyAvailability'])->name('availabilities.updateMy');
    Route::put('/my-availability/availability', [AvailabilityController::class, 'upsertMyAvailability'])->name('availabilities.upsert');
    Route::delete('/my-availability/availability/{availability}', [AvailabilityController::class, 'destroyMyAvailability'])->name('availabilities.destroyMy');
});

require __DIR__.'/settings.php';
