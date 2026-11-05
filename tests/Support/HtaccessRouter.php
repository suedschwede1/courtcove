<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Tiny model of public/.htaccess for routing tests: walks the RewriteRule lines in file order
 * up to the front-controller rule and reports who answers a path (no leading slash).
 *
 * Understood rules: "%1 [L,R=301]" -> redirect, "index.php [L]" -> laravel,
 * "zf2-bridge.php [L]" -> zf2. Rules with other targets (guard 404, env setters) and the
 * catch-all patterns "^" / ".*" are skipped. First match wins, default laravel.
 */
final class HtaccessRouter
{
    /** @param list<array{0: string, 1: string}> $rules pattern + outcome in file order */
    private function __construct(private readonly array $rules) {}

    public static function fromFile(string $path): self
    {
        $rules = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('#^\s*RewriteRule\s+(\S+)\s+(\S+)(?:\s+\[([^\]]*)\])?\s*$#', $line, $m) !== 1) {
                continue;
            }

            [, $pattern, $target] = $m;
            $flags = $m[3] ?? '';

            if ($pattern === '^' && $target === 'index.php' && $flags === 'L') {
                break; // front controller: everything after it is irrelevant
            }

            if (in_array($pattern, ['^', '.*'], true)) {
                continue;
            }

            $outcome = match (true) {
                $target === '%1' && $flags === 'L,R=301' => 'redirect',
                $target === 'index.php' && $flags === 'L' => 'laravel',
                $target === 'zf2-bridge.php' && $flags === 'L' => 'zf2',
                default => null,
            };

            if ($outcome !== null) {
                $rules[] = [$pattern, $outcome];
            }
        }

        return new self($rules);
    }

    /** @return 'redirect'|'laravel'|'zf2' */
    public function route(string $path): string
    {
        foreach ($this->rules as [$pattern, $outcome]) {
            if (preg_match('#'.str_replace('#', '\#', $pattern).'#', $path) === 1) {
                return $outcome;
            }
        }

        return 'laravel';
    }
}
