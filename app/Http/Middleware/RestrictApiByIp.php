<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the API path (/api and everything below it) to the client addresses listed in
 * booking.api.allowed_ips (env API_ALLOWED_IPS, comma separated). Every other path is untouched.
 *
 * Fails closed: an empty list refuses everyone on the API path. The address is Request::ip(), which here is the
 * connection address (no trusted proxies are configured), so forwarding headers cannot spoof an allowed address.
 * The list lives in the server environment, not in the repository.
 */
final class RestrictApiByIp
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api', 'api/*')) {
            return $next($request);
        }

        $allowed = config('booking.api.allowed_ips', []);
        $allowed = is_array($allowed) ? $allowed : self::parse((string) $allowed);

        if (in_array((string) $request->ip(), $allowed, true)) {
            return $next($request);
        }

        // Neutral body: no address (neither the caller's nor the allowed ones) is revealed.
        return response('Forbidden', Response::HTTP_FORBIDDEN, ['X-Api-Restricted' => '1', 'Content-Type' => 'text/plain']);
    }

    /** @return list<string> */
    public static function parse(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)), static fn (string $ip): bool => $ip !== '')));
    }
}
