<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase;

use App\Modules\TournamentBase\Http\Middleware\RequireModuleLogin;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Random\Engine\Secure;
use Random\Randomizer;

class TournamentBaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/tournament-base.php', 'tournament-base');

        // bind (not singleton): every consumer gets a fresh randomizer; tests swap in a seeded one
        $this->app->bind(Randomizer::class, fn () => new Randomizer(new Secure));
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'tournament-base');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'tournament-base');
        Blade::anonymousComponentPath(__DIR__.'/resources/views/components', 'tournament-base');

        $this->app->make(Router::class)->aliasMiddleware('tournament-base.login', RequireModuleLogin::class);

        // After the session/auth middlewares (it needs the user) but before route-model binding, so a guest
        // is sent to the login before an unknown id can be told apart from a known one.
        $this->app->make(Kernel::class)->addToMiddlewarePriorityBefore(SubstituteBindings::class, RequireModuleLogin::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Phase2PreflightCommand::class,
                Console\Phase2ScrubStagingCommand::class,
            ]);
        }
    }
}
