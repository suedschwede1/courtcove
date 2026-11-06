<?php

declare(strict_types=1);

namespace Tests\Unit\Css;

use JsonException;

/** Consistency check of a Vite build directory (manifest.json plus assets/). */
final class BuildManifest
{
    /** @return list<string> human-readable problems; empty when the build is consistent */
    public static function problems(string $buildDir): array
    {
        $manifestPath = $buildDir.'/manifest.json';
        if (! is_file($manifestPath)) {
            return ['manifest.json is missing'];
        }

        try {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return ['manifest.json is invalid: '.$e->getMessage()];
        }

        $referenced = [];
        foreach ((array) $manifest as $chunk) {
            if (! is_array($chunk)) {
                continue;
            }
            $files = array_merge(
                isset($chunk['file']) ? [$chunk['file']] : [],
                (array) ($chunk['css'] ?? []),
                (array) ($chunk['assets'] ?? []),
            );
            foreach ($files as $file) {
                $referenced[(string) $file] = true;
            }
        }

        $problems = [];
        foreach (array_keys($referenced) as $file) {
            if (! is_file($buildDir.'/'.$file)) {
                $problems[] = 'manifest references a missing file: '.$file;
            }
        }

        $present = glob($buildDir.'/assets/*') ?: [];
        sort($present);
        foreach ($present as $path) {
            $file = 'assets/'.basename($path);
            if (! isset($referenced[$file])) {
                $problems[] = 'stale asset not referenced by the manifest: '.$file;
            }
        }

        return $problems;
    }
}
