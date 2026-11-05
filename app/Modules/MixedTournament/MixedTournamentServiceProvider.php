<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament;

use App\Modules\TournamentBase\Routing\ModuleRoutes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MixedTournamentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'mixed');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'mixed');

        Route::middleware(ModuleRoutes::middleware())->group(fn () => $this->loadRoutesFrom(__DIR__.'/routes.php'));
    }
}
