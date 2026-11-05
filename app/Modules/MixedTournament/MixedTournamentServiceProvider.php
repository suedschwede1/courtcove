<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament;

use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Policies\MixedEventPolicy;
use App\Modules\TournamentBase\Routing\ModuleRoutes;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MixedTournamentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'mixed');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'mixed');

        Gate::policy(MixedEvent::class, MixedEventPolicy::class);

        Route::middleware(ModuleRoutes::middleware())->group(fn () => $this->loadRoutesFrom(__DIR__.'/routes.php'));

        // An expired form token on a module page bounces back with a notice instead of the bare 419 page.
        $this->app->make(ExceptionHandler::class)->renderable(
            fn (HttpExceptionInterface $e, Request $request) => $e->getStatusCode() === 419 && $request->is('mixed-tournament', 'mixed-tournament/*')
                ? back()->with('error', __('mixed::messages.csrf_expired'))
                : null
        );
    }
}
