<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Support;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Expired form tokens on tournament module routes bounce back with a notice (as in ZF2)
 * instead of the bare 419 page. Every other route keeps the framework behaviour.
 */
class CsrfExpiredRedirect
{
    public static function register(Exceptions $exceptions): void
    {
        $handler = static function (Throwable $e, Request $request) {
            $isExpired = $e instanceof TokenMismatchException
                || ($e instanceof HttpException && $e->getStatusCode() === 419);

            if (! $isExpired) {
                return null;
            }

            $patterns = (array) config('tournament-base.csrf_redirect_routes', []);

            if ($patterns === [] || ! $request->routeIs(...$patterns)) {
                return null;
            }

            // API/AJAX callers keep the plain 419.
            if ($request->expectsJson()) {
                return null;
            }

            return redirect()->to(self::safePreviousUrl($request, self::fallbackRoute($request)))->with('error', __('tournament-base::common.csrf_expired'));
        };

        $exceptions->render(fn (TokenMismatchException $e, Request $request) => $handler($e, $request));
        $exceptions->render(fn (HttpException $e, Request $request) => $handler($e, $request));
    }

    /**
     * The Referer only when it points at this host; otherwise the module's fallback route (default: the login page).
     */
    private static function fallbackRoute(Request $request): string
    {
        foreach ((array) config('tournament-base.csrf_redirect_fallbacks', []) as $pattern => $name) {
            if ($request->routeIs((string) $pattern)) {
                return (string) $name;
            }
        }

        return 'login';
    }

    private static function safePreviousUrl(Request $request, string $fallback = 'login'): string
    {
        $previous = (string) $request->headers->get('referer', '');
        $host = $previous !== '' ? parse_url($previous, PHP_URL_HOST) : null;

        if (is_string($host) && strcasecmp($host, $request->getHost()) === 0) {
            return $previous;
        }

        return route($fallback);
    }
}
