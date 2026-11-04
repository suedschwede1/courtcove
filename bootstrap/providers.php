<?php

use App\Modules\Members\MembersServiceProvider;
use App\Modules\TournamentBase\TournamentBaseServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    MembersServiceProvider::class,
    TournamentBaseServiceProvider::class,
];
