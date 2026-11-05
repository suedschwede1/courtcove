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
 * "/".$path, "%{REQUEST_FILENAME} !-d" / "!-f" are checked against the document root (the
 * directory of the .htaccess), so a real directory such as css/ is not reported as a redirect;
 * any other condition, and any condition carrying flags like [OR] or [NC] (they would change how
 * conditions combine and are not modelled), makes the helper throw.
 * First match wins; a path that is an existing file or directory of the document root and was not
 * rewritten is 'static', everything else defaults to laravel.
 */
final class HtaccessRouter
{
    /** @param list<array{0: string, 1: string, 2: list<array{0: string, 1: string}>}> $rules pattern, outcome and conditions (variable, test) in file order */
    private function __construct(private readonly array $rules, private readonly string $docRoot) {}

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

            foreach ($ruleConds as [$var, $test]) {
                if (preg_match('#\s\[[A-Za-z,=!]+\]$#', $test) === 1) {
                    throw new \LogicException("HtaccessRouter does not model RewriteCond flags: {$var} {$test} above: {$line}");
                }

                if (! ($var === '%{REQUEST_URI}' || ($var === '%{REQUEST_FILENAME}' && in_array($test, ['!-d', '!-f'], true)))) {
                    throw new \LogicException("HtaccessRouter cannot model RewriteCond {$var} {$test} above: {$line}");
                }
            }

            $rules[] = [$pattern, $outcome, $ruleConds];
        }

        return new self($rules, dirname($path));
    }

    /** @return 'redirect'|'laravel'|'zf2'|'static' */
    public function route(string $path): string
    {
        $onDisk = $this->docRoot.'/'.$path;

        foreach ($this->rules as [$pattern, $outcome, $conds]) {
            if (preg_match('#'.str_replace('#', '\#', $pattern).'#', $path) !== 1) {
                continue;
            }

            foreach ($conds as [$var, $test]) {
                $holds = match (true) {
                    $var === '%{REQUEST_URI}' => preg_match('#'.str_replace('#', '\#', $test).'#', '/'.$path) === 1,
                    $test === '!-d' => ! is_dir($onDisk),
                    default => ! is_file($onDisk),
                };

                if (! $holds) {
                    continue 2;
                }
            }

            return $outcome;
        }

        // Not rewritten: Apache serves an existing file or directory itself (the root answers with index.php).
        return $path !== '' && (is_file($onDisk) || is_dir($onDisk)) ? 'static' : 'laravel';
    }
}
