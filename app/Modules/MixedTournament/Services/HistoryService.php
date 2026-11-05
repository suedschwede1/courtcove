<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Services;

/**
 * Derives the partner and opponent history of an event from already
 * confirmed matches. Counting is undirected: (3,7) and (7,3) are the same key.
 * Framework-free so the mixing can be tested without a database.
 */
final class HistoryService
{
    /**
     * @param  list<array{team_a_p1: int|string, team_a_p2: int|string, team_b_p1: int|string, team_b_p2: int|string}>  $matches
     * @return array{partners: array<string, int>, opponents: array<string, int>}
     */
    public function build(array $matches): array
    {
        $partners = [];
        $opponents = [];

        foreach ($matches as $match) {
            $this->increment($partners, $match['team_a_p1'], $match['team_a_p2']);
            $this->increment($partners, $match['team_b_p1'], $match['team_b_p2']);

            foreach ([$match['team_a_p1'], $match['team_a_p2']] as $a) {
                foreach ([$match['team_b_p1'], $match['team_b_p2']] as $b) {
                    $this->increment($opponents, $a, $b);
                }
            }
        }

        return ['partners' => $partners, 'opponents' => $opponents];
    }

    public function key(int|string $a, int|string $b): string
    {
        $pair = [(int) $a, (int) $b];
        sort($pair);

        return $pair[0].':'.$pair[1];
    }

    /**
     * Mixing score for the KPI tile of the mixing tab: share of the possible
     * participant pairs that have been partners at least once (0-100%), plus
     * the raw numbers behind it.
     *
     * Coverage alone (paired/total_pairs) says little after few rounds - after
     * the first round it is necessarily low, which is no sign of poor mixing.
     * More telling is the repeat rate among the pairings already played
     * (repeated/paired): it is independent of the round count and shows whether
     * the algorithm really finds fresh pairings or keeps repeating the same
     * ones. 'rating' is based on that.
     *
     * @param  array<string, int>  $partnerCounts  as returned by build()['partners']
     * @param  list<int|string>  $participantIds  all participant ids of the event
     * @return array{total_pairs: int, paired: int, repeated: int, percent: int, repeat_rate: int, rating: string}
     */
    public function mixingScore(array $partnerCounts, array $participantIds): array
    {
        $n = count($participantIds);
        $totalPairs = $n > 1 ? (int) ($n * ($n - 1) / 2) : 0;
        $paired = 0;
        $repeated = 0;

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $key = $this->key($participantIds[$i], $participantIds[$j]);
                $count = $partnerCounts[$key] ?? 0;
                if ($count > 0) {
                    $paired++;
                }
                if ($count > 1) {
                    $repeated++;
                }
            }
        }

        $repeatRate = $paired > 0 ? $repeated / $paired * 100 : 0;

        if ($paired === 0) {
            $rating = 'none';
        } elseif ($repeatRate <= 10) {
            $rating = 'good';
        } elseif ($repeatRate <= 30) {
            $rating = 'ok';
        } else {
            $rating = 'poor';
        }

        return [
            'total_pairs' => $totalPairs,
            'paired' => $paired,
            'repeated' => $repeated,
            'percent' => $totalPairs > 0 ? (int) round($paired / $totalPairs * 100) : 0,
            'repeat_rate' => (int) round($repeatRate),
            'rating' => $rating,
        ];
    }

    /** @param  array<string, int>  $counts */
    private function increment(array &$counts, int|string $a, int|string $b): void
    {
        $key = $this->key($a, $b);
        $counts[$key] = isset($counts[$key]) ? $counts[$key] + 1 : 1;
    }
}
