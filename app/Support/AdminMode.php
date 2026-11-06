<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The one place that decides whether an account's privileges are in effect ("admin mode").
 *
 * Privileges (role admin/assist and the allow.* list) only count while admin mode is active;
 * without it the account is treated as a normal member in every check (User::can(), the Gate,
 * policies, the booking limits). The raw privileges stay readable via User::hasPrivilege().
 *
 * Active means:
 *  - HTTP request of the authenticated user: the request carries the cookie `admin_mode=1`
 *    AND the account holds at least one raw privilege (a member with the cookie gets nothing);
 *  - stateless API/MCP request authenticated with a personal access token: active, see
 *    tokenRequestsCountAsActive() (decision point);
 *  - no HTTP request at all (console commands, queue jobs, scheduler, unit tests): active,
 *    so commands keep their behaviour;
 *  - checks about another account than the authenticated one: active (that account's stored
 *    rights; the cookie belongs to the acting browser, not to them).
 *
 * The result is memoised on the request (attributes), so nothing static leaks between requests or tests.
 */
final class AdminMode
{
    public const COOKIE = 'admin_mode';

    /** Request attribute set by TrackAdminModeRequest: this request came through the HTTP kernel. */
    public const HTTP_MARKER = 'admin_mode.http_request';

    private const MEMO_PREFIX = 'admin_mode.active.';

    /**
     * DECISION POINT: a request authenticated with an API token (REST API, MCP server) counts as
     * admin mode active. The token is an explicit authorization created by an administrator and such
     * clients cannot send the browser cookie. Switch to false (config booking.admin_mode.api_tokens_active,
     * env ADMIN_MODE_API_TOKENS_ACTIVE) to make token requests behave like members instead.
     */
    public static function tokenRequestsCountAsActive(): bool
    {
        return (bool) config('booking.admin_mode.api_tokens_active', true);
    }

    public static function active(User $user, ?Request $request = null): bool
    {
        $request ??= self::currentRequest();

        if ($request === null || ! $request->attributes->get(self::HTTP_MARKER, false)) {
            return true;
        }

        $actor = $request->user();
        if (! $actor instanceof User || $actor->getKey() !== $user->getKey()) {
            return true;
        }

        $memoKey = self::MEMO_PREFIX.$user->getKey();
        if ($request->attributes->has($memoKey)) {
            return (bool) $request->attributes->get($memoKey);
        }

        $active = self::compute($user, $request);
        $request->attributes->set($memoKey, $active);

        return $active;
    }

    /** Whether the request asks for admin mode (cookie only, no privilege check; for the switch display). */
    public static function cookieOn(?Request $request = null): bool
    {
        $request ??= self::currentRequest();

        return $request !== null && $request->cookie(self::COOKIE) === '1';
    }

    /** Session cookie (no expiry) that turns admin mode on; path/domain/secure/SameSite from config session.*. */
    public static function makeCookie(): Cookie
    {
        // Minutes 0 = session cookie; null path/domain/secure/SameSite take the CookieJar defaults,
        // which the framework fills from config session.path / domain / secure / same_site (like the session cookie).
        return cookie(self::COOKIE, '1', 0, null, null, null, true);
    }

    /** Expired cookie that deletes the admin mode cookie on the same path/domain. */
    public static function forgetCookie(): Cookie
    {
        return cookie()->forget(self::COOKIE);
    }

    /**
     * The page to return to after switching admin mode on from the gate page: the current page for GET,
     * the referring page of the same host otherwise (a POST target cannot be reopened). Relative to the
     * app root (without the base path such as /booking), always starting with a single "/".
     */
    public static function intendedPath(Request $request): string
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            $query = $request->getQueryString();

            return self::safePath('/'.ltrim($request->path(), '/').($query !== null && $query !== '' ? '?'.$query : ''));
        }

        return self::safePath(self::pathFromSameHostUrl($request, (string) $request->headers->get('referer', '')));
    }

    /**
     * A same-host relative path or the calendar: rejects absolute and protocol-relative URLs ("//host",
     * "/\host"), backslashes and control characters, so the redirect can never leave the app.
     */
    public static function safePath(mixed $path): string
    {
        $fallback = '/calendar';

        if (! is_string($path) || $path === '' || strlen($path) > 2000) {
            return $fallback;
        }

        if ($path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return $fallback;
        }

        return $path;
    }

    private static function pathFromSameHostUrl(Request $request, string $url): string
    {
        $parts = parse_url($url);
        if ($url === '' || $parts === false || ! isset($parts['host']) || strcasecmp($parts['host'], $request->getHost()) !== 0) {
            return '';
        }

        $path = $parts['path'] ?? '/';
        $base = $request->getBaseUrl();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        $path = '/'.ltrim($path, '/');

        return isset($parts['query']) && $parts['query'] !== '' ? $path.'?'.$parts['query'] : $path;
    }

    private static function compute(User $user, Request $request): bool
    {
        // A personal access token (not Sanctum's TransientToken of a cookie session) = stateless API/MCP request.
        // At runtime the token is null (no Sanctum) or a TransientToken for cookie sessions, unlike its PHPDoc.
        /** @var mixed $token */
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken && self::tokenRequestsCountAsActive()) {
            return true;
        }

        return $request->cookie(self::COOKIE) === '1' && $user->hasAnyPrivilege();
    }

    private static function currentRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        return app('request');
    }
}
