<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Scheduling\RunLog;
use App\Models\Option;
use App\Models\User;
use App\Notifications\Registry\BuiltInNotifications;
use App\Notifications\Registry\NotificationRegistry;
use App\Services\Mail\StagingMailGuard;
use App\Services\Notifications\Transport\FakeTransport;
use App\Services\Notifications\WhatsAppConfigScrub;
use App\Services\TournamentLinks;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TournamentLinks::class);
        // Registry of the modules (menu, overview, administration group, rights, sidebar); every module registers
        // itself in the boot() of its own service provider (docs/modules.md).
        $this->app->singleton(ModuleRegistry::class);
        $this->app->singleton(RunLog::class);
        $this->app->singleton(FakeTransport::class);
        // Central registry of the WhatsApp notification types and consent purposes; a row whose type is not
        // registered is skipped as subject_gone.
        $this->app->singleton(NotificationRegistry::class, static function (): NotificationRegistry {
            $registry = new NotificationRegistry;
            BuiltInNotifications::register($registry);

            return $registry;
        });
        // Staging scrub hook: phase2:scrub-staging runs every tagged ConfigScrub after the module scrubs.
        $this->app->tag([WhatsAppConfigScrub::class], 'phase2.config-scrub');
    }

    public function boot(): void
    {
        // Staging mail protection: redirects or stops every outgoing message on a staging database (no effect elsewhere).
        // Note: Mailer uses until(), so an earlier MessageSending listener returning non-null would skip this guard; keep it first.
        Event::listen(MessageSending::class, [StagingMailGuard::class, 'handle']);

        Gate::before(function (User $user, string $ability): ?bool {
            return $user->can($ability) ? true : null;
        });

        View::share('bookingName', rescue(function (): string {
            return Cache::remember('booking.service_name', 300, function (): string {
                $name = trim((string) Option::getValue('service.name', config('booking.name')));

                return $name !== '' ? $name : (string) config('booking.name');
            });
        }, (string) config('booking.name'), report: false));

        View::share('bookingLogoPath', rescue(function (): string {
            return Cache::remember('booking.logo_path', 300, function (): string {
                $path = trim((string) Option::getValue('service.brand.logo_path', config('booking.logo_path')));

                return $path !== '' ? $path : (string) config('booking.logo_path');
            });
        }, (string) config('booking.logo_path'), report: false));

        // Composer instead of View::share so the options and the locale are read
        // at render time (the SetLocale middleware runs after providers boot).
        View::composer(['auth.register', 'auth.login'], function (\Illuminate\View\View $view): void {
            $view->with('registrationContent', rescue(function (): array {
                $locale = app()->getLocale();

                return [
                    'heading' => trim((string) Option::getValue('service.user.registration.heading', __('booking.register.heading'), $locale)) ?: __('booking.register.heading'),
                    'welcome' => trim((string) Option::getValue('service.user.registration.welcome', __('booking.register.welcome'), $locale)) ?: __('booking.register.welcome'),
                    'intro' => trim((string) Option::getValue('service.user.registration.intro', __('booking.register.intro'), $locale)) ?: __('booking.register.intro'),
                    'email_help' => trim((string) Option::getValue('service.user.registration.email_help', __('booking.register.email_help'), $locale)) ?: __('booking.register.email_help'),
                    'privacy' => trim((string) Option::getValue('service.user.registration.privacy', __('booking.register.privacy_text'), $locale)) ?: __('booking.register.privacy_text'),
                    'success' => trim((string) Option::getValue('service.user.registration.success', __('booking.register.success_message'), $locale)) ?: __('booking.register.success_message'),
                ];
            }, [
                'heading' => __('booking.register.heading'),
                'welcome' => __('booking.register.welcome'),
                'intro' => __('booking.register.intro'),
                'email_help' => __('booking.register.email_help'),
                'privacy' => __('booking.register.privacy_text'),
                'success' => __('booking.register.success_message'),
            ], report: false));
        });
    }
}
