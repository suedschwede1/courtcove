<?php

use App\Console\Scheduling\ScheduleDefinition;
use App\Http\Middleware\EnsureAdminMode;
use App\Http\Middleware\RestrictApiByIp;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StripFourByteCharacters;
use App\Http\Middleware\TrackAdminModeRequest;
use App\Modules\TournamentBase\Support\CsrfExpiredRedirect;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // admin_mode stays unencrypted: it only takes effect for accounts that hold a privilege (App\Support\AdminMode).
        $middleware->encryptCookies(except: ['locale', 'admin_mode']);
        $middleware->prepend(TrackAdminModeRequest::class);
        // API path only for the addresses in API_ALLOWED_IPS (everything else is untouched).
        $middleware->prepend(RestrictApiByIp::class);
        $middleware->alias(['admin.mode' => EnsureAdminMode::class]);
        $middleware->web(append: [SetLocale::class, StripFourByteCharacters::class]);
        // Provider webhooks authenticate by signature (VerifyWebhookSignature), not by session.
        $middleware->validateCsrfTokens(except: ['webhooks/whatsapp/*']);
        $middleware->api(append: [StripFourByteCharacters::class]);
    })
    // Scheduled tasks: see App\Console\Scheduling\ScheduleDefinition (server cron runs `php artisan schedule:run` every minute).
    ->withSchedule(fn (Schedule $schedule) => (new ScheduleDefinition)->define($schedule))
    ->withExceptions(fn (Exceptions $exceptions) => CsrfExpiredRedirect::register($exceptions))->create();
