<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament;

use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Policies\MixedEventPolicy;
use App\Modules\MixedTournament\Preflight\MixedPreflight;
use App\Modules\MixedTournament\Staging\MixedScrub;
use App\Modules\TournamentBase\Routing\ModuleRoutes;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MixedTournamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([MixedScrub::class], 'phase2.scrub');
        $this->app->tag([MixedPreflight::class], 'phase2.preflight');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'mixed');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'mixed');

        Gate::policy(MixedEvent::class, MixedEventPolicy::class);

        Route::middleware(ModuleRoutes::middleware())->group(fn () => $this->loadRoutesFrom(__DIR__.'/routes.php'));
    }
}
