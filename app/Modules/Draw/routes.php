<?php

declare(strict_types=1);

use App\Modules\Draw\Http\Controllers\Admin\DrawAdminController;
use App\Modules\Draw\Http\Controllers\Admin\DrawLifecycleController;
use App\Modules\Draw\Http\Controllers\RecordController;
use App\Modules\Draw\Http\Controllers\TokenController;
use Illuminate\Support\Facades\Route;

// Public record: anyone must be able to recompute the draw. Club decision 2026-11-06: login required while
// config tournament-base.require_login is on (tournament-base.public; the JSON then answers a guest with 401).
Route::get('draw/record/{slug}', [RecordController::class, 'show'])
    ->middleware('tournament-base.public')
    ->where('slug', '[A-Za-z0-9_-]{4,32}')
    ->name('draw.record');

Route::get('draw/record/{slug}/json', [RecordController::class, 'json'])
    ->middleware('tournament-base.public')
    ->where('slug', '[A-Za-z0-9_-]{4,32}')
    ->name('draw.record.json');

// Token pick for the participants (old TokenController): login only, no admin privilege. The login runs
// before the draw id is resolved; a GET on the POST-only pick redirects to the token page (ZF2).
Route::prefix('draw/tokens')->name('draw.tokens')
    ->middleware(['tournament-base.login:draw::common.login_required'])
    ->group(function (): void {
        Route::get('{did}', [TokenController::class, 'show'])->whereNumber('did')->name('');
        Route::post('{did}/pick', [TokenController::class, 'pick'])->whereNumber('did')->name('.pick');
        Route::get('{did}/pick', [TokenController::class, 'redirectToShow'])->whereNumber('did')->name('.pick.redirect');
    });

// Draws admin (old Draw AdminController, privilege admin.draw). The module login middleware is the
// authentication (a plain 'auth' would run first and drop the module message); the gate runs after it,
// before any posted id is resolved. A GET on a POST-only action redirects to the overview (ZF2).
Route::prefix('draw')->name('draw.')
    ->middleware(['tournament-base.login:draw::common.login_required', 'can:admin.draw'])
    ->group(function (): void {
        Route::get('', [DrawAdminController::class, 'index'])->name('index');
        Route::post('commit', [DrawAdminController::class, 'commit'])->name('commit');
        Route::get('commit', [DrawAdminController::class, 'redirectToIndex'])->name('commit.redirect');

        Route::post('open/{did}', [DrawLifecycleController::class, 'open'])->whereNumber('did')->name('open');
        Route::post('delete/{did}', [DrawLifecycleController::class, 'delete'])->whereNumber('did')->name('delete');
        Route::post('execute/{did}', [DrawLifecycleController::class, 'execute'])->whereNumber('did')->name('execute');
        Route::post('close/{did}', [DrawLifecycleController::class, 'close'])->whereNumber('did')->name('close');
        Route::get('{action}/{did}', [DrawLifecycleController::class, 'redirectToIndex'])
            ->whereIn('action', ['open', 'close', 'delete', 'execute'])->whereNumber('did')->name('action.redirect');
    });
