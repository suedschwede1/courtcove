<?php

declare(strict_types=1);

namespace App\Modules\Members;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MembersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('web')->group(function (): void {
            $this->loadRoutesFrom(__DIR__.'/routes.php');
        });

        $this->loadViewsFrom(__DIR__.'/resources/views', 'members');
        Blade::anonymousComponentPath(__DIR__.'/resources/views/components', 'members');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'members');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ImportMembersCommand::class,
                Console\ImportNuligaLicenseCommand::class,
            ]);
        }
    }
}
