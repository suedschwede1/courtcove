<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Helpers for testing front controllers whose public/ directory is exposed through a symlink.
 *
 * Under Apache + PHP-FPM, __DIR__ of the served script is the UNRESOLVED link path. PHP CLI cannot
 * reproduce that: it resolves symlinks for the primary script and for included files alike, so
 * __DIR__ is always the real path there (verified with require/include through the link, chdir and
 * include_path variants). To simulate the FPM behaviour, the magic constant __DIR__ is replaced by
 * the link path in a copy of the script that is kept next to the original (same real directory);
 * realpath() still resolves the link exactly as it does in FPM.
 */
trait SimulatesSymlinkedDocroot
{
    /**
     * Writes a copy of $script whose __DIR__ evaluates to $linkDir; returns the copy's path.
     */
    private function copyWithUnresolvedDir(string $script, string $linkDir, string $copyPath): string
    {
        $source = '';

        foreach (token_get_all((string) file_get_contents($script)) as $token) {
            $source .= is_array($token) && $token[0] === T_DIR
                ? var_export($linkDir, true)
                : (is_array($token) ? $token[1] : $token);
        }

        file_put_contents($copyPath, $source);

        return $copyPath;
    }
}
