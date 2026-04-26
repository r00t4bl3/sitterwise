<?php

namespace App\Providers;

use App\Events\BookingAccepted;
use App\Events\BookingCreated;
use App\Events\BookingInvitationSent;
use App\Events\BookingReminderTriggered;
use App\Listeners\SendBookingAcceptedNotifications;
use App\Listeners\SendBookingCreatedNotifications;
use App\Listeners\SendBookingInvitationNotifications;
use App\Listeners\SendBookingReminderNotifications;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

        Event::listen(
            BookingCreated::class,
            SendBookingCreatedNotifications::class,
        );

        Event::listen(
            BookingAccepted::class,
            SendBookingAcceptedNotifications::class,
        );

        Event::listen(
            BookingInvitationSent::class,
            SendBookingInvitationNotifications::class,
        );

        Event::listen(
            BookingReminderTriggered::class,
            SendBookingReminderNotifications::class,
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
