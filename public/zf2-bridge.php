<?php

/**
 * Bridge to the legacy ZF2 tournament application.
 *
 * public/.htaccess rewrites the tournament paths here (internal rewrite); this file makes
 * ZF2's index.php believe it lives in this docroot and runs it. It is not a public URL.
 *
 * ZF2 public dir resolution:
 *   1. env ZF2_PUBLIC
 *   2. first line of <app root>/zf2.path (untracked; must be an absolute path)
 *   3. default <app root>/../zf2/public, ONLY when the app directory is named "bookingnew"
 *      (live layout: ~/apps/bookingnew + ~/apps/zf2). Any other app directory (e.g.
 *      bookingnew-staging) must set 1 or 2, so a staging host can never silently serve the live
 *      ZF2 install; without them the bridge answers 503.
 *
 * The logic runs inside a closure so no variables leak into ZF2's global scope; the final
 * `require` stays at global scope, as ZF2's index.php expects.
 */
require (static function (): string {
    $fail = static function (int $status, string $message): never {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
        exit;
    };

    // Only reachable through the rewrite rules. After an internal rewrite REQUEST_URI is the
    // ORIGINAL uri (e.g. /mixed-tournament), so it never contains the bridge name. Check the
    // decoded path anywhere (PATH_INFO requests like /zf2-bridge.php/backend, %-encoded names).
    $requestPath = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));

    if (stripos($requestPath, 'zf2-bridge.php') !== false) {
        $fail(404, 'Not found');
    }

    $appRoot = dirname(__DIR__);
    $zf2Public = null;
    $fromEnv = getenv('ZF2_PUBLIC');

    if ($fromEnv !== false && trim($fromEnv) !== '') {
        $zf2Public = trim($fromEnv);
    } elseif (is_readable($appRoot.'/zf2.path')) {
        $lines = file($appRoot.'/zf2.path', FILE_IGNORE_NEW_LINES);
        $first = trim((string) ($lines[0] ?? ''));

        // An explicit file must hold an absolute path; anything else is a misconfiguration.
        $zf2Public = str_starts_with($first, '/') ? $first : null;
    } elseif (basename($appRoot) === 'bookingnew') {
        $zf2Public = dirname($appRoot).'/zf2/public';
    }

    if ($zf2Public !== null) {
        $zf2Public = rtrim($zf2Public, '/');
    }

    $zf2Index = $zf2Public === null ? null : $zf2Public.'/index.php';

    if ($zf2Index === null || ! is_file($zf2Index) || ! is_readable($zf2Index)) {
        $fail(503, 'Tournament module temporarily unavailable.');
    }

    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    $_SERVER['SCRIPT_FILENAME'] = $zf2Index;
    $_SERVER['SCRIPT_NAME'] = $dir.'/index.php';
    $_SERVER['PHP_SELF'] = $dir.'/index.php';

    return $zf2Index;
})();
