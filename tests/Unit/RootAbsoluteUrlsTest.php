<?php

declare(strict_types=1);

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The app runs under the /booking base path in production, so root-absolute URL literals
 * ('/bookings/...', fetch('/members/...')) in views/JS point at the wrong place.
 * Use url()/route()/@js(...) instead.
 */
class RootAbsoluteUrlsTest extends TestCase
{
    #[Test]
    public function views_and_js_contain_no_root_absolute_url_literals(): void
    {
        $offenders = [];
        $root = dirname(__DIR__, 2).DIRECTORY_SEPARATOR;

        foreach ($this->files() as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) as $i => $line) {
                if ($this->isOffending($line)) {
                    $offenders[] = str_replace($root, '', $file).':'.($i + 1).': '.trim($line);
                }
            }
        }

        $this->assertSame([], $offenders, "Root-absolute URLs break under the /booking base path:\n".implode("\n", $offenders));
    }

    #[Test]
    public function detector_flags_known_bad_patterns_and_allows_safe_ones(): void
    {
        $bad = [
            "const r = await fetch('/bookings/players?q=' + v);",
            'await fetch(`/members/${id}/field`, {',
            "x-bind:action=\"'/bookings/' + bid\"",
            '<form action="/bookings" method="POST">',
            "window.location.href = '/calendar';",
        ];
        $good = [
            "fetch(@js(route('bookings.players')) + '?q=' + v)",
            "fetch(`{{ url('/members') }}/\${id}/field`)",
            'x-bind:action="@js(url(\'/bookings\')) + \'/\' + bid"',
            "fetch('https://example.org/api')",
            "fetch('//cdn.example.org/x')",
            "'/'",
        ];

        foreach ($bad as $line) {
            $this->assertTrue($this->isOffending($line), "should flag: $line");
        }
        foreach ($good as $line) {
            $this->assertFalse($this->isOffending($line), "should allow: $line");
        }
    }

    private function isOffending(string $line): bool
    {
        // fetch()/axios calls with a root-absolute literal as first argument
        if (preg_match('/\b(fetch|axios(\.[a-z]+)?)\(\s*[\'"`]\/(?!\/)/', $line)) {
            return true;
        }

        // action= / href= / src= attributes (also Alpine bindings) with a root-absolute literal
        if (preg_match('/\b(action|href|src)=["\']\s*[\'"`]?\/(?!\/)[A-Za-z0-9_\-]/', $line)) {
            return true;
        }

        // location assignments with a root-absolute literal
        if (preg_match('/location(\.href)?\s*=\s*[\'"`]\/(?!\/)[A-Za-z0-9_\-]/', $line)) {
            return true;
        }

        // resource-style literals such as '/bookings/' or "/members"
        return (bool) preg_match('/(?<!url\()(?<!asset\()[\'"`]\/(bookings|members|admin|calendar|account|my-bookings|api)\b/', $line);
    }

    /** @return list<string> */
    private function files(): array
    {
        $root = dirname(__DIR__, 2);
        $dirs = [$root.'/resources'];

        foreach (glob($root.'/app/Modules/*/resources') ?: [] as $dir) {
            $dirs[] = $dir;
        }

        $files = [];

        foreach ($dirs as $dir) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

            foreach ($it as $file) {
                if (preg_match('/\.(blade\.php|js|ts|vue)$/', $file->getFilename())) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }
}
