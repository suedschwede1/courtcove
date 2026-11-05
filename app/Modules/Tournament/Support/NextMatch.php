<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Support;

use App\Modules\Tournament\Services\TournamentManager;

/**
 * The next match of a player (old IndexController::indexAction): his unplayed matches, those without a
 * date first, then by date; equal dates keep the manager's order. Deviation from ZF2: a KO match whose
 * opponent is not known yet is skipped (ZF2 offered "Gegner offen" with a date button).
 */
final class NextMatch
{
    /** @return array<string, mixed>|null */
    public static function of(TournamentManager $m, int $tid, int $tpid): ?array
    {
        $candidates = [];

        foreach ($m->playerMatches($tid, $tpid) as $match) {
            if ($m->matchState($match) !== 'played' && $match['tpid_a'] && $match['tpid_b']) {
                $candidates[] = $match;
            }
        }

        usort($candidates, function (array $a, array $b): int {
            if ($a['played'] === $b['played']) {
                return 0;
            }

            if (! $a['played']) {
                return -1;
            }

            if (! $b['played']) {
                return 1;
            }

            return strcmp((string) $a['played'], (string) $b['played']);
        });

        return $candidates[0] ?? null;
    }
}
