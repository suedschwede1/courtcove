<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalendarController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

Route::get('/', static fn() => redirect()->route('calendar.index'));
Route::get('/booking', static fn() => redirect()->route('calendar.index'));
Route::get('/bookings', static fn() => redirect()->route('calendar.index'));
Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

Route::middleware('auth')->group(function (): void {
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('can:admin.see-menu')
        ->get('/', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::middleware('can:admin.user')->group(function (): void {
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class)->except(['show']);
        Route::post('users/{user}/password', [\App\Http\Controllers\Admin\UserController::class, 'password'])->name('users.password');
    });

    Route::middleware('can:admin.event')->group(function (): void {
        Route::resource('events', \App\Http\Controllers\Admin\EventController::class)->except(['show']);
    });
});
