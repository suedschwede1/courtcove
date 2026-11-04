<?php

declare(strict_types=1);

return [
    // Name of the legacy tournament database, supplied per environment (never hard-coded).
    'live_database' => env('PHASE2_LIVE_DB_DATABASE'),

    'courts' => [1 => 'Court B', 2 => 'Court D', 3 => 'Court C'],
];
