<?php

declare(strict_types=1);

namespace App\Modules\Pyramid;

use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Policies\PyramidLadderPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PyramidServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'pyramid');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'pyramid');

        Gate::policy(PyramidLadder::class, PyramidLadderPolicy::class);
    }
}
