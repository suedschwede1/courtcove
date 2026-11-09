<?php

declare(strict_types=1);

namespace App\Modules\Tournament;

use App\Models\User;
use App\Modules\Tournament\Preflight\TournamentPreflight;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\Tournament\Staging\TournamentScrub;
use App\Modules\Tournament\Support\ChampionshipNavigation;
use App\Modules\TournamentBase\Routing\ModuleRoutes;
use App\Services\TournamentLinks;
use App\Support\Modules\ModuleAdminEntry;
use App\Support\Modules\ModuleDefinition;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TournamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request, like ZF2's shared service (its per-tid cache must not outlive a request).
        $this->app->scoped(TournamentManager::class);

        $this->app->tag([TournamentScrub::class], 'phase2.scrub');
        $this->app->tag([TournamentPreflight::class], 'phase2.preflight');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'tournament');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'tournament');

        Route::middleware(ModuleRoutes::middleware())->group(fn () => $this->loadRoutesFrom(__DIR__.'/routes.php'));

        // Listed while a championship runs (as before) or for its administrators.
        $this->app->make(ModuleRegistry::class)->register(new ModuleDefinition(
            key: 'championship',
            name: 'tournament-base::common.nav.championship',
            icon: 'medal',
            description: 'tournament-base::common.module_description.championship',
            route: 'tournament.index',
            routePatterns: ['tournament.*'],
            navigation: ChampionshipNavigation::class,
            adminEntries: [new ModuleAdminEntry('championship', 'tournament.admin', 'admin.tournament', 'booking.admin.nav.championship', ['tournament.admin*'])],
            privileges: ['admin.tournament' => 'tournament-base::common.privilege.championship'],
            visible: static fn (?User $user): bool => $user !== null
                && (app(TournamentLinks::class)->currentChampionship() !== null || $user->can('admin.tournament')),
            url: static fn (): string => app(TournamentLinks::class)->championshipUrl(),
            order: 20,
        ));
    }
}
