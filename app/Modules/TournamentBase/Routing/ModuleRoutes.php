<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Routing;

/**
 * Single place for the middleware every module route group shares.
 *
 * Four-byte character stripping is applied globally (web and api groups), so module
 * routes get it through the web group and must not be added to its opt-out list.
 */
final class ModuleRoutes
{
    /** @return list<string> */
    public static function middleware(): array
    {
        return ['web'];
    }
}
