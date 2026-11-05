<?php

declare(strict_types=1);

use App\Modules\Pyramid\Http\Controllers\Admin\LadderAdminController;
use App\Modules\Pyramid\Http\Controllers\ChallengeController;
use App\Modules\Pyramid\Http\Controllers\JoinController;
use App\Modules\Pyramid\Http\Controllers\LadderController;
use App\Modules\Pyramid\Http\Controllers\PlayerController;
use Illuminate\Support\Facades\Route;

// Public group: readable without login. Later tasks add their routes of the module's route table
// above the last route; `pyramide/{ladderId}` stays registered last so no fixed word is shadowed.
Route::get('pyramide', [LadderController::class, 'index'])->name('pyramide.index');

Route::get('pyramide/regeln', [LadderController::class, 'rules'])->name('pyramide.rules');
Route::get('pyramide/forderungen', [LadderController::class, 'challenges'])->name('pyramide.challenges');
Route::get('pyramide/spieler/{playerId}', [PlayerController::class, 'show'])
    ->whereNumber('playerId')
    ->name('pyramide.player');
Route::get('pyramide/forderung/{challengeId}', [ChallengeController::class, 'show'])
    ->whereNumber('challengeId')
    ->name('pyramide.challenge.show');

// Late sign-up: guests go to the login without a flash (the area is not "playing" yet).
Route::post('pyramide/anmelden', [JoinController::class, 'store'])
    ->middleware('tournament-base.login')
    ->name('pyramide.join');

// Participant group: guests are sent to the login with the flash naming the area.
Route::middleware('tournament-base.login:pyramid::ui.login_to_play')->group(function (): void {
    foreach (['termin' => 'schedule', 'ergebnis' => 'result', 'ablehnen' => 'decline', 'zurueckziehen' => 'withdraw'] as $path => $action) {
        Route::post('pyramide/forderung/{challengeId}/'.$path, [ChallengeController::class, $action])
            ->whereNumber('challengeId')
            ->name('pyramide.challenge.'.$action);
    }

    Route::get('pyramide/fordern/{playerId}', [ChallengeController::class, 'confirm'])
        ->whereNumber('playerId')
        ->name('pyramide.challenge.confirm');
    Route::post('pyramide/fordern/{playerId}', [ChallengeController::class, 'store'])
        ->whereNumber('playerId')
        ->name('pyramide.challenge.store');
});

// Administration: guests are sent to the login with the admin flash; the right itself is checked in the controllers.
Route::middleware('tournament-base.login:pyramid::admin.login_required')->prefix('pyramide/admin')->group(function (): void {
    Route::get('/', [LadderAdminController::class, 'index'])->name('pyramide.admin.index');
    Route::match(['get', 'post'], 'create', [LadderAdminController::class, 'create'])->name('pyramide.admin.create');
});

Route::get('pyramide/{ladderId}', [LadderController::class, 'index'])
    ->whereNumber('ladderId')
    ->name('pyramide.ladder');
