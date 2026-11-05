<?php

declare(strict_types=1);

use App\Modules\Pyramid\Http\Controllers\LadderController;
use Illuminate\Support\Facades\Route;

// Public group: readable without login. Later tasks add their routes of the module's route table
// above the last route; `pyramide/{ladderId}` stays registered last so no fixed word is shadowed.
Route::get('pyramide', [LadderController::class, 'index'])->name('pyramide.index');

Route::get('pyramide/{ladderId}', [LadderController::class, 'index'])
    ->whereNumber('ladderId')
    ->name('pyramide.ladder');
