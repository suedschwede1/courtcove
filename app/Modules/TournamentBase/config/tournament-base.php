<?php

declare(strict_types=1);

return [
    // Name of the legacy tournament database, supplied per environment (never hard-coded).
    'live_database' => env('PHASE2_LIVE_DB_DATABASE'),

    // Route-name patterns where an expired form token redirects back with a notice instead of a 419 page.
    'csrf_redirect_routes' => ['pyramide.*'],

    // Optional court names, supplied per environment as JSON, e.g. {"1":"Alpha","2":"Beta"}.
    // Invalid or missing JSON yields no names.
    'courts' => (static function (): array {
        $decoded = json_decode((string) env('TOURNAMENT_COURTS', '[]'), true);

        return is_array($decoded) ? $decoded : [];
    })(),
];
