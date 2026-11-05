<?php

declare(strict_types=1);

namespace App\Modules\Pyramid;

use Illuminate\Support\ServiceProvider;

class PyramidServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'pyramid');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'pyramid');
    }
}
