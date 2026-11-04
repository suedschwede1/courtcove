<?php

declare(strict_types=1);

namespace App\Modules\Draw;

use App\Modules\Draw\Preflight\DrawPreflight;
use App\Modules\TournamentBase\Routing\ModuleRoutes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DrawServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([DrawPreflight::class], 'phase2.preflight');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'draw');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'draw');

        Route::middleware(ModuleRoutes::middleware())
            ->group(fn () => $this->loadRoutesFrom(__DIR__.'/routes.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\DrawVerifyCommand::class,
            ]);
        }
    }
}
