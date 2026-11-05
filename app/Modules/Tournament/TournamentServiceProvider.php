<?php

declare(strict_types=1);

namespace App\Modules\Tournament;

use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\TournamentBase\Routing\ModuleRoutes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TournamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request, like ZF2's shared service (its per-tid cache must not outlive a request).
        $this->app->scoped(TournamentManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'tournament');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'tournament');

        Route::middleware(ModuleRoutes::middleware())->group(fn () => $this->loadRoutesFrom(__DIR__.'/routes.php'));
    }
}
