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

        $query = $request->isMethod('GET') ? $request->getQueryString() : null;
        $target = '/'.$request->path().($query ? '?'.$query : '');

        $redirect = redirect()->route('login', ['redirect_to' => $target]);

        if ($flashKey !== null && $flashKey !== '') {
            $redirect->with('error', __($flashKey));
        }

        return $redirect;
    }
}
