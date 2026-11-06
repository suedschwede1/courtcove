<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AdminMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `admin.mode[:privilege,...]` for the administration areas.
 *
 * Runs the request and only steps in when the answer is 403 for a signed-in account that holds the
 * area's privilege as stored (raw) while admin mode is off: then the friendly "Switch on admin mode"
 * page (HTML) or a 403 JSON with `admin_mode_required: true` replaces the plain 403. Everything else
 * passes unchanged, so per-ladder / per-event grants (member rights) keep working without admin mode,
 * guests still get the login and accounts without the privilege still get the plain 403.
 *
 * The area's privilege: the middleware parameters if given, else the abilities of the route's own
 * `can:` middleware, else any privilege. Place it before `can:` / the controller checks; authorization
 * runs before anything is written, so the denied run has no side effects.
 */
final class EnsureAdminMode
{
    public const STRICT = 'strict';

    public function handle(Request $request, Closure $next, string ...$privileges): Response
    {
        $user = $request->user();

        if (! $user instanceof User || AdminMode::active($user, $request)) {
            return $next($request);
        }

        // `strict` (first parameter): answer with the gate page before running the request, for start pages
        // that answer 200 to everyone (e.g. the mixed admin overview) but are meant for the global right.
        $strict = ($privileges[0] ?? null) === self::STRICT;
        if ($strict) {
            array_shift($privileges);
        }

        if ($strict && $this->holdsAreaPrivilege($user, $request, $privileges)) {
            return $this->gatePage($request);
        }

        $response = $next($request);

        if ($response->getStatusCode() !== Response::HTTP_FORBIDDEN || ! $this->holdsAreaPrivilege($user, $request, $privileges)) {
            return $response;
        }

        return $this->gatePage($request);
    }

    private function gatePage(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('booking.admin_mode.required_json'),
                'admin_mode_required' => true,
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->view('admin-mode.required', [
            'intended' => AdminMode::intendedPath($request),
        ], Response::HTTP_FORBIDDEN);
    }

    /** @param  list<string>  $privileges */
    private function holdsAreaPrivilege(User $user, Request $request, array $privileges): bool
    {
        if ($privileges === []) {
            $privileges = $this->routeAbilities($request);
        }

        return $privileges === [] ? $user->hasAnyPrivilege() : $user->hasPrivilege(implode(',', $privileges));
    }

    /** @return list<string> abilities of the route's `can:` middleware (only privilege names, no policy abilities) */
    private function routeAbilities(Request $request): array
    {
        $abilities = [];
        foreach ($request->route()?->gatherMiddleware() ?? [] as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
                $ability = explode(',', substr($middleware, 4))[0];
                if (in_array($ability, User::PRIVILEGES, true)) {
                    $abilities[] = $ability;
                }
            }
        }

        return $abilities;
    }
}
