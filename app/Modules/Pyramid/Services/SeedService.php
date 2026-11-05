<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Services;

/**
 * Initial seeding of the starting field. Free of database code so the order can
 * be checked before it is written - a wrong seeding cannot be corrected cleanly
 * after the first challenge.
 *
 * Ported unchanged from the old application (Pyramid\Service\SeedService).
 */
final class SeedService
{
    /**
     * @param  list<array<string, mixed>>  $players  rows with 'label' and 'itn' (null allowed)
     * @param  float|string  $itnDefault  rating for participants without a classification
     * @return list<array<string, mixed>> the same rows plus 'itn_rated' and 'position', ascending by
     *                                    itn_rated, ties by label
     */
    public function seed(array $players, float|string $itnDefault): array
    {
        $itnDefault = (float) $itnDefault;
        $field = [];

        foreach ($players as $player) {
            $player['itn'] = $player['itn'] === null || $player['itn'] === ''
                ? null
                : (float) $player['itn'];
            $player['itn_rated'] = $player['itn'] === null ? $itnDefault : $player['itn'];
            $field[] = $player;
        }

        usort($field, function (array $a, array $b): int {
            if ($a['itn_rated'] === $b['itn_rated']) {
                // Design decision 3: fixed byte collation. The old code used strcoll(),
                // and ZF2 never sets a locale, so it compared bytes (C locale).
                return strcmp((string) $a['label'], (string) $b['label']);
            }

            return $a['itn_rated'] < $b['itn_rated'] ? -1 : 1;
        });

        $position = 1;

        foreach ($field as $index => $player) {
            $field[$index]['position'] = $position++;
        }

        return $field;
    }
}
