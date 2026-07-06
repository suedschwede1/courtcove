<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DatabaseController;
use App\Http\Controllers\Admin\EventController;
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

Route::get('/lang/{locale}', [LocaleController::class, 'switch'])->name('lang.switch');

Route::get('/admin-mode/{state}', [AdminModeController::class, 'set'])
    ->whereIn('state', ['on', 'off'])
    ->middleware(['auth', 'can:admin.booking'])
    ->name('admin-mode.set');

Route::get('/', static fn () => redirect()->route('calendar.index'));
Route::get('/booking', static fn () => redirect()->route('calendar.index'));
Route::get('/bookings', static fn () => redirect()->route('calendar.index'));
Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

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

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('can:admin.see-menu')
        ->get('/', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::middleware('can:admin.user')->group(function (): void {
        Route::post('users/bulk', [UserController::class, 'bulkUpdate'])->name('users.bulk');
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
});
