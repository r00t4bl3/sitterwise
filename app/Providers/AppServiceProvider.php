<?php

namespace App\Providers;

use App\Listeners\UpdateLastLogin;
use App\Models\BookingGroup;
use App\Observers\BookingGroupObserver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->guardMailInNonProduction();
        $this->guardSmsInNonProduction();
        $this->configureRateLimiting();

        BookingGroup::observe(BookingGroupObserver::class);

        Event::listen(
            Login::class,
            UpdateLastLogin::class,
        );

        // All booking listeners are auto-discovered.
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function guardMailInNonProduction(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $deliverableDrivers = ['sendgrid', 'ses', 'postmark', 'mailgun', 'resend'];

        if (in_array(config('mail.default'), $deliverableDrivers)) {
            config(['mail.default' => 'log']);
        }
    }

    protected function guardSmsInNonProduction(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $this->app->extend(TwilioService::class, function () {
            return new class extends TwilioService
            {
                public function send(string $to, string $message, array $options = []): array
                {
                    logger("SMS dry-run to {$to}: {$message}");

                    return $this->sendDryRun($to, $message);
                }
            };
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(9)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('register', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return [
                Limit::perMinute(3)->by($request->ip())
                    ->response(fn () => back()->withErrors(['email' => 'Too many registration attempts. Please wait a minute.'])),
                Limit::perHour(1)->by($request->input('email', $request->ip())),
            ];
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return [
                Limit::perMinute(3)->by($request->ip())
                    ->response(fn () => back()->withErrors(['email' => 'Too many password reset requests. Please wait a minute.'])),
                Limit::perHour(1)->by($request->input('email', $request->ip())),
            ];
        });

        RateLimiter::for('reset-password', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('caregiver-otp-send', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(3)->by($request->ip())
                ->response(fn () => back()->withErrors(['rate_limit' => 'Too many verification code requests. Please wait a minute.']));
        });

        RateLimiter::for('caregiver-otp-verify', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(5)->by($request->ip())
                ->response(fn () => back()->withErrors(['rate_limit' => 'Too many verification attempts. Please wait a minute.']));
        });

        RateLimiter::for('caregiver-submit', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return [
                Limit::perMinute(2)->by($request->ip())
                    ->response(fn () => back()->withErrors(['rate_limit' => 'Too many submission attempts. Please wait a minute.'])),
                Limit::perHour(1)->by($request->session()->get('verified_email', $request->ip())),
            ];
        });

        RateLimiter::for('caregiver-save-progress', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(20)->by($request->session()->get('verified_email', $request->ip()));
        });

        RateLimiter::for('caregiver-replace-reference', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(3)->by($request->ip())
                ->response(fn () => back()->withErrors(['rate_limit' => 'Too many requests. Please wait a minute.']));
        });

        RateLimiter::for('reference-submit', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(5)->by($request->ip())
                ->response(fn () => back()->withErrors(['rate_limit' => 'Too many requests. Please wait a minute.']));
        });

        RateLimiter::for('guest-booking', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(5)->by($request->ip())
                ->response(fn () => back()->withErrors(['rate_limit' => 'Too many requests. Please wait a minute.']));
        });
    }
}
