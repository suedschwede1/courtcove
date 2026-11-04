<?php

declare(strict_types=1);

namespace App\Modules\Draw;

use Illuminate\Support\ServiceProvider;

class DrawServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'draw');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'draw');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\DrawVerifyCommand::class,
            ]);
        }
    }
}
