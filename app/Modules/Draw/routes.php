<?php

declare(strict_types=1);

use App\Modules\Draw\Http\Controllers\Admin\DrawAdminController;
use App\Modules\Draw\Http\Controllers\RecordController;
use Illuminate\Support\Facades\Route;

// Public record - deliberately WITHOUT login: anyone must be able to recompute the draw.
Route::get('draw/record/{slug}', [RecordController::class, 'show'])
    ->where('slug', '[A-Za-z0-9_-]{4,32}')
    ->name('draw.record');

Route::get('draw/record/{slug}/json', [RecordController::class, 'json'])
    ->where('slug', '[A-Za-z0-9_-]{4,32}')
    ->name('draw.record.json');

// Draws admin (old Draw AdminController, privilege admin.draw). The module login middleware is the
// authentication (a plain 'auth' would run first and drop the module message); the gate runs after it,
// before any posted id is resolved. A GET on a POST-only action redirects to the overview (ZF2).
Route::prefix('draw')->name('draw.')
    ->middleware(['tournament-base.login:draw::common.login_required', 'can:admin.draw'])
    ->group(function (): void {
        Route::get('', [DrawAdminController::class, 'index'])->name('index');
        Route::post('commit', [DrawAdminController::class, 'commit'])->name('commit');
        Route::get('commit', [DrawAdminController::class, 'redirectToIndex'])->name('commit.redirect');
    });
