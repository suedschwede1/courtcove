<?php

declare(strict_types=1);

return [
    'beacon' => [
        // Public drand endpoints of the default chain, tried in this order (ZF2 BeaconService::$endpoints).
        'endpoints' => ['https://api.drand.sh', 'https://drand.cloudflare.com'],

        // Request and connect timeout in seconds (ZF2: 10).
        'timeout' => 10,

        'user_agent' => 'draw-beacon/1.0',
    ],
];
