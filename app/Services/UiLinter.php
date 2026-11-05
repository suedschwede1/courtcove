<?php

declare(strict_types=1);

namespace App\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

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

    /** @return list<array{rule: string, file: string, line: int, message: string}> */
    public function run(): array
    {
        $findings = [];

        foreach ($this->files() as $file) {
            $relativePath = $this->relativePath($file);
            $type = str_ends_with($file, '.css') ? 'css' : 'blade';
            $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];

            foreach ($this->rules as $rule) {
                if (($rule['files'] ?? 'blade') !== $type) {
                    continue;
                }
                if ($this->isExcluded($relativePath, $rule['exclude'] ?? [])) {
                    continue;
                }

                if ($rule['context'] ?? false) {
                    $findings = array_merge($findings, $this->applyContextRule($rule, $lines, $relativePath));
                } elseif ($rule['hex'] ?? false) {
                    $findings = array_merge($findings, $this->applyHexRule($rule, $lines, $relativePath, basename($file)));
                } else {
                    $findings = array_merge($findings, $this->applyLineRule($rule, $lines, $relativePath));
                }
            }
        }

        return $findings;
    }

    private function relativePath(string $file): string
    {
        $normalized = str_replace('\\', '/', $file);
        $base = str_replace('\\', '/', $this->basePath).'/';

        return str_starts_with($normalized, $base) ? substr($normalized, strlen($base)) : $normalized;
    }

    /** @return list<string> */
    private function files(): array
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

        sort($files);

        return $files;
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
     * @return list<array{rule: string, file: string, line: int, message: string}>
     */
    private function applyLineRule(array $rule, array $lines, string $relativePath): array
    {
        $findings = [];
        foreach ($lines as $i => $line) {
            if (preg_match($rule['pattern'], $line)) {
                $findings[] = [
                    'rule' => $rule['id'],
                    'file' => $relativePath,
                    'line' => $i + 1,
                    'message' => $rule['warn'],
                ];
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  list<string>  $lines
     * @return list<array{rule: string, file: string, line: int, message: string}>
     */
    private function applyHexRule(array $rule, array $lines, string $relativePath, string $basename): array
    {
        $findings = [];
        $allowed = array_map('strtolower', $rule['allowed'] ?? []);

        if (isset($rule['allowed_only_in']) && ! in_array($basename, $rule['allowed_only_in'], true)) {
            $allowed = [];
        }

        foreach ($lines as $i => $line) {
            preg_match_all($rule['pattern'], $line, $matches);
            foreach ($matches[0] as $hex) {
                if (! in_array(strtolower($hex), $allowed, true)) {
                    $findings[] = [
                        'rule' => $rule['id'],
                        'file' => $relativePath,
                        'line' => $i + 1,
                        'message' => sprintf($rule['warn'], $hex),
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  list<string>  $lines
     * @return list<array{rule: string, file: string, line: int, message: string}>
     */
    private function applyContextRule(array $rule, array $lines, string $relativePath): array
    {
        $findings = [];
        $require = $rule['context_require'] ?? '';

        foreach ($lines as $i => $line) {
            if (! preg_match($rule['pattern'], $line)) {
                continue;
            }

            $start = max(0, $i - 3);
            $end = min(count($lines) - 1, $i + 3);
            $context = implode(' ', array_slice($lines, $start, $end - $start + 1));

            if (! str_contains($context, $require)) {
                $findings[] = [
                    'rule' => $rule['id'],
                    'file' => $relativePath,
                    'line' => $i + 1,
                    'message' => $rule['warn'],
                ];
            }
        }

        return $findings;
    }
}
