<?php

declare(strict_types=1);

namespace App\Modules\Members;

use App\Modules\Members\Models\Member;
use App\Modules\Members\Support\MemberConsentObserver;
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

        // Ends WhatsApp consents on exit, anonymisation and unlink. Registered unconditionally: a schema check
        // here would cost a query per request; the observer skips quietly while the notification tables are missing.
        Member::observe(MemberConsentObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\BackfillPaidOnCommand::class,
                Console\ImportMembersCommand::class,
                Console\ImportNuligaLicenseCommand::class,
            ]);
        }
    }
}
