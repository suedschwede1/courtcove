<?php

/**
 * Bridge to the legacy ZF2 tournament application.
 *
 * public/.htaccess rewrites the tournament paths here (internal rewrite); this file makes
 * ZF2's index.php believe it lives in this docroot and runs it. It is not a public URL.
 *
 * ZF2 public dir resolution: env ZF2_PUBLIC, then first line of <app root>/zf2.path
 * (untracked), then <app root>/../../zf2/public.
 */
$requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

if (basename($requestPath) === 'zf2-bridge.php') {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$zf2Public = getenv('ZF2_PUBLIC');

if ($zf2Public === false || trim($zf2Public) === '') {
    $zf2Public = null;
    $pathFile = dirname(__DIR__).'/zf2.path';

    if (is_readable($pathFile)) {
        $lines = file($pathFile, FILE_IGNORE_NEW_LINES);
        $first = $lines === false ? '' : trim((string) ($lines[0] ?? ''));

        if ($first !== '') {
            $zf2Public = $first;
        }
    }

    $zf2Public ??= dirname(__DIR__, 2).'/zf2/public';
}

$zf2Public = rtrim(trim($zf2Public), '/');
$zf2Index = $zf2Public.'/index.php';

if (! is_file($zf2Index) || ! is_readable($zf2Index)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Tournament module temporarily unavailable.';
    exit;
}

$dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
$_SERVER['SCRIPT_FILENAME'] = $zf2Index;
$_SERVER['SCRIPT_NAME'] = $dir.'/index.php';
$_SERVER['PHP_SELF'] = $dir.'/index.php';

require $zf2Index;
