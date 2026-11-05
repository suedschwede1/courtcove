<?php

declare(strict_types=1);

use App\Modules\Pyramid\Http\Controllers\ChallengeController;
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

// Participant group: guests are sent to the login with the flash naming the area.
Route::middleware('tournament-base.login:pyramid::ui.login_to_play')->group(function (): void {
    Route::get('pyramide/fordern/{playerId}', [ChallengeController::class, 'confirm'])
        ->whereNumber('playerId')
        ->name('pyramide.challenge.confirm');
    Route::post('pyramide/fordern/{playerId}', [ChallengeController::class, 'store'])
        ->whereNumber('playerId')
        ->name('pyramide.challenge.store');
});

Route::get('pyramide/{ladderId}', [LadderController::class, 'index'])
    ->whereNumber('ladderId')
    ->name('pyramide.ladder');
