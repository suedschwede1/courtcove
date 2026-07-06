<?php

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Modules\Api\Http\Controllers\BookingController;
use Modules\Api\Http\Controllers\DueController;
use Modules\Api\Http\Controllers\EventController;
use Modules\Api\Http\Controllers\MemberController;
use Modules\Api\Http\Controllers\PingController;
use Modules\Api\Mcp\Servers\BookingServer;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('ping', PingController::class)->name('ping');

    Mcp::web('mcp', BookingServer::class);

    Route::middleware('can:admin.booking')->group(function () {
        Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
        Route::put('bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
    });

    Route::middleware('can:admin.event')->group(function () {
        Route::post('events', [EventController::class, 'store'])->name('events.store');
        Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
    });

    Route::middleware('can:admin.members')->group(function () {
        Route::post('members', [MemberController::class, 'store'])->name('members.store');
        Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update');

        Route::post('dues', [DueController::class, 'store'])->name('dues.store');
        Route::put('dues/{due}', [DueController::class, 'update'])->name('dues.update');
    });
});
