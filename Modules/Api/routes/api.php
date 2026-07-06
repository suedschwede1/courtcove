<?php

use Illuminate\Support\Facades\Route;
use Modules\Api\Http\Controllers\AuthController;
use Modules\Api\Http\Controllers\PingController;

Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('ping', PingController::class)->name('ping');
    });
});
