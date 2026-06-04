<?php

namespace App\Providers;

use App\Events\BookingAccepted;
use App\Events\BookingCreated;
use App\Events\BookingGroupCreated;
use App\Events\BookingInvitationSent;
use App\Events\BookingReceipt;
use App\Events\BookingReminderTriggered;
use App\Events\GuestAccountSetup;
use App\Listeners\SendBookingAcceptedNotifications;
use App\Listeners\SendBookingCreatedNotifications;
use App\Listeners\SendBookingGroupCreatedNotifications;
use App\Listeners\SendBookingInvitationNotifications;
use App\Listeners\SendBookingReceiptNotification;
use App\Listeners\SendBookingReminderNotifications;
use App\Listeners\SendGuestAccountSetupNotification;
use App\Listeners\UpdateLastLogin;
use App\Models\BookingGroup;
use App\Observers\BookingGroupObserver;
use App\Services\TwilioService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
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
        $this->guardMailInNonProduction();
        $this->guardSmsInNonProduction();

        BookingGroup::observe(BookingGroupObserver::class);

        Event::listen(
            Login::class,
            UpdateLastLogin::class,
        );

        Event::listen(
            BookingCreated::class,
            SendBookingCreatedNotifications::class,
        );

        Event::listen(
            BookingGroupCreated::class,
            SendBookingGroupCreatedNotifications::class,
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

        Event::listen(
            BookingReceipt::class,
            SendBookingReceiptNotification::class,
        );

        Event::listen(
            GuestAccountSetup::class,
            SendGuestAccountSetupNotification::class,
        );
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
