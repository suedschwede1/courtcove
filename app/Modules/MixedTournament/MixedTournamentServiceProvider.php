<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament;

use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Policies\MixedEventPolicy;
use App\Modules\MixedTournament\Preflight\MixedPreflight;
use App\Modules\MixedTournament\Staging\MixedScrub;
use App\Modules\MixedTournament\Support\MixedNavigation;
use App\Modules\TournamentBase\Routing\ModuleRoutes;
use App\Services\TournamentLinks;
use App\Support\Modules\ModuleAdminEntry;
use App\Support\Modules\ModuleDefinition;
use App\Support\Modules\ModuleRegistry;
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

        $this->app->make(ModuleRegistry::class)->register(new ModuleDefinition(
            key: 'mixed',
            name: 'tournament-base::common.nav.mixed',
            icon: 'trophy',
            description: 'tournament-base::common.module_description.mixed',
            route: 'mixed.index',
            routePatterns: ['mixed.*'],
            navigation: MixedNavigation::class,
            adminEntries: [new ModuleAdminEntry('mixed', 'mixed.admin.index', 'admin.mixed', 'booking.admin.nav.mixed', ['mixed.admin.*'])],
            privileges: ['admin.mixed' => 'tournament-base::common.privilege.mixed'],
            url: static fn (): string => app(TournamentLinks::class)->mixedUrl(),
            order: 40,
        ));
    }
}
