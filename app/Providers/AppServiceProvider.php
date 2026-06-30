<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Option;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->can($ability) ? true : null;
        });

        View::share('bookingName', rescue(function (): string {
            return Cache::remember('booking.service_name', 300, function (): string {
                $name = trim((string) Option::getValue('service.name', config('booking.name')));

                return $name !== '' ? $name : (string) config('booking.name');
            });
        }, (string) config('booking.name'), report: false));

        View::share('registrationContent', rescue(function (): array {
            $locale = app()->getLocale();

            return [
                'heading' => trim((string) Option::getValue('service.user.registration.heading', __('booking.register.heading'), $locale)) ?: __('booking.register.heading'),
                'welcome' => trim((string) Option::getValue('service.user.registration.welcome', __('booking.register.welcome'), $locale)) ?: __('booking.register.welcome'),
                'intro' => trim((string) Option::getValue('service.user.registration.intro', __('booking.register.intro'), $locale)) ?: __('booking.register.intro'),
                'privacy' => trim((string) Option::getValue('service.user.registration.privacy', __('booking.register.privacy_text'), $locale)) ?: __('booking.register.privacy_text'),
                'success' => trim((string) Option::getValue('service.user.registration.success', __('booking.register.success_message'), $locale)) ?: __('booking.register.success_message'),
            ];
        }, [
            'heading' => __('booking.register.heading'),
            'welcome' => __('booking.register.welcome'),
            'intro' => __('booking.register.intro'),
            'privacy' => __('booking.register.privacy_text'),
            'success' => __('booking.register.success_message'),
        ], report: false));
    }
}
