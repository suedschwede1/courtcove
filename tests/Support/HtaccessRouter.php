<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Tiny model of public/.htaccess for routing tests: walks the RewriteRule lines in file order
 * up to the front-controller rule and reports who answers a path (no leading slash).
 *
 * Understood rules: "%1 [L,R=301]" -> redirect, "index.php [L]" -> laravel,
 * "zf2-bridge.php [L]" -> zf2. Rules with other targets (guard 404, env setters) and the
 * catch-all patterns "^" / ".*" are skipped, except the "^ %1 [L,R=301]" slash redirect.
 * A rule to one of those three targets with other flags makes the helper throw instead of
 * being silently ignored (no false greens).
 *
 * RewriteConds directly above a modelled rule: "%{REQUEST_URI} <regex>" is matched against
 * "/".$path, "%{REQUEST_FILENAME} !-d" / "!-f" count as true (the routed paths are no files or
 * directories); any other condition on a modelled rule makes the helper throw.
 * First match wins, default laravel.
 */
final class HtaccessRouter
{
    /** @param list<array{0: string, 1: string, 2: list<string>}> $rules pattern, outcome and REQUEST_URI condition regexes in file order */
    private function __construct(private readonly array $rules) {}

    public static function fromFile(string $path): self
    {
        $rules = [];
        $conds = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('#^\s*RewriteCond\s+(\S+)\s+(.+?)\s*$#', $line, $c) === 1) {
                $conds[] = [$c[1], $c[2]];

                continue;
            }

            if (preg_match('#^\s*RewriteRule\s+(\S+)\s+(\S+)(?:\s+\[([^\]]*)\])?\s*$#', $line, $m) !== 1) {
                continue;
            }

            $ruleConds = $conds;
            $conds = [];
            [, $pattern, $target] = $m;
            $flags = $m[3] ?? '';

            if ($pattern === '^' && $target === 'index.php' && $flags === 'L') {
                break; // front controller: everything after it is irrelevant
            }

            $outcome = match (true) {
                $target === '%1' && $flags === 'L,R=301' => 'redirect',
                $target === 'index.php' && $flags === 'L' => 'laravel',
                $target === 'zf2-bridge.php' && $flags === 'L' => 'zf2',
                default => null,
            };

            if ($outcome === null && in_array($target, ['index.php', 'zf2-bridge.php', '%1'], true)) {
                throw new \LogicException("HtaccessRouter cannot model: {$line}");
            }

            if ($outcome === null || (in_array($pattern, ['^', '.*'], true) && $outcome !== 'redirect')) {
                continue;
            }

            $uriConds = [];

            foreach ($ruleConds as [$var, $test]) {
                if ($var === '%{REQUEST_URI}') {
                    $uriConds[] = $test;
                } elseif (! ($var === '%{REQUEST_FILENAME}' && in_array($test, ['!-d', '!-f'], true))) {
                    throw new \LogicException("HtaccessRouter cannot model RewriteCond {$var} {$test} above: {$line}");
                }
            }

            $rules[] = [$pattern, $outcome, $uriConds];
        }

        return new self($rules);
    }

    /** @return 'redirect'|'laravel'|'zf2' */
    public function route(string $path): string
    {
        foreach ($this->rules as [$pattern, $outcome, $uriConds]) {
            if (preg_match('#'.str_replace('#', '\#', $pattern).'#', $path) !== 1) {
                continue;
            }

            foreach ($uriConds as $cond) {
                if (preg_match('#'.str_replace('#', '\#', $cond).'#', '/'.$path) !== 1) {
                    continue 2;
                }
            }

            return $outcome;
        }

        return 'laravel';
    }
}
