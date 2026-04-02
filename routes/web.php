<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CaregiverController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SuperAdmin\AttributeDefinitionController;
use App\Http\Controllers\SuperAdmin\CertificationTypeController;
use App\Http\Controllers\SuperAdmin\HotelController;
use App\Http\Controllers\SuperAdmin\LocationController;
use App\Http\Controllers\SuperAdmin\SpecialtyTypeController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('availabilities', AvailabilityController::class)->only(['index', 'show', 'update', 'destroy']);

    Route::get('bookings/search-hotels', [BookingController::class, 'searchHotels'])->name('admin.bookings.searchHotels');
    Route::resource('bookings', BookingController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::middleware('admin')->group(function () {
        Route::get('clients/search-suggestions', [ClientController::class, 'searchSuggestions'])->name('clients.searchSuggestions');
        Route::get('clients/{client}/data', [ClientController::class, 'getClientData'])->name('clients.getClientData');
        Route::post('clients/{client}/profile-photo', [ClientController::class, 'updateProfilePhoto'])->name('clients.updateProfilePhoto');
        Route::post('clients/{client}/password', [ClientController::class, 'resetPassword'])->name('clients.resetPassword');
        Route::resource('clients', ClientController::class)->except(['destroy']);

        Route::get('caregivers/search-suggestions', [CaregiverController::class, 'searchSuggestions'])->name('caregivers.searchSuggestions');
        Route::post('caregivers/{caregiver}/profile-photo', [CaregiverController::class, 'updateProfilePhoto'])->name('caregivers.updateProfilePhoto');
        Route::post('caregivers/{caregiver}/password', [CaregiverController::class, 'resetPassword'])->name('caregivers.resetPassword');
        Route::resource('caregivers', CaregiverController::class)->except(['destroy']);
    });

    Route::middleware('super_admin')->group(function () {
        Route::resource('certifications', CertificationTypeController::class)->except(['show', 'create', 'edit'])->name('index', 'certifications.index');
        Route::resource('specialties', SpecialtyTypeController::class)->except(['show', 'create', 'edit'])->name('index', 'specialties.index');
        Route::resource('locations', LocationController::class)->except(['show', 'create', 'edit'])->name('index', 'locations.index');
        Route::resource('attributes', AttributeDefinitionController::class)->except(['show', 'create', 'edit'])->name('index', 'attributes.index');
        Route::resource('hotels', HotelController::class)->except(['show', 'create', 'edit'])->name('index', 'hotels.index');
    });
});

require __DIR__.'/settings.php';
