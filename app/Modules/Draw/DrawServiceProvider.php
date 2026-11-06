<?php

declare(strict_types=1);

namespace App\Modules\Draw;

use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Preflight\DrawPreflight;
use App\Modules\Draw\Services\DrandBeaconService;
use App\Modules\Draw\Staging\DrawScrub;
use App\Modules\TournamentBase\Routing\ModuleRoutes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DrawServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/draw.php', 'draw');
        // Scoped: the chain info is fetched at most once per request (ZF2: per service instance).
        $this->app->scoped(BeaconService::class, DrandBeaconService::class);
        $this->app->tag([DrawPreflight::class], 'phase2.preflight');
        $this->app->tag([DrawScrub::class], 'phase2.scrub');
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
