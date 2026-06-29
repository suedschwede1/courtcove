<?php

declare(strict_types=1);

namespace App\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class UiLinter
{
    /** @param array<int, array<string, mixed>> $rules */
    public function __construct(
        private readonly string $viewsPath,
        private readonly array $rules,
    ) {}

    /** @return list<array{rule: string, file: string, line: int, message: string}> */
    public function run(): array
    {
        $findings = [];

        foreach ($this->bladeFiles() as $file) {
            $relativePath = str_replace($this->viewsPath . DIRECTORY_SEPARATOR, '', $file);
            $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];

            foreach ($this->rules as $rule) {
                if ($this->isExcluded($relativePath, $rule['exclude'])) {
                    continue;
                }

                if ($rule['context'] ?? false) {
                    $findings = array_merge($findings, $this->applyContextRule($rule, $lines, $relativePath));
                } elseif ($rule['hex'] ?? false) {
                    $findings = array_merge($findings, $this->applyHexRule($rule, $lines, $relativePath));
                } else {
                    $findings = array_merge($findings, $this->applyLineRule($rule, $lines, $relativePath));
                }
            }
        }

        return $findings;
    }

    /** @return list<string> */
    private function bladeFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->viewsPath));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /** @param list<string> $excludes */
    private function isExcluded(string $relativePath, array $excludes): bool
    {
        foreach ($excludes as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<string>         $lines
     * @return list<array{rule: string, file: string, line: int, message: string}>
     */
    private function applyLineRule(array $rule, array $lines, string $relativePath): array
    {
        $findings = [];
        foreach ($lines as $i => $line) {
            if (preg_match($rule['pattern'], $line)) {
                $findings[] = [
                    'rule'    => $rule['id'],
                    'file'    => $relativePath,
                    'line'    => $i + 1,
                    'message' => $rule['warn'],
                ];
            }
        }
        return $findings;
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<string>         $lines
     * @return list<array{rule: string, file: string, line: int, message: string}>
     */
    private function applyHexRule(array $rule, array $lines, string $relativePath): array
    {
        $findings = [];
        $allowed  = array_map('strtolower', UiRules::ALLOWED_HEX);

        foreach ($lines as $i => $line) {
            preg_match_all($rule['pattern'], $line, $matches);
            foreach ($matches[0] as $hex) {
                if (!in_array(strtolower($hex), $allowed, true)) {
                    $findings[] = [
                        'rule'    => $rule['id'],
                        'file'    => $relativePath,
                        'line'    => $i + 1,
                        'message' => sprintf($rule['warn'], $hex),
                    ];
                }
            }
        }
        return $findings;
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<string>         $lines
     * @return list<array{rule: string, file: string, line: int, message: string}>
     */
    private function applyContextRule(array $rule, array $lines, string $relativePath): array
    {
        $findings = [];
        $require  = $rule['context_require'];

        foreach ($lines as $i => $line) {
            if (!preg_match($rule['pattern'], $line)) {
                continue;
            }

            $start   = max(0, $i - 3);
            $end     = min(count($lines) - 1, $i + 3);
            $context = implode(' ', array_slice($lines, $start, $end - $start + 1));

            if (!str_contains($context, $require)) {
                $findings[] = [
                    'rule'    => $rule['id'],
                    'file'    => $relativePath,
                    'line'    => $i + 1,
                    'message' => $rule['warn'],
                ];
            }
        }
        return $findings;
    }
}
