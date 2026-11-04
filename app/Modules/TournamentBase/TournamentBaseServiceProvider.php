<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase;

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

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Phase2PreflightCommand::class,
            ]);
        }
    }
}
