<?php

declare(strict_types=1);

use App\Modules\Tournament\Http\Controllers\BracketController;
use App\Modules\Tournament\Http\Controllers\GroupsController;
use App\Modules\Tournament\Http\Controllers\MatchController;
use App\Modules\Tournament\Http\Controllers\MyMatchesController;
use App\Modules\Tournament\Http\Controllers\OverviewController;
use App\Modules\Tournament\Http\Controllers\PlayersController;
use App\Modules\Tournament\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

// Routes of the tournament (club championship) module (URL prefix `tournament`, route names `tournament.*`).
// Paths and parameter patterns are copied from the old module.config.php. Every championship page needs a
// login (old LoginRedirectTrait, "Alle Turnieransichten setzen einen Login voraus"); what a viewer sees
// depends on the TournamentAccess level. The module login middleware is the authentication: a plain 'auth'
// next to it would run first (middleware priority) and drop the module message and the redirect target.
Route::prefix('tournament')->name('tournament.')
    ->middleware('tournament-base.login:tournament::common.login_required')
    ->group(function (): void {
        Route::get('', [OverviewController::class, 'index'])->name('index');
        Route::get('groups/{group?}', [GroupsController::class, 'show'])->where('group', '[A-Z]')->name('groups');
        Route::get('schedule/{filter?}', [ScheduleController::class, 'show'])->where('filter', '[a-z]+')->name('schedule');
        Route::get('bracket/{phase?}', [BracketController::class, 'show'])->where('phase', '[A-Za-z_]+')->name('bracket');
        Route::get('players', [PlayersController::class, 'index'])->name('players');
        Route::get('mine', [MyMatchesController::class, 'index'])->name('mine');
        Route::get('modus', [OverviewController::class, 'mode'])->name('mode');

        // The match page (date and result) and its actions. A GET on an action path redirects to the page (ZF2).
        Route::get('match/{tmid}', [MatchController::class, 'show'])->whereNumber('tmid')->name('match');
        Route::post('match/{tmid}/save', [MatchController::class, 'save'])->whereNumber('tmid')->name('match.save');
        Route::post('match/{tmid}/confirm', [MatchController::class, 'confirm'])->whereNumber('tmid')->name('match.confirm');
        Route::post('match/{tmid}/delete', [MatchController::class, 'delete'])->whereNumber('tmid')->name('match.delete');
        Route::get('match/{tmid}/{action}', [MatchController::class, 'redirectToMatch'])
            ->whereNumber('tmid')->whereIn('action', ['save', 'confirm', 'delete'])->name('match.redirect');
    });
