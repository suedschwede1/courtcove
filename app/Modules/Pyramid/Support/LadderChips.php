<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Support;

use App\Modules\Pyramid\Models\PyramidLadder;

/**
 * The ladder switcher of the page header: newest season first, then category, then id (as in ZF2).
 */
final class LadderChips
{
    /**
     * @param  iterable<PyramidLadder>  $ladders
     * @return list<array{ladder: PyramidLadder, label: string}>
     */
    public static function for(iterable $ladders): array
    {
        $list = [];

        foreach ($ladders as $ladder) {
            $list[] = $ladder;
        }

        usort($list, static function (PyramidLadder $a, PyramidLadder $b): int {
            if ((int) $a->season !== (int) $b->season) {
                return (int) $b->season <=> (int) $a->season;
            }

            $byCategory = strcmp((string) $a->category, (string) $b->category);

            return $byCategory !== 0 ? $byCategory : ((int) $a->id <=> (int) $b->id);
        });

        return array_map(static fn (PyramidLadder $ladder): array => [
            'ladder' => $ladder,
            'label' => (int) $ladder->season.($ladder->category === null ? '' : ' · '.$ladder->category),
        ], $list);
    }
}
