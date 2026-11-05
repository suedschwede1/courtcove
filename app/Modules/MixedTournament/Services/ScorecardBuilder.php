<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Services;

/**
 * Data of the printable scorecards (old scorecard.phtml / scorecard-mexicano.phtml
 * without the markup): one card per group, in print order, with the page-break
 * flag per wave.
 *
 * The wave number itself is computed (it steers order and page breaks) but is
 * NEVER part of a card: nobody at the court cares about it.
 *
 * `$labels` maps participant id => display name, `$genders` participant id =>
 * the already translated gender letter (D / H / ?); a missing id becomes '?'.
 * `group_no` of a card is the stored (zero-based) group number; the view shows
 * it plus one.
 */
final class ScorecardBuilder
{
    private const SLOTS = ['team_a_p1', 'team_a_p2', 'team_b_p1', 'team_b_p2'];

    public function __construct(private readonly WaveService $waves) {}

    /**
     * @param  list<array<string, mixed>>  $matches  DB rows (group_no, match_no, team_a_p1..team_b_p2, is_extra), ordered
     * @param  array<int|string, string>  $labels
     * @param  array<int|string, string>  $genders
     * @return list<array{group_no: int, is_extra: bool, players: list<array{no: int, label: string, gender: string}>, pairings: list<array{no: int, team_a: string, team_b: string}>, ends_wave: bool}>
     */
    public function americano(array $matches, array $labels, array $genders, int $courtCount): array
    {
        $byGroup = $this->byGroup($matches);
        $waveByGroup = $this->waves->americanoWaves($byGroup, max(1, $courtCount));

        // Sorted by wave so the stack of one wave lies together - group
        // numbers alone do not do that, because the wave plan sorts by group
        // size and pushes the extra group to the back.
        $order = array_keys($byGroup);
        usort($order, function (int $a, int $b) use ($waveByGroup): int {
            if ($waveByGroup[$a] !== $waveByGroup[$b]) {
                return $waveByGroup[$a] - $waveByGroup[$b];
            }

            return $a - $b;
        });

        $cards = [];

        foreach ($order as $position => $groupNo) {
            $groupMatches = $byGroup[$groupNo];

            // Players in the order of their first appearance, distinct,
            // because the same people stand in several matches of the group.
            $groupPlayers = [];
            foreach ($groupMatches as $match) {
                foreach (self::SLOTS as $slot) {
                    $pid = (int) $match[$slot];
                    if (! in_array($pid, $groupPlayers, true)) {
                        $groupPlayers[] = $pid;
                    }
                }
            }

            $pairings = [];
            foreach ($groupMatches as $i => $match) {
                $pairings[] = [
                    'no' => $i + 1,
                    'team_a' => $this->team($match['team_a_p1'], $match['team_a_p2'], $labels),
                    'team_b' => $this->team($match['team_b_p1'], $match['team_b_p2'], $labels),
                ];
            }

            $next = $order[$position + 1] ?? null;

            $cards[] = [
                'group_no' => $groupNo,
                'is_extra' => ! empty($groupMatches[0]['is_extra']),
                'players' => $this->players($groupPlayers, $labels, $genders),
                'pairings' => $pairings,
                'ends_wave' => $next !== null && $waveByGroup[$next] !== $waveByGroup[$groupNo],
            ];
        }

        return $cards;
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     * @param  array<int|string, string>  $labels
     * @param  array<int|string, string>  $genders
     * @return list<array{group_no: int, court: int, players: list<array{no: int, label: string, gender: string}>, team_a: string, team_b: string, ends_wave: bool}>
     */
    public function mexicano(array $matches, array $labels, array $genders, int $courtCount): array
    {
        $byGroup = $this->byGroup($matches);
        $slots = $this->waves->mexicanoSlots($byGroup, max(1, $courtCount));
        $positions = array_keys($byGroup);
        $cards = [];

        foreach ($positions as $position => $groupNo) {
            $next = $positions[$position + 1] ?? null;
            $endsWave = $next !== null && $slots[$next]['wave'] !== $slots[$groupNo]['wave'];

            foreach ($byGroup[$groupNo] as $match) {
                $cards[] = [
                    'group_no' => $groupNo,
                    'court' => $slots[$groupNo]['court'],
                    'players' => $this->players(array_map(fn (string $slot): int => (int) $match[$slot], self::SLOTS), $labels, $genders),
                    'team_a' => $this->team($match['team_a_p1'], $match['team_a_p2'], $labels),
                    'team_b' => $this->team($match['team_b_p1'], $match['team_b_p2'], $labels),
                    'ends_wave' => $endsWave,
                ];
            }
        }

        return $cards;
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     * @return array<int, list<array<string, mixed>>>
     */
    private function byGroup(array $matches): array
    {
        $byGroup = [];
        foreach ($matches as $match) {
            $byGroup[(int) $match['group_no']][] = $match;
        }
        ksort($byGroup);

        return $byGroup;
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int|string, string>  $labels
     * @param  array<int|string, string>  $genders
     * @return list<array{no: int, label: string, gender: string}>
     */
    private function players(array $ids, array $labels, array $genders): array
    {
        $players = [];
        foreach ($ids as $i => $pid) {
            $players[] = ['no' => $i + 1, 'label' => $labels[$pid] ?? '?', 'gender' => $genders[$pid] ?? '?'];
        }

        return $players;
    }

    /** @param  array<int|string, string>  $labels */
    private function team(int|string $first, int|string $second, array $labels): string
    {
        return ($labels[(int) $first] ?? '?').' & '.($labels[(int) $second] ?? '?');
    }
}
