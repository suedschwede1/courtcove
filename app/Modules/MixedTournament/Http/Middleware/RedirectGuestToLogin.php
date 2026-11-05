<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guests of the mixed tournament area go to the login with the module text; after the login
 * Laravel returns them to the page they asked for (url.intended).
 */
class RedirectGuestToLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        session()->flash('error', __('mixed::messages.login_required'));

        return redirect()->guest(route('login'));
    }
}
