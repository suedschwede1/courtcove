<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\ApiTokenController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DatabaseController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\LoginHistoryController;
use App\Http\Controllers\Admin\OptionController;
use App\Http\Controllers\Admin\SchedulerController;
use App\Http\Controllers\Admin\SquareController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\TestMailController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WhatsAppController;
use App\Http\Controllers\AdminModeController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LoginVerificationController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PlayerExchangeController;
use App\Http\Controllers\UiGalleryController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/login', [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

Route::get('/register', [RegisterController::class, 'showForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,1');
Route::get('/login/verify', [LoginVerificationController::class, 'show'])->name('login.verify.show');
Route::post('/login/verify', [LoginVerificationController::class, 'verify'])->name('login.verify.submit');
Route::post('/login/verify/resend', [LoginVerificationController::class, 'resend'])->name('login.verify.resend');

Route::get('/password/forgot', [ForgotPasswordController::class, 'showForm'])->name('password.forgot');
Route::post('/password/forgot', [ForgotPasswordController::class, 'sendResetLink'])
    ->middleware('throttle:5,1')->name('password.email');
Route::get('/password/reset/{token}', [ResetPasswordController::class, 'showForm'])
    ->middleware('throttle:20,1')->name('password.reset');
Route::post('/password/reset', [ResetPasswordController::class, 'reset'])
    ->middleware('throttle:5,1')->name('password.update');

Route::get('/_ui', UiGalleryController::class)->name('ui.gallery');
Route::get('/lang/{locale}', [LocaleController::class, 'switch'])->name('lang.switch');

// Admin mode switch (header switch and the "Switch on admin mode" gate page). POST with CSRF; the
// controller checks the RAW privilege, because without admin mode no privilege is in effect.
Route::post('/admin-mode/{state}', [AdminModeController::class, 'set'])
    ->whereIn('state', ['on', 'off'])
    ->middleware('auth')
    ->name('admin-mode.set');

// Player exchange switch in the menus (same opt-in/out as the account checkbox). POST with CSRF.
Route::post('/player-exchange/{state}', [PlayerExchangeController::class, 'set'])
    ->whereIn('state', ['on', 'off'])
    ->middleware('auth')
    ->name('player-exchange.set');

// Provider webhooks (WhatsApp status and inbound messages): the only routes without session and CSRF
// (also listed in bootstrap/app.php); the signature middleware is their authentication.
Route::prefix('webhooks/whatsapp')
    ->name('webhooks.whatsapp.')
    // throttle first, so rejected requests cannot flood the log
    ->middleware(['throttle:300,1', VerifyWebhookSignature::class])
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class, SetLocale::class])
    ->group(function (): void {
        Route::post('/status', [WhatsAppWebhookController::class, 'status'])->name('status');
        Route::post('/inbound', [WhatsAppWebhookController::class, 'inbound'])->name('inbound');
    });

Route::get('/', static fn () => redirect()->route('calendar.index'));
Route::get('/booking', static fn () => redirect()->route('calendar.index'));
Route::get('/bookings', static fn () => redirect()->route('calendar.index'));
Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

// Legacy ZF2 URLs (bookmarks, mails, links on the club site). Temporary (302) redirects,
// deliberately NOT 301: a rollback to ZF2 must not be poisoned by browser-cached
// permanent redirects. No auth required. /user/login|logout|password[-reset] are NOT here:
// public/.htaccess routes them to the ZF2 tournament app (see zf2-bridge.php).
Route::get('/user/bookings', static fn () => redirect()->route('account.bookings'));
Route::get('/user/settings', static fn () => redirect()->route('account.edit'));
Route::get('/user/registration', static fn () => redirect()->route('register'));
Route::get('/user/activation/{any?}', static fn () => redirect()->route('login'))->where('any', '.*');
Route::get('/square/{any?}', static fn () => redirect()->route('calendar.index'))->where('any', '.*');
Route::get('/backend/{any?}', static fn () => redirect()->route('admin.dashboard'))->where('any', '.*');

// Signed one-click unsubscribe link from player exchange e-mails: no login, no expiry.
Route::get('/player-exchange/unsubscribe/{user}', [PlayerExchangeController::class, 'unsubscribe'])
    ->middleware('signed')
    ->name('player-exchange.unsubscribe');

Route::middleware('auth')->group(function (): void {
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::get('/bookings/players', [BookingController::class, 'players'])->name('bookings.players');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}/edit', [BookingController::class, 'edit'])->name('bookings.edit');
    Route::put('/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');

    Route::post('/bookings/{booking}/join', [PlayerExchangeController::class, 'join'])->name('player-exchange.join');
    Route::delete('/bookings/{booking}/join', [PlayerExchangeController::class, 'leave'])->name('player-exchange.leave');
    Route::post('/bookings/{booking}/search/end', [PlayerExchangeController::class, 'end'])->name('player-exchange.end');

    Route::get('/my-bookings', [AccountController::class, 'bookings'])->name('account.bookings');
    Route::get('/my-account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/my-account', [AccountController::class, 'update'])->name('account.update');
    Route::put('/my-account/password', [AccountController::class, 'password'])->name('account.password');
});

// The administration checks the stored right; only the booking and event admin routes depend on the admin mode
// (App\Support\AdminMode::MODE_ROUTES): there a privileged account without it gets the "Switch on admin mode"
// page instead of the 403.
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('can:admin.see-menu')
        ->get('/', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::middleware('can:admin.user')->group(function (): void {
        Route::post('users/bulk', [UserController::class, 'bulkUpdate'])->name('users.bulk');
        Route::get('users/{user}/login-history', [UserController::class, 'loginHistory'])->name('users.login-history');
        Route::get('login-history', [LoginHistoryController::class, 'index'])->name('login-history.index');
        Route::resource('users', UserController::class)->except(['show']);
        Route::post('users/{user}/password', [UserController::class, 'password'])->name('users.password');
    });

    Route::middleware(['admin.mode', 'can:admin.event'])->group(function (): void {
        Route::resource('events', EventController::class)->except(['show']);
    });

    Route::middleware('can:admin.booking')->get('statistics', [StatisticsController::class, 'index'])->name('statistics.index');

    Route::middleware(['admin.mode', 'can:admin.booking'])->group(function (): void {
        Route::post('bookings/{booking}/cancel', [App\Http\Controllers\Admin\BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::resource('bookings', App\Http\Controllers\Admin\BookingController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    });

    Route::middleware('can:admin.config')->group(function (): void {
        Route::get('config', [OptionController::class, 'edit'])->name('config.edit');
        Route::put('config', [OptionController::class, 'update'])->name('config.update');
        Route::get('config/verhalten', [OptionController::class, 'editBehavior'])->name('config.behavior.edit');
        Route::put('config/verhalten', [OptionController::class, 'updateBehavior'])->name('config.behavior.update');
        Route::get('whatsapp', [WhatsAppController::class, 'edit'])->name('whatsapp.edit');
        Route::put('whatsapp', [WhatsAppController::class, 'update'])->name('whatsapp.update');
        Route::resource('squares', SquareController::class)->except(['show']);
        Route::get('scheduler', [SchedulerController::class, 'index'])->name('scheduler.index');
        Route::post('scheduler/{task}/run', [SchedulerController::class, 'run'])
            ->where('task', '[a-z0-9][a-z0-9:._-]{0,99}')
            ->middleware('throttle:5,1')
            ->name('scheduler.run');
        Route::post('scheduler/queue/retry', [SchedulerController::class, 'retryFailed'])->name('scheduler.queue.retry');
        Route::post('scheduler/queue/flush', [SchedulerController::class, 'flushFailed'])->name('scheduler.queue.flush');
        Route::get('testmail', [TestMailController::class, 'index'])->name('testmail.index');
        Route::post('testmail', [TestMailController::class, 'send'])->name('testmail.send');
        Route::get('database', [DatabaseController::class, 'index'])->name('database.index');
        Route::post('database/migrate', [DatabaseController::class, 'migrate'])->name('database.migrate');
        Route::post('database/repair/{table}', [DatabaseController::class, 'repair'])->name('database.repair');
        Route::post('database/backups', [DatabaseController::class, 'createBackup'])->name('database.backups.create');
        Route::get('database/backups/{filename}/download', [DatabaseController::class, 'downloadBackup'])
            ->name('database.backups.download')
            ->where('filename', '^backup-\d{8}_\d{6}(-prerestore)?\.sql\.gz$');
        Route::post('database/backups/{filename}/delete', [DatabaseController::class, 'deleteBackup'])
            ->name('database.backups.delete')
            ->where('filename', '^backup-\d{8}_\d{6}(-prerestore)?\.sql\.gz$');
        Route::post('database/backups/{filename}/restore', [DatabaseController::class, 'restoreBackup'])
            ->name('database.backups.restore')
            ->where('filename', '^backup-\d{8}_\d{6}(-prerestore)?\.sql\.gz$');
    });

    Route::middleware('can:admin.api-tokens')->group(function (): void {
        Route::get('api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
        Route::post('api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
        Route::delete('api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
    });
});
