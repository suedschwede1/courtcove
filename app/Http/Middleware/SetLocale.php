<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active UI locale for the request: authenticated user's
 * stored preference (bs_users_meta 'locale') > 'locale' cookie > config
 * default. Unknown/invalid values are ignored at each step.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $available */
        $available = config('app.available_locales', ['de']);

        $locale = $this->fromUser($request, $available)
            ?? $this->fromCookie($request, $available)
            ?? (string) config('app.locale');

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    private function fromUser(Request $request, array $available): ?string
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $locale = $user->getMeta('locale');

        return $locale !== null && in_array($locale, $available, true) ? $locale : null;
    }

    private function fromCookie(Request $request, array $available): ?string
    {
        $locale = $request->cookie('locale');

        return $locale !== null && in_array($locale, $available, true) ? $locale : null;
    }
}
