<?php

use App\Http\Controllers\Admin\CaregiverAvailabilityController;
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

    Route::middleware('admin')->group(function () {
        Route::get('clients/search-suggestions', [ClientController::class, 'searchSuggestions'])->name('clients.searchSuggestions');
        Route::get('clients/{client}/data', [ClientController::class, 'getClientData'])->name('clients.getClientData');
        Route::get('caregivers/search-suggestions', [CaregiverController::class, 'searchSuggestions'])->name('caregivers.searchSuggestions');
        Route::resource('clients', ClientController::class)->except(['destroy']);
        Route::post('clients/{client}/profile-photo', [ClientController::class, 'updateProfilePhoto'])->name('clients.updateProfilePhoto');
        Route::post('clients/{client}/password', [ClientController::class, 'resetPassword'])->name('clients.resetPassword');
        Route::post('caregivers/{caregiver}/profile-photo', [CaregiverController::class, 'updateProfilePhoto'])->name('caregivers.updateProfilePhoto');
        Route::post('caregivers/{caregiver}/password', [CaregiverController::class, 'resetPassword'])->name('caregivers.resetPassword');
        Route::resource('caregivers', CaregiverController::class)->except(['destroy']);

        Route::resource('availabilities', AvailabilityController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::resource('caregivers.availabilities', CaregiverAvailabilityController::class);

        Route::get('/admin/bookings', [BookingController::class, 'index'])->name('admin.bookings.index');
        Route::get('/admin/bookings/search-hotels', [BookingController::class, 'searchHotels'])->name('admin.bookings.searchHotels');
        Route::post('/admin/bookings', [BookingController::class, 'store'])->name('admin.bookings.store');
        Route::put('/admin/bookings/{booking}', [BookingController::class, 'update'])->name('admin.bookings.update');
        Route::delete('/admin/bookings/{booking}', [BookingController::class, 'destroy'])->name('admin.bookings.destroy');
    });

    Route::middleware('super_admin')->group(function () {
        Route::resource('admin/certifications', CertificationTypeController::class)->except(['show', 'create', 'edit'])->name('index', 'admin.certifications.index');
        Route::resource('admin/specialties', SpecialtyTypeController::class)->except(['show', 'create', 'edit'])->name('index', 'admin.specialties.index');
        Route::resource('admin/locations', LocationController::class)->except(['show', 'create', 'edit'])->name('index', 'admin.locations.index');
        Route::resource('admin/attributes', AttributeDefinitionController::class)->except(['show', 'create', 'edit'])->name('index', 'admin.attributes.index');
        Route::resource('admin/hotels', HotelController::class)->except(['show', 'create', 'edit'])->name('index', 'admin.hotels.index');
    });
});

require __DIR__.'/settings.php';
