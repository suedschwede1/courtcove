<?php

declare(strict_types=1);

use App\Modules\Draw\Http\Controllers\RecordController;
use Illuminate\Support\Facades\Route;

Route::get('draw/record/{slug}', [RecordController::class, 'show'])
    ->where('slug', '[A-Za-z0-9_-]{4,32}')
    ->name('draw.record');

Route::get('draw/record/{slug}/json', [RecordController::class, 'json'])
    ->where('slug', '[A-Za-z0-9_-]{4,32}')
    ->name('draw.record.json');
