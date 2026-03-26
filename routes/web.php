<?php

use App\Http\Controllers\CaregiverController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('caregivers')->name('caregivers.')->group(function () {
        Route::get('/', [CaregiverController::class, 'index'])->name('index');
        Route::middleware('admin')->group(function () {
            Route::get('/create', [CaregiverController::class, 'create'])->name('create');
            Route::post('/', [CaregiverController::class, 'store'])->name('store');
            Route::get('/search-suggestions', [CaregiverController::class, 'searchSuggestions'])->name('searchSuggestions');
            Route::get('/{caregiver}/edit', [CaregiverController::class, 'edit'])->name('edit');
            Route::patch('/{caregiver}', [CaregiverController::class, 'update'])->name('update');
            Route::post('/{caregiver}/profile-photo', [CaregiverController::class, 'updateProfilePhoto'])->name('updateProfilePhoto');
            Route::post('/{caregiver}/password', [CaregiverController::class, 'resetPassword'])->name('resetPassword');
        });
        Route::get('/{caregiver}', [CaregiverController::class, 'show'])->name('show');
    });
});

require __DIR__.'/settings.php';
