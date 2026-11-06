<?php

declare(strict_types=1);

namespace App\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Source checks complement, but cannot replace, a rendered design review.
 *
 * Every finding carries a line-independent fingerprint (file, rule, normalized
 * source line, matched text) so the legacy baseline survives line shifts but not
 * a changed or additional violation.
 */
class UiLinter
{
    /** @var list<string> */
    private readonly array $roots;

    private readonly string $basePath;

    /**
     * @param  string|list<string>  $roots
     * @param  array<int, array<string, mixed>>  $rules
     */
    public function __construct(string|array $roots, private readonly array $rules, ?string $basePath = null)
    {
        $this->roots = (array) $roots;
        $this->basePath = rtrim($basePath ?? (is_string($roots) ? $roots : base_path()), '/\\');
    }

    /** @return list<array{rule: string, file: string, line: int, message: string, source: string, fingerprint: string}> */
    public function run(): array
    {
        $findings = [];

        foreach ($this->files() as $file) {
            $relativePath = $this->relativePath($file);
            $type = str_ends_with($file, '.css') ? 'css' : 'blade';
            $source = file_get_contents($file);
            if ($source === false) {
                throw new RuntimeException('Cannot read UI source: '.$relativePath);
            }
            $rawLines = explode("\n", str_replace("\r\n", "\n", $source));
            $lines = explode("\n", $this->blankComments(str_replace("\r\n", "\n", $source), $type));

            foreach ($this->rules as $rule) {
                if (($rule['files'] ?? 'blade') !== $type) {
                    continue;
                }
                if ($this->isExcluded($relativePath, $rule['exclude'] ?? [])) {
                    continue;
                }

                $hits = match (true) {
                    (bool) ($rule['context'] ?? false) => $this->applyContextRule($rule, $lines),
                    (bool) ($rule['hex'] ?? false) => $this->applyHexRule($rule, $lines, basename($file)),
                    default => $this->applyLineRule($rule, $lines, basename($file)),
                };

                foreach ($hits as [$index, $match, $message]) {
                    $snippet = trim((string) preg_replace('/\s+/', ' ', $rawLines[$index] ?? ''));
                    $findings[] = [
                        'rule' => (string) $rule['id'],
                        'file' => $relativePath,
                        'line' => $index + 1,
                        'message' => $message,
                        'source' => $snippet,
                        'fingerprint' => hash('sha256', $relativePath."\0".$rule['id']."\0".$snippet."\0".$match),
                    ];
                }
            }
        }

        return $findings;
    }

    /** @return list<string> */
    public function files(): array
    {
        $files = [];

        foreach ($this->roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                $name = $file->getFilename();
                if ($file->isFile() && (str_ends_with($name, '.blade.php') || str_ends_with($name, '.css'))) {
                    $files[] = $file->getPathname();
                }
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    private function relativePath(string $file): string
    {
        $normalized = str_replace('\\', '/', $file);
        $base = str_replace('\\', '/', $this->basePath).'/';

        return str_starts_with($normalized, $base) ? substr($normalized, strlen($base)) : $normalized;
    }

    /**
     * Comments never count as findings. They are blanked in place so offsets and
     * line numbers stay intact. Block comments are only stripped from CSS: in Blade
     * a path glob such as is('admin/*') would otherwise open a false comment.
     */
    private function blankComments(string $source, string $type): string
    {
        $pattern = $type === 'css' ? '/\/\*.*?\*\//s' : '/{{--.*?--}}|<!--.*?-->/s';

        return preg_replace_callback(
            $pattern,
            static fn (array $m): string => (string) preg_replace('/[^\n]/', ' ', $m[0]),
            $source,
        ) ?? $source;
    }

    /** @param list<string> $excludes */
    private function isExcluded(string $relativePath, array $excludes): bool
    {
        foreach ($excludes as $segment) {
            if (str_contains('/'.$relativePath, '/'.$segment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  list<string>  $lines
     * @return list<array{int, string, string}> line index, matched text, message
     */
    private function applyLineRule(array $rule, array $lines, string $basename): array
    {
        // Optional allowance for whole lines, e.g. custom property declarations in the token sheet.
        $allowLine = $rule['allow_line'] ?? null;
        if ($allowLine !== null && isset($rule['allow_line_only_in']) && ! in_array($basename, $rule['allow_line_only_in'], true)) {
            $allowLine = null;
        }

        $hits = [];
        foreach ($lines as $i => $line) {
            if ($allowLine !== null && preg_match($allowLine, $line)) {
                continue;
            }
            if (preg_match($rule['pattern'], $line, $m)) {
                $hits[] = [$i, $m[0], (string) $rule['warn']];
            }
        }

        return $hits;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  list<string>  $lines
     * @return list<array{int, string, string}>
     */
    private function applyHexRule(array $rule, array $lines, string $basename): array
    {
        $hits = [];
        $allowed = array_map('strtolower', $rule['allowed'] ?? []);

        if (isset($rule['allowed_only_in']) && ! in_array($basename, $rule['allowed_only_in'], true)) {
            $allowed = [];
        }

        foreach ($lines as $i => $line) {
            preg_match_all($rule['pattern'], $line, $matches);
            foreach ($matches[0] as $hex) {
                if (! in_array(strtolower($hex), $allowed, true)) {
                    $hits[] = [$i, $hex, sprintf($rule['warn'], $hex)];
                }
            }
        }

        return $hits;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  list<string>  $lines
     * @return list<array{int, string, string}>
     */
    private function applyContextRule(array $rule, array $lines): array
    {
        $hits = [];
        $require = $rule['context_require'] ?? '';

        foreach ($lines as $i => $line) {
            if (! preg_match($rule['pattern'], $line, $m)) {
                continue;
            }

            $start = max(0, $i - 3);
            $end = min(count($lines) - 1, $i + 3);
            $context = implode(' ', array_slice($lines, $start, $end - $start + 1));

            if (! str_contains($context, $require)) {
                $hits[] = [$i, $m[0], (string) $rule['warn']];
            }
        }

        return $hits;
    }
}
