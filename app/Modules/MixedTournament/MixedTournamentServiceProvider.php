<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament;

use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Policies\MixedEventPolicy;
use App\Modules\TournamentBase\Http\Middleware\RequireModuleLogin;
use App\Modules\TournamentBase\Routing\ModuleRoutes;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MixedTournamentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'mixed');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'mixed');

        // The login redirect must run before route-model binding, otherwise a guest would learn which ids exist.
        $this->app->make(Kernel::class)->prependToMiddlewarePriority(RequireModuleLogin::class);

        Gate::policy(MixedEvent::class, MixedEventPolicy::class);

        Route::middleware(ModuleRoutes::middleware())->group(fn () => $this->loadRoutesFrom(__DIR__.'/routes.php'));
    }
}
