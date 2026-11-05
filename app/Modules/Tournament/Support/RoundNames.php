<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Support;

/**
 * Translated round names of a bracket (FormatService::rounds() returns the German names of ZF2 as data;
 * the pages show these translated labels instead).
 */
final class RoundNames
{
    /** @return list<string> one name per round, first round first */
    public static function for(int $size): array
    {
        $out = [];
        $step = $size;

        while ($step > 1) {
            $out[] = in_array($step, [2, 4, 8, 16], true)
                ? __('tournament::common.rounds.'.$step)
                : __('tournament::common.rounds.last', ['count' => $step]);
            $step = intdiv($step, 2);
        }

        return $out;
    }
}
