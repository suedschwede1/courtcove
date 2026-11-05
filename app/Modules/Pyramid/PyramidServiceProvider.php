<?php

declare(strict_types=1);

namespace App\Modules\Pyramid;

use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Policies\PyramidLadderPolicy;
use App\Modules\Pyramid\Preflight\PyramidPreflight;
use App\Modules\Pyramid\Staging\PyramidScrub;
use App\Modules\TournamentBase\Routing\ModuleRoutes;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PyramidServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([PyramidScrub::class], 'phase2.scrub');
        $this->app->tag([PyramidPreflight::class], 'phase2.preflight');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'pyramid');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'pyramid');

        Gate::policy(PyramidLadder::class, PyramidLadderPolicy::class);

        Route::middleware(ModuleRoutes::middleware())->group(fn () => $this->loadRoutesFrom(__DIR__.'/routes.php'));
    }
}
