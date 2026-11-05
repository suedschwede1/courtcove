<?php

use App\Modules\Draw\DrawServiceProvider;
use App\Modules\Members\MembersServiceProvider;
use App\Modules\Pyramid\PyramidServiceProvider;
use App\Modules\TournamentBase\TournamentBaseServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    MembersServiceProvider::class,
    TournamentBaseServiceProvider::class,
    DrawServiceProvider::class,
    PyramidServiceProvider::class,
];
