<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:admin.members'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        // Route::resource('members', \App\Modules\Members\Http\Controllers\ExampleController::class);
    });
