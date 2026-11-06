<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AdminMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware: marks the request as a real HTTP request, so AdminMode::active() evaluates
 * the admin mode cookie. Without the mark (console, queue, scheduler, unit tests) admin mode
 * counts as active and privileges behave as stored.
 */
final class TrackAdminModeRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(AdminMode::HTTP_MARKER, true);

        return $next($request);
    }
}
