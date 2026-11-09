<?php

declare(strict_types=1);

namespace App\Modules\Draw;

use App\Models\User;
use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Preflight\DrawPreflight;
use App\Modules\Draw\Services\DrandBeaconService;
use App\Modules\Draw\Staging\DrawScrub;
use App\Modules\Draw\Support\DrawNavigation;
use App\Modules\TournamentBase\Routing\ModuleRoutes;
use App\Support\Modules\ModuleAdminEntry;
use App\Support\Modules\ModuleDefinition;
use App\Support\Modules\ModuleRegistry;
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

        // The draws have no menu entry of their own: they sit in the championship's sidebar group and show up in the
        // overview for their administrators (the record pages are linked from the championship).
        $this->app->make(ModuleRegistry::class)->register(new ModuleDefinition(
            key: 'draw',
            name: 'tournament-base::common.nav.draw',
            icon: 'ball',
            description: 'tournament-base::common.module_description.draw',
            route: 'draw.index',
            routePatterns: ['draw.*'],
            navigation: DrawNavigation::class,
            sidebarGroup: 'championship',
            adminEntries: [new ModuleAdminEntry('draws', 'draw.index', 'admin.draw', 'booking.admin.nav.draws', ['draw.*'])],
            privileges: ['admin.draw' => 'tournament-base::common.privilege.draw'],
            visible: static fn (?User $user): bool => $user?->can('admin.draw') === true,
            inMenu: false,
            order: 30,
        ));

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\DrawVerifyCommand::class,
            ]);
        }
    }
}
