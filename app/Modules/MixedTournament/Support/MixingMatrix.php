<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Support;

use App\Modules\MixedTournament\Services\HistoryService;

/**
 * The Durchmischung matrix: per participant pair the number of shared partnerships over the confirmed
 * and completed rounds (partner counts as in ZF2, not partner-or-opponent as the mockup says), the
 * colour level of each cell and the matches played per participant.
 *
 * Levels: 0 = never, 1 = once, 2 = twice, 3 = three times or more. The self cell has no count.
 */
final class MixingMatrix
{
    private const SLOTS = ['team_a_p1', 'team_a_p2', 'team_b_p1', 'team_b_p2'];

    public function __construct(private readonly HistoryService $history) {}

    /**
     * @param  array<int, string>  $participants  participant id => label, in display order
     * @param  array<string, int>  $partnerCounts  HistoryService::build()['partners']
     * @param  list<array{team_a_p1: int|string, team_a_p2: int|string, team_b_p1: int|string, team_b_p2: int|string}>  $pastMatches
     * @return array{heads: list<string>, rows: list<array{id: int, label: string, cells: list<array{count: ?int, level: int, partner: string}>}>, matches_played: array<int, int>}
     */
    public function build(array $participants, array $partnerCounts, array $pastMatches): array
    {
        $matchesPlayed = array_fill_keys(array_map('intval', array_keys($participants)), 0);
        foreach ($pastMatches as $match) {
            foreach (self::SLOTS as $slot) {
                $pid = (int) $match[$slot];
                if (isset($matchesPlayed[$pid])) {
                    $matchesPlayed[$pid]++;
                }
            }
        }

        $heads = [];
        foreach ($participants as $label) {
            $heads[] = self::head($label);
        }

        $rows = [];
        foreach ($participants as $a => $labelA) {
            $cells = [];
            foreach ($participants as $b => $labelB) {
                if ((int) $a === (int) $b) {
                    $cells[] = ['count' => null, 'level' => 0, 'partner' => $labelB];

                    continue;
                }
                $count = $partnerCounts[$this->history->key($a, $b)] ?? 0;
                $cells[] = ['count' => $count, 'level' => min($count, 3), 'partner' => $labelB];
            }
            $rows[] = ['id' => (int) $a, 'label' => $labelA, 'cells' => $cells];
        }

        return ['heads' => $heads, 'rows' => $rows, 'matches_played' => $matchesPlayed];
    }

    /** Column head: the first three letters of the last word ("Spieler 07" -> "07", "Anna Beispiel" -> "Bei"). */
    public static function head(string $label): string
    {
        $words = preg_split('/\s+/u', trim($label), -1, PREG_SPLIT_NO_EMPTY) ?: [''];

        return mb_substr((string) end($words), 0, 3);
    }
}
