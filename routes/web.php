<?php

use App\Http\Controllers\AttributeDefinitionController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CaregiverController;
use App\Http\Controllers\CaregiverPayoutController;
use App\Http\Controllers\CertificationTypeController;
use App\Http\Controllers\ChargeBookingController;
use App\Http\Controllers\ChargingController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\SpecialtyTypeController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::post('webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/payments', [ClientPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/setup-intent', [ClientPaymentController::class, 'getSetupIntent'])->name('payments.setupIntent');
    Route::post('/payments/methods', [ClientPaymentController::class, 'storePaymentMethod'])->name('payments.methods.store');
    Route::patch('/payments/methods/{paymentMethod}/default', [ClientPaymentController::class, 'setDefault'])->name('payments.methods.default');
    Route::delete('/payments/methods/{paymentMethod}', [ClientPaymentController::class, 'destroy'])->name('payments.methods.destroy');

    Route::get('/payouts', [CaregiverPayoutController::class, 'index'])->name('payouts.index');
    Route::post('/payouts/stripe/connect', [CaregiverPayoutController::class, 'connect'])->name('payouts.stripe.connect');
    Route::get('/payouts/stripe/onboarding', [CaregiverPayoutController::class, 'onboarding'])->name('payouts.stripe.onboarding');
    Route::get('/payouts/stripe/status', [CaregiverPayoutController::class, 'status'])->name('payouts.stripe.status');
    Route::get('/payouts/stripe/return', [CaregiverPayoutController::class, 'return'])->name('payouts.stripe.return');
    Route::get('/payouts/stripe/refresh', [CaregiverPayoutController::class, 'refresh'])->name('payouts.stripe.refresh');

    Route::resource('availabilities', AvailabilityController::class)->only(['index', 'show', 'update', 'destroy']);

    Route::get('bookings/search-hotels', [BookingController::class, 'searchHotels'])->name('admin.bookings.searchHotels');
    Route::get('bookings/recommended-caregivers', [BookingController::class, 'recommendedCaregivers'])->name('bookings.recommendedCaregivers');
    Route::resource('bookings', BookingController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::middleware('admin')->group(function () {
        Route::get('admin/bookings/charge', [ChargeBookingController::class, 'create'])->name('admin.bookings.charge.create');
        Route::post('admin/bookings/{booking}/charge', [ChargingController::class, 'charge'])->name('admin.bookings.charge');
        Route::get('admin/bookings/{booking}/calculate-total', [ChargingController::class, 'calculateTotal'])->name('admin.bookings.calculateTotal');
        Route::post('bookings/{booking}/notify', [BookingController::class, 'notify'])->name('bookings.notify');

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

require __DIR__ . '/settings.php';