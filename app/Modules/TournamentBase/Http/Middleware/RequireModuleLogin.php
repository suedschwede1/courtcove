<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends guests to the login form with a return path (like ZF2's redirect to user/login) and an
 * optional flash message naming the area they tried to open.
 */
class RequireModuleLogin
{
    public function handle(Request $request, Closure $next, ?string $flashKey = null): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        // Keeps the base path (e.g. /booking) so the login form returns into the app; the query
        // string only survives for GET (a POST target is re-opened as a plain GET).
        $target = $request->isMethod('GET')
            ? $request->getRequestUri()
            : $request->getBaseUrl().'/'.$request->path();

        // Always an in-app absolute path; anything odd falls back to the login default.
        if (! str_starts_with($target, '/') || str_starts_with($target, '//')) {
            $target = '/';
        }

        $redirect = redirect()->route('login', ['redirect_to' => $target]);

        if ($flashKey !== null && $flashKey !== '') {
            $redirect->with('error', __($flashKey));
        }

        return $redirect;
    }
}
