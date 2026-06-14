<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttributeDefinitionController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingReviewController;
use App\Http\Controllers\BroadcastSmsController;
use App\Http\Controllers\CaregiverApplicationController;
use App\Http\Controllers\CaregiverController;
use App\Http\Controllers\CaregiverInterviewTalkingPointController;
use App\Http\Controllers\CaregiverPayoutController;
use App\Http\Controllers\CertificationTypeController;
use App\Http\Controllers\ChargeBookingController;
use App\Http\Controllers\ChargingController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuestBookingController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\InterviewTalkingPointController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\PricingRuleController;
use App\Http\Controllers\QuickLinkController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SpecialtyTypeController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TransactionController;
use App\Http\Middleware\VerifyEmail;
use Illuminate\Support\Facades\Route;

// Public routes
Route::redirect('/', '/login')->name('home');

// Caregiver public profile
Route::get('/bio/{slug}', [CaregiverController::class, 'publicBio'])->name('caregivers.bio');

// Stripe webhook endpoint
Route::post('webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');

// Twilio status callback webhook
Route::post('webhooks/twilio/status', [BroadcastSmsController::class, 'statusCallback'])->name('webhooks.twilio.status');

Route::post('webhooks/twilio/inbound', [BroadcastSmsController::class, 'inboundSms'])->name('webhooks.twilio.inbound');

// Guest booking routes (public, no auth required)
Route::get('/book', [GuestBookingController::class, 'create'])->name('guest.bookings.create');
Route::post('/book', [GuestBookingController::class, 'store'])->name('guest.bookings.store')->middleware('throttle:guest-booking');
Route::get('/book/payment/{token}', [GuestBookingController::class, 'payment'])->name('guest.bookings.payment');
Route::get('/book/payment/{token}/setup-intent', [GuestBookingController::class, 'getSetupIntent'])->name('guest.bookings.setupIntent');
Route::post('/book/payment/{token}/status', [GuestBookingController::class, 'checkPaymentStatus'])->name('guest.bookings.status')->middleware('throttle:guest-booking');
Route::post('/book/payment/{token}/verify', [GuestBookingController::class, 'verifyPayment'])->name('guest.bookings.verify')->middleware('throttle:guest-booking');
Route::get('/book/confirmation/{booking}', [GuestBookingController::class, 'confirmation'])->name('guest.bookings.confirmation');

// Guest review routes (signed URL from email for non-logged-in clients) - outside auth group
Route::middleware('signed')->group(function () {
    Route::get('review/{booking}', [BookingReviewController::class, 'createFromLink'])->name('review.create');
    Route::post('review/{booking}', [BookingReviewController::class, 'storeFromLink'])->name('review.store');
});

// Caregiver application routes (public, no auth required)
Route::get('/caregiver/apply/verify-email', [CaregiverApplicationController::class, 'showVerifyEmail'])->name('caregiver.apply.verify');
Route::post('/caregiver/apply/send-otp', [CaregiverApplicationController::class, 'sendOtp'])->name('caregiver.apply.send-otp')->middleware('throttle:caregiver-otp-send');
Route::post('/caregiver/apply/verify-otp', [CaregiverApplicationController::class, 'verifyOtp'])->name('caregiver.apply.verify-otp')->middleware('throttle:caregiver-otp-verify');

Route::middleware(VerifyEmail::class)->group(function () {
    Route::get('/caregiver/apply', [CaregiverApplicationController::class, 'showWizard'])->name('caregiver.apply');
    Route::post('/caregiver/apply/save-progress', [CaregiverApplicationController::class, 'saveProgress'])->name('caregiver.apply.save-progress')->middleware('throttle:caregiver-save-progress');
    Route::post('/caregiver/apply/submit', [CaregiverApplicationController::class, 'submit'])->name('caregiver.apply.submit')->middleware('throttle:caregiver-submit');
});

Route::get('/caregiver/apply/thank-you', [CaregiverApplicationController::class, 'thankYou'])->name('caregiver.apply.thank-you');
Route::get('/caregiver/apply/status/{token}', [CaregiverApplicationController::class, 'showStatus'])->name('caregiver.apply.status');
Route::post('/caregiver/apply/status/{token}/replace-reference/{referenceRequest}', [CaregiverApplicationController::class, 'replaceReference'])->name('caregiver.apply.replace-reference')->middleware('throttle:caregiver-replace-reference');

// Reference portal routes (public, no auth — references receive tokenized links via email)

Route::get('/references/{token}', [ReferenceController::class, 'show'])->name('references.show');
Route::post('/references/{token}', [ReferenceController::class, 'store'])->name('references.store')->middleware('throttle:reference-submit');

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/search', [SearchController::class, 'index'])->name('search');

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
    // Route::get('/bookings/available', [CaregiverController::class, 'showBookings'])->name('caregiver.bookings.index');
    // Route::get('/bookings/available/{booking}', [CaregiverController::class, 'showBooking'])->name('caregiver.bookings.show');

    // Route::get('bookings/search-hotels', [BookingController::class, 'searchHotels'])->name('bookings.searchHotels');
    Route::post('bookings/{booking}/reserve', [BookingController::class, 'reserve'])->name('bookings.reserve');
    Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::post('bookings/{booking}/release', [BookingController::class, 'release'])->name('bookings.release');
    Route::get('bookings/recommended-caregivers', [BookingController::class, 'recommendedCaregivers'])->name('bookings.recommendedCaregivers');
    Route::get('bookings/export', [BookingController::class, 'export'])->name('bookings.export')->middleware('admin');
    Route::resource('bookings', BookingController::class)->only(['index', 'create', 'show', 'store', 'update', 'destroy']);
    Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('jobs/{booking}', [JobController::class, 'show'])->name('jobs.show');
    Route::post('jobs/{booking}/checkout', [JobController::class, 'checkout'])->name('jobs.checkout');
    Route::post('jobs/{booking}/rate', [JobController::class, 'rate'])->name('jobs.rate');
    Route::post('assignments/{assignment}/back-out', [AssignmentController::class, 'backOut'])->name('assignments.back-out');

    Route::middleware('client')->group(function () {
        Route::get('reviews/{booking}', [BookingReviewController::class, 'create'])->name('reviews.create');
        Route::post('reviews/{booking}', [BookingReviewController::class, 'store'])->name('reviews.store');
    });

    Route::get('/milestones', [MilestoneController::class, 'index'])->name('milestones')->middleware('caregiver');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::middleware('admin')->group(function () {
        // Route::get('admin/bookings/charge', [ChargeBookingController::class, 'create'])->name('admin.bookings.charge.create');
        // Route::post('admin/bookings/{booking}/charge', [ChargingController::class, 'charge'])->name('admin.bookings.charge');
        // Route::get('admin/bookings/{booking}/calculate-total', [ChargingController::class, 'calculateTotal'])->name('admin.bookings.calculateTotal');
        Route::post('bookings/{booking}/process-payment', [BookingController::class, 'processPayment'])->name('bookings.processPayment');
        Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('bookings/{booking}/replace-caregiver', [BookingController::class, 'replaceCaregiver'])->name('bookings.replace-caregiver');
        Route::post('bookings/{booking}/notify', [BookingController::class, 'notify'])->name('bookings.notify');
        Route::post('bookings/groups/{bookingGroup}/split', [BookingController::class, 'splitGroup'])->name('bookings.groups.split');
        Route::get('clients/search-suggestions', [ClientController::class, 'searchSuggestions'])->name('clients.searchSuggestions');
        Route::get('clients/{client}/data', [ClientController::class, 'getClientData'])->name('clients.getClientData');
        Route::post('clients/{client}/profile-photo', [ClientController::class, 'updateProfilePhoto'])->name('clients.updateProfilePhoto');
        Route::post('clients/{client}/password', [ClientController::class, 'resetPassword'])->name('clients.resetPassword');
        Route::post('clients/{client}/payment-method', [ClientController::class, 'storePaymentMethod'])->name('clients.paymentMethod.store');
        Route::patch('clients/{client}/payment-method/{paymentMethod}/default', [ClientController::class, 'setDefaultPaymentMethod'])->name('clients.paymentMethod.default');
        Route::delete('clients/{client}/payment-method/{paymentMethod}', [ClientController::class, 'deletePaymentMethod'])->name('clients.paymentMethod.destroy');
        Route::get('clients/{client}/bookings', [ClientController::class, 'bookingHistory'])->name('clients.bookingHistory');
        Route::resource('clients', ClientController::class)->except(['destroy']);

        Route::get('caregivers/{caregiver}/jobs', [CaregiverController::class, 'jobHistory'])->name('caregivers.jobHistory');
        Route::get('caregivers/search-suggestions', [CaregiverController::class, 'searchSuggestions'])->name('caregivers.searchSuggestions');
        Route::post('caregivers/{caregiver}/profile-photo', [CaregiverController::class, 'updateProfilePhoto'])->name('caregivers.updateProfilePhoto');
        Route::post('caregivers/{caregiver}/password', [CaregiverController::class, 'resetPassword'])->name('caregivers.resetPassword');
        Route::put('caregivers/{caregiver}/admin-rating', [CaregiverController::class, 'updateAdminRating'])->name('caregivers.updateAdminRating');
        Route::put('caregivers/{caregiver}/reliability-override', [CaregiverController::class, 'updateReliabilityOverride'])->name('caregivers.updateReliabilityOverride');
        Route::post('caregivers/{caregiver}/resume', [CaregiverController::class, 'resumeCaregiver'])->name('caregivers.resume');
        Route::resource('caregivers', CaregiverController::class)->except(['destroy']);

        // Caregiver Applications & References management
        Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
        Route::get('applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
        Route::post('applications/{application}/references/{referenceRequest}/resend', [ApplicationController::class, 'resendReference'])->name('applications.references.resend');
        Route::patch('applications/{application}/references/{referenceRequest}', [ApplicationController::class, 'updateReference'])->name('applications.references.update');
        Route::post('applications/{application}/approve', [ApplicationController::class, 'approve'])->name('applications.approve');
        Route::post('applications/{application}/schedule-interview', [ApplicationController::class, 'scheduleInterview'])->name('applications.schedule-interview');
        Route::post('applications/{application}/background-check', [ApplicationController::class, 'startBackgroundCheck'])->name('applications.background-check');
        Route::post('applications/{application}/hire', [ApplicationController::class, 'hire'])->name('applications.hire');
        Route::post('applications/{application}/complete-onboarding', [ApplicationController::class, 'completeOnboarding'])->name('applications.complete-onboarding');
        Route::post('applications/{application}/checklist/{checklistItem}/toggle', [ApplicationController::class, 'toggleChecklistItem'])->name('applications.checklist.toggle');
        Route::post('applications/{application}/certifications/{certType}/verify', [ApplicationController::class, 'toggleCertificationVerification'])->name('applications.certifications.verify');
        Route::post('applications/{application}/decline', [ApplicationController::class, 'decline'])->name('applications.decline');

        // Assignment management (admin actions)
        Route::post('assignments/{assignment}/excuse', [AssignmentController::class, 'excuse'])->name('assignments.excuse');
        Route::post('assignments/{assignment}/no-show', [AssignmentController::class, 'logNoShow'])->name('assignments.no-show');
        Route::post('assignments/{assignment}/late-arrival', [AssignmentController::class, 'logLateArrival'])->name('assignments.late-arrival');

        // Interview evaluation
        Route::get('applications/{application}/interview', [InterviewController::class, 'create'])->name('applications.interview');
        Route::post('applications/{application}/interview', [InterviewController::class, 'store'])->name('applications.interview.store');

        // Interview talking points (per-interview)
        Route::get('applications/{application}/interview/talking-points', [CaregiverInterviewTalkingPointController::class, 'index'])->name('applications.interview.talking-points');
        Route::post('applications/{application}/interview/talking-points', [CaregiverInterviewTalkingPointController::class, 'store'])->name('applications.interview.talking-points.store');
        Route::patch('applications/{application}/interview/talking-points/{point}', [CaregiverInterviewTalkingPointController::class, 'toggle'])->name('applications.interview.talking-points.toggle');
        Route::put('applications/{application}/interview/talking-points/{point}', [CaregiverInterviewTalkingPointController::class, 'update'])->name('applications.interview.talking-points.update');
        Route::delete('applications/{application}/interview/talking-points/{point}', [CaregiverInterviewTalkingPointController::class, 'destroy'])->name('applications.interview.talking-points.destroy');
        Route::post('applications/{application}/interview/talking-points/reorder', [CaregiverInterviewTalkingPointController::class, 'reorder'])->name('applications.interview.talking-points.reorder');
    });

    Route::middleware('super_admin')->group(function () {
        Route::get('broadcast-sms', [BroadcastSmsController::class, 'index'])->name('broadcast-sms.index');
        Route::post('broadcast-sms', [BroadcastSmsController::class, 'store'])->name('broadcast-sms.store');

        // Talking points master template CRUD
        Route::get('talking-points', [InterviewTalkingPointController::class, 'index'])->name('talking-points.index');
        Route::post('talking-points', [InterviewTalkingPointController::class, 'store'])->name('talking-points.store');
        Route::put('talking-points/{talkingPoint}', [InterviewTalkingPointController::class, 'update'])->name('talking-points.update');
        Route::delete('talking-points/{talkingPoint}', [InterviewTalkingPointController::class, 'destroy'])->name('talking-points.destroy');
        Route::post('talking-points/reorder', [InterviewTalkingPointController::class, 'reorder'])->name('talking-points.reorder');
    });

    // Route::middleware('super_admin')->group(function () {
    //     Route::resource('certifications', CertificationTypeController::class)->except(['show', 'create', 'edit'])->name('index', 'certifications.index');
    //     Route::resource('specialties', SpecialtyTypeController::class)->except(['show', 'create', 'edit'])->name('index', 'specialties.index');
    //     Route::resource('locations', LocationController::class)->except(['show', 'create', 'edit'])->name('index', 'locations.index');
    //     Route::resource('attributes', AttributeDefinitionController::class)->except(['show', 'create', 'edit'])->name('index', 'attributes.index');
    //     Route::resource('hotels', HotelController::class)->except(['show', 'create', 'edit'])->name('index', 'hotels.index');
    // });

    Route::resource('certifications', CertificationTypeController::class)->except(['show', 'create', 'edit'])->name('index', 'certifications.index');
    Route::resource('specialties', SpecialtyTypeController::class)->except(['show', 'create', 'edit'])->name('index', 'specialties.index');
    Route::resource('locations', LocationController::class)->except(['show', 'create', 'edit'])->name('index', 'locations.index');
    Route::resource('attributes', AttributeDefinitionController::class)->except(['show', 'create', 'edit'])->name('index', 'attributes.index');

    Route::get('hotels/search', [HotelController::class, 'search'])->name('hotels.search');
    Route::resource('hotels', HotelController::class)->except(['show', 'create', 'edit'])->name('index', 'hotels.index');
    Route::resource('pricing-rules', PricingRuleController::class)->except(['show', 'create', 'edit'])->name('index', 'pricing-rules.index');
    Route::get('quick-links/search', [QuickLinkController::class, 'search'])->name('quick-links.search');
    Route::resource('quick-links', QuickLinkController::class)->except(['show', 'create', 'edit']);

});

require __DIR__.'/settings.php';
