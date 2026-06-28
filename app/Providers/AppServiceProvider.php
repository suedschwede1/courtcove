<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Option;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->can($ability) ? true : null;
        });

        // The configurable club name (admin option `service.name`, falling back to
        // config). Shared with every view so child views can use it in @section('title')
        // and the like — not just the layout. Falls back to config if the DB is
        // unavailable (e.g. console/migrations).
        View::share('bookingName', rescue(function (): string {
            $name = trim((string) Option::getValue('service.name', config('booking.name')));

            return $name !== '' ? $name : (string) config('booking.name');
        }, (string) config('booking.name'), report: false));
    }
}
