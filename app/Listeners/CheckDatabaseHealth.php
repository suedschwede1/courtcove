<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;

class CheckDatabaseHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        DB::connection()->getPdo();
    }
}
