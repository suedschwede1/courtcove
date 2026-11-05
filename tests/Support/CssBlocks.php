<?php

namespace Tests\Support;

/** Brace-balanced CSS block helpers for stylesheet tests. */
final class CssBlocks
{
    /** Returns the body after "$opener {" (empty string if absent). */
    public static function body(string $css, string $opener): string
    {
        $start = strpos($css, $opener.' {');
        if ($start === false) {
            return '';
        }
        [$body] = self::scan($css, $start + strlen($opener) + 2);

        return $body;
    }

    /** Returns $css with every "$opener {...}" block removed. */
    public static function without(string $css, string $opener): string
    {
        while (($start = strpos($css, $opener.' {')) !== false) {
            [, $end] = self::scan($css, $start + strlen($opener) + 2);
            $css = substr($css, 0, $start).substr($css, $end + 1);
        }

        return $css;
    }

    /** Returns $css with every "@layer name {...}" block removed. */
    public static function withoutLayers(string $css): string
    {
        while (preg_match('/@layer[^{;]*\{/', $css, $m, PREG_OFFSET_CAPTURE) === 1) {
            $start = $m[0][1];
            [, $end] = self::scan($css, $start + strlen($m[0][0]));
            $css = substr($css, 0, $start).substr($css, $end + 1);
        }

        return $css;
    }

    /** @return array{string, int} body and index of the closing brace */
    private static function scan(string $css, int $i): array
    {
        $depth = 1;
        $body = '';
        $n = strlen($css);
        for (; $i < $n; $i++) {
            $c = $css[$i];
            if ($c === '{') {
                $depth++;
            } elseif ($c === '}' && --$depth === 0) {
                break;
            }
            $body .= $c;
        }

        return [$body, $i];
    }
}
