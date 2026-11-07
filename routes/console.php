<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Player exchange: lapse unfilled player searches one hour before the start (Verfall).
Schedule::command('player-exchange:expire')->name('player-exchange:expire')->everyFiveMinutes()->withoutOverlapping();
