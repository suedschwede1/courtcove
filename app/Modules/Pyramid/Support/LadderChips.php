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

        // The category (else the title) names a ladder; the season is added when seasons differ, so a single
        // season reads "Herren | Damen" like the mockup and two chips never carry the same text.
        $seasons = array_unique(array_map(static fn (PyramidLadder $ladder): int => (int) $ladder->season, $list));
        $names = array_map(static fn (PyramidLadder $ladder): string => self::name($ladder, count($seasons) > 1), $list);
        $counts = array_count_values($names);

        $chips = [];

        foreach ($list as $i => $ladder) {
            // Still not unique (same category and season twice): the title and the id tell them apart.
            $chips[] = ['ladder' => $ladder, 'label' => $counts[$names[$i]] > 1 ? trim($ladder->title.' #'.$ladder->id) : $names[$i]];
        }

        return $chips;
    }

    private static function name(PyramidLadder $ladder, bool $withSeason): string
    {
        $name = trim((string) ($ladder->category ?? '')) ?: trim((string) $ladder->title) ?: (string) (int) $ladder->season;

        // A title that already carries the season is not given the season twice.
        if ($withSeason && ! str_contains($name, (string) (int) $ladder->season)) {
            $name .= ' · '.(int) $ladder->season;
        }

        return $name;
    }
}
