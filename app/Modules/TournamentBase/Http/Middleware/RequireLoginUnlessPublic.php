<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guard of the tournament pages that can be public (Pyramid ranking, rules and challenges, the draw record and its
 * JSON): while config tournament-base.require_login is on (default), guests are sent to the login with the return
 * path like every other tournament page, and a JSON request gets a plain 401. With the setting off they pass.
 */
final class RequireLoginUnlessPublic extends RequireModuleLogin
{
    public function handle(Request $request, Closure $next, ?string $flashKey = null): Response
    {
        if (! config('tournament-base.require_login', true) || $request->user() !== null) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->routeIs('*.json')) {
            return response()->json(['message' => __('tournament-base::common.login_required')], 401);
        }

        return parent::handle($request, $next, $flashKey ?? 'tournament-base::common.login_required');
    }
}
