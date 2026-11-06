<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\ApiTokenController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DatabaseController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\LoginHistoryController;
use App\Http\Controllers\Admin\OptionController;
use App\Http\Controllers\Admin\SquareController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\TestMailController;
use App\Http\Controllers\Admin\UserController;
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
use App\Http\Controllers\UiGalleryController;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth')->group(function (): void {
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::get('/bookings/players', [BookingController::class, 'players'])->name('bookings.players');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}/edit', [BookingController::class, 'edit'])->name('bookings.edit');
    Route::put('/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');

    Route::get('/my-bookings', [AccountController::class, 'bookings'])->name('account.bookings');
    Route::get('/my-account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/my-account', [AccountController::class, 'update'])->name('account.update');
    Route::put('/my-account/password', [AccountController::class, 'password'])->name('account.password');
});

// admin.mode: without admin mode a privileged account gets the "Switch on admin mode" page instead of the 403.
Route::middleware(['auth', 'admin.mode'])->prefix('admin')->name('admin.')->group(function (): void {
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

    Route::middleware('can:admin.event')->group(function (): void {
        Route::resource('events', EventController::class)->except(['show']);
    });

    Route::middleware('can:admin.booking')->group(function (): void {
        Route::get('statistics', [StatisticsController::class, 'index'])->name('statistics.index');
        Route::post('bookings/{booking}/cancel', [App\Http\Controllers\Admin\BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::resource('bookings', App\Http\Controllers\Admin\BookingController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    });

    Route::middleware('can:admin.config')->group(function (): void {
        Route::get('config', [OptionController::class, 'edit'])->name('config.edit');
        Route::put('config', [OptionController::class, 'update'])->name('config.update');
        Route::get('config/verhalten', [OptionController::class, 'editBehavior'])->name('config.behavior.edit');
        Route::put('config/verhalten', [OptionController::class, 'updateBehavior'])->name('config.behavior.update');
        Route::resource('squares', SquareController::class)->except(['show']);
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
