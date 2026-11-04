<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Removes 4-byte UTF-8 characters (emoji etc.) from request input.
 *
 * The legacy bs_* tables are utf8mb3 and reject such characters with a 500 (MySQL error 3988);
 * the old ZF2 app silently mangled them. We strip them before any controller sees the input.
 */
class StripFourByteCharacters
{
    /**
     * Routes that keep their 4-byte characters because they write only utf8mb4 tables.
     *
     * Route names are matched with Str::is() and paths (without leading slash) likewise.
     * Only the Members module is exempt for now. The tournament modules are NOT exempt, even
     * though their own tables will be utf8mb4: while any module path is still bridged to the
     * old ZF2 app (which connects as utf8mb3), they must be stripped too. Add them here only
     * when the bridge is removed (decommission step).
     *
     * @var list<string>
     */
    public const EXEMPT_ROUTE_NAMES = ['members.*'];

    /** @var list<string> */
    public const EXEMPT_PATHS = ['members', 'members/*'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isExempt($request)) {
            $request->query->replace(self::strip($request->query->all()));
            $request->request->replace(self::strip($request->request->all()));

            if ($request->isJson()) {
                $request->json()->replace(self::strip($request->json()->all()));
            }
        }

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        $name = $request->route()?->getName();

        return ($name !== null && $request->routeIs(...self::EXEMPT_ROUTE_NAMES))
            || $request->is(...self::EXEMPT_PATHS);
    }

    private static function strip(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::strip(...), $value);
        }

        if (is_string($value)) {
            // preg_replace returns null for invalid UTF-8: keep the original then.
            return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $value) ?? $value;
        }

        return $value;
    }
}
