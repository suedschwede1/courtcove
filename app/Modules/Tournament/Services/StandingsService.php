<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use InvalidArgumentException;

/**
 * Ranking of a group table (port of the ZF2 StandingsService, repo version).
 *
 * Criteria in this order:
 *
 *   1. points          2 per win, minus points_adjust
 *   2. internal table  wins ONLY from the matches among the tied players
 *   3. set difference  range according to $tiebreakScope
 *   4. game difference same range
 *   5. lot             flagged as needs_lot, never silently decided
 *
 * On the range: in groups of three the distinction "among the tied only" versus "all matches" has no
 * effect (the win distributions (2,1,0) and (1,1,1) are the only ones possible). It starts to matter
 * in groups of four with distributions such as (2,2,1,1). The default is 'tied', in line with the
 * regional federation rules: direct duels count more than a high set ratio against the group's last.
 *
 * Framework free, plain arrays, exactly like the old code.
 */
final class StandingsService
{
    private int $pointsPerWin = 2;

    /** 'tied' = only matches among the tied players, 'all' = all group matches */
    private string $tiebreakScope = 'tied';

    /** 'drop_last' | 'points' | 'per_match' | 'lot': comparison of the group runners-up */
    private string $runnerUpMode = 'drop_last';

    public function setPointsPerWin(int $p): self
    {
        $this->pointsPerWin = $p;

        return $this;
    }

    public function setTiebreakScope(string $s): self
    {
        $this->tiebreakScope = ($s === 'all') ? 'all' : 'tied';

        return $this;
    }

    public function setRunnerUpMode(string $m): self
    {
        $this->runnerUpMode = in_array($m, ['drop_last', 'points', 'per_match', 'lot'], true)
            ? $m
            : 'drop_last';

        return $this;
    }

    /**
     * @param  list<array<string, mixed>>  $players  each: tpid, label, seed, points_adjust
     * @param  list<array<string, mixed>>  $matches  each: tpid_a, tpid_b, winner, sets_a, sets_b, games_a,
     *                                               games_b; finished matches only
     * @return list<array<string, mixed>> ranked; each row additionally has rank, points, wins, played,
     *                                    set_diff, game_diff, needs_lot
     */
    public function rank(array $players, array $matches): array
    {
        $rows = [];

        foreach ($players as $player) {
            $tpid = (int) $player['tpid'];

            $stats = $this->aggregate($tpid, $matches);

            $rows[$tpid] = array_merge($player, [
                'tpid' => $tpid,
                'wins' => $stats['wins'],
                'losses' => $stats['losses'],
                'played' => $stats['played'],
                'points' => $stats['wins'] * $this->pointsPerWin - (int) (isset($player['points_adjust']) ? $player['points_adjust'] : 0),
                'set_diff' => $stats['set_diff'],
                'game_diff' => $stats['game_diff'],
                'needs_lot' => false,
            ]);
        }

        // Stage 1: points descending, provisionally by seed on a tie so that the order stays deterministic.

        $ordered = array_values($rows);

        usort($ordered, function ($a, $b) {
            if ($a['points'] != $b['points']) {
                return $b['points'] - $a['points'];
            }

            return $this->seedOf($a) - $this->seedOf($b);
        });

        // Stages 2 to 5 inside the blocks of equal points.

        $result = [];

        foreach ($this->blocksByPoints($ordered) as $block) {

            if (count($block) < 2) {
                $result[] = $block[0];

                continue;
            }

            $tpids = [];

            foreach ($block as $row) {
                $tpids[] = $row['tpid'];
            }

            // Range for stages 2 to 4.

            $scoped = ($this->tiebreakScope === 'all')
                ? $matches
                : $this->matchesAmong($tpids, $matches);

            $internal = [];

            foreach ($tpids as $tpid) {
                $internal[$tpid] = $this->aggregate($tpid, $this->matchesAmong($tpids, $matches));
            }

            $scopedStats = [];

            foreach ($tpids as $tpid) {
                $scopedStats[$tpid] = $this->aggregate($tpid, $scoped);
            }

            usort($block, function ($a, $b) use ($internal, $scopedStats) {
                $ia = $internal[$a['tpid']];
                $ib = $internal[$b['tpid']];

                // Stage 2: internal table
                if ($ia['wins'] != $ib['wins']) {
                    return $ib['wins'] - $ia['wins'];
                }

                $sa = $scopedStats[$a['tpid']];
                $sb = $scopedStats[$b['tpid']];

                // Stage 3: set difference
                if ($sa['set_diff'] != $sb['set_diff']) {
                    return $sb['set_diff'] - $sa['set_diff'];
                }

                // Stage 4: game difference
                if ($sa['game_diff'] != $sb['game_diff']) {
                    return $sb['game_diff'] - $sa['game_diff'];
                }

                // Stage 5: undecidable; the order stays stable by seed, the flag is set below.
                return $this->seedOf($a) - $this->seedOf($b);
            });

            // Record which criterion separated each row from its neighbour. Without it the display
            // cannot explain the order, and a table in which somebody with the worse game difference
            // stands higher looks like an error.
            foreach ($block as $index => $row) {
                $block[$index]['tied_block'] = true;
                $block[$index]['decided_by'] = null;
            }

            for ($i = 0; $i < count($block); $i++) {
                $other = ($i === 0) ? 1 : $i - 1;

                if (! isset($block[$other])) {
                    continue;
                }

                $a = $block[$i]['tpid'];
                $b = $block[$other]['tpid'];

                if ($internal[$a]['wins'] != $internal[$b]['wins']) {
                    $block[$i]['decided_by'] = 'head_to_head';
                } elseif ($scopedStats[$a]['set_diff'] != $scopedStats[$b]['set_diff']) {
                    $block[$i]['decided_by'] = 'set_diff';
                } elseif ($scopedStats[$a]['game_diff'] != $scopedStats[$b]['game_diff']) {
                    $block[$i]['decided_by'] = 'game_diff';
                } else {
                    $block[$i]['decided_by'] = 'lot';
                }
            }

            // Hand out the scored values: the totals in set_diff and game_diff can differ from them
            // when tiebreak_scope is 'tied'.

            foreach ($block as $index => $row) {
                $tpid = $row['tpid'];

                $block[$index]['scored_h2h'] = $internal[$tpid]['wins'];
                $block[$index]['scored_set_diff'] = $scopedStats[$tpid]['set_diff'];
                $block[$index]['scored_game_diff'] = $scopedStats[$tpid]['game_diff'];
            }

            // Flag remaining ties so that the display asks for a lot instead of asserting an arbitrary order.

            foreach ($block as $index => $row) {
                $tpid = $row['tpid'];

                foreach ($block as $otherIndex => $other) {
                    if ($index === $otherIndex) {
                        continue;
                    }

                    $o = $other['tpid'];

                    if ($internal[$tpid]['wins'] == $internal[$o]['wins']
                        && $scopedStats[$tpid]['set_diff'] == $scopedStats[$o]['set_diff']
                        && $scopedStats[$tpid]['game_diff'] == $scopedStats[$o]['game_diff']) {

                        $block[$index]['needs_lot'] = true;
                        break;
                    }
                }
            }

            foreach ($block as $row) {
                $result[] = $row;
            }
        }

        // Assign ranks and defaults for rows without a tie.

        foreach ($result as $index => $row) {
            $result[$index]['rank'] = $index + 1;

            if (! isset($result[$index]['tied_block'])) {
                $result[$index]['tied_block'] = false;
                $result[$index]['decided_by'] = 'points';
                $result[$index]['scored_h2h'] = null;
                $result[$index]['scored_set_diff'] = $row['set_diff'];
                $result[$index]['scored_game_diff'] = $row['game_diff'];
            }
        }

        return $result;
    }

    /**
     * Comparison of the group runners-up across group borders.
     *
     * In the default mode drop_last the match against the group's last is dropped in groups of four, so
     * every candidate record rests on two matches. Points, set and game difference decide afterwards; a
     * complete tie is flagged needs_lot.
     *
     * @param  array<array<string, mixed>>  $seconds  each: tpid, label, grp, points, wins, played, set_diff,
     *                                                game_diff (taken over from rank())
     * @param  array<string, array<int, array<string, mixed>>>  $groupStandings  complete tables by group
     * @param  list<array<string, mixed>>  $matches  all finished group matches
     * @return list<array<string, mixed>> ranked, with rank and needs_lot
     */
    public function rankRunnersUp(array $seconds, array $groupStandings = [], array $matches = []): array
    {
        $rows = array_values($seconds);

        // In lot mode nothing is ranked here (the order comes from the Draw module). All candidates are
        // flagged so that the display asks for a draw instead of asserting a ranking.

        if ($this->runnerUpMode === 'lot') {
            foreach ($rows as $index => $row) {
                $rows[$index]['rank'] = $index + 1;
                $rows[$index]['needs_lot'] = true;
            }

            return $rows;
        }

        if ($this->runnerUpMode === 'drop_last') {
            $rows = $this->recordsWithoutLastOpponent($rows, $groupStandings, $matches);
        }

        $perMatch = ($this->runnerUpMode === 'per_match');

        usort($rows, function ($a, $b) use ($perMatch) {
            foreach (['points', 'set_diff', 'game_diff'] as $key) {
                $va = $this->norm($a, $key, $perMatch);
                $vb = $this->norm($b, $key, $perMatch);

                if (abs($va - $vb) > 0.0001) {
                    return ($vb > $va) ? 1 : -1;
                }
            }

            return $this->seedOf($a) - $this->seedOf($b);
        });

        foreach ($rows as $index => $row) {
            $rows[$index]['rank'] = $index + 1;
            $rows[$index]['needs_lot'] = false;
        }

        // Flag only neighbouring ties. The border between place 2 and 3 matters, the promotion hangs on it.

        for ($i = 0; $i < count($rows) - 1; $i++) {
            if ($this->runnerUpEqual($rows[$i], $rows[$i + 1], $perMatch)) {
                $rows[$i]['needs_lot'] = true;
                $rows[$i + 1]['needs_lot'] = true;
            }
        }

        return $rows;
    }

    /**
     * Drops, in groups with more than three players, the candidate's match against the group's last
     * player, so every comparison record rests on two matches. An explicit points_adjust is kept.
     *
     * @param  array<array<string, mixed>>  $rows
     * @param  array<string, array<int, array<string, mixed>>>  $groupStandings
     * @param  list<array<string, mixed>>  $matches
     * @return list<array<string, mixed>>
     */
    private function recordsWithoutLastOpponent(array $rows, array $groupStandings, array $matches): array
    {
        foreach ($rows as $index => $row) {
            $group = isset($row['grp']) ? $row['grp'] : null;

            if (! $group || ! isset($groupStandings[$group])) {
                throw new InvalidArgumentException(
                    'drop_last requires complete standings for every candidate group'
                );
            }

            $table = array_values($groupStandings[$group]);

            if (count($table) <= 3) {
                continue;
            }

            usort($table, function ($a, $b) {
                return (int) $a['rank'] - (int) $b['rank'];
            });

            $lastRow = $table[count($table) - 1];

            if (! empty($lastRow['needs_lot'])) {
                throw new InvalidArgumentException(
                    'drop_last requires a resolved last place; a lot decision is still open'
                );
            }

            $candidate = (int) $row['tpid'];
            $lastTpid = (int) $table[count($table) - 1]['tpid'];
            $kept = [];

            foreach ($matches as $match) {
                $a = (int) $match['tpid_a'];
                $b = (int) $match['tpid_b'];

                if (($a === $candidate && $b === $lastTpid)
                    || ($a === $lastTpid && $b === $candidate)) {
                    continue;
                }

                $kept[] = $match;
            }

            $stats = $this->aggregate($candidate, $kept);
            $adjust = isset($row['points_adjust']) ? (int) $row['points_adjust'] : 0;
            $rows[$index]['wins'] = $stats['wins'];
            $rows[$index]['losses'] = $stats['losses'];
            $rows[$index]['played'] = $stats['played'];
            $rows[$index]['points'] = $stats['wins'] * $this->pointsPerWin - $adjust;
            $rows[$index]['set_diff'] = $stats['set_diff'];
            $rows[$index]['game_diff'] = $stats['game_diff'];
            $rows[$index]['dropped_opponent_label'] = isset($lastRow['label']) ? $lastRow['label'] : null;
        }

        return $rows;
    }

    /**
     * Whether a manual lot order fits the current tie: its tpids are exactly the rows flagged needs_lot
     * (the condition resolveLot() enforces). A stale order (results changed, a typo, a scope without a
     * tie) does not fit and must not be applied.
     *
     * @param  array<array<string, mixed>>  $rows
     * @param  array<int|string>  $orderedTpids
     */
    public static function lotOrderFits(array $rows, array $orderedTpids): bool
    {
        $unresolved = [];
        foreach ($rows as $row) {
            if (! empty($row['needs_lot'])) {
                $unresolved[] = (int) $row['tpid'];
            }
        }
        $provided = array_values(array_unique(array_map('intval', $orderedTpids)));
        sort($unresolved);
        sort($provided);

        return count($unresolved) >= 2 && $unresolved === $provided;
    }

    /**
     * Applies a persisted lot order to an already ranked block.
     *
     * @param  array<array<string, mixed>>  $rows
     * @param  array<int|string>  $orderedTpids
     * @return list<array<string, mixed>>
     */
    public function resolveLot(array $rows, array $orderedTpids): array
    {
        $unresolved = [];
        foreach ($rows as $row) {
            if (! empty($row['needs_lot'])) {
                $unresolved[] = (int) $row['tpid'];
            }
        }
        $provided = array_values(array_unique(array_map('intval', $orderedTpids)));
        $checkUnresolved = $unresolved;
        $checkProvided = $provided;
        sort($checkUnresolved);
        sort($checkProvided);
        if ($checkUnresolved !== $checkProvided) {
            throw new InvalidArgumentException(
                'lot order must contain exactly all players of the unresolved tie'
            );
        }

        $positions = [];
        foreach (array_values($orderedTpids) as $index => $tpid) {
            $positions[(int) $tpid] = $index;
        }

        usort($rows, function ($a, $b) use ($positions) {
            $aListed = isset($positions[(int) $a['tpid']]);
            $bListed = isset($positions[(int) $b['tpid']]);
            if ($aListed && $bListed && ! empty($a['needs_lot']) && ! empty($b['needs_lot'])) {
                return $positions[(int) $a['tpid']] - $positions[(int) $b['tpid']];
            }

            return (int) $a['rank'] - (int) $b['rank'];
        });

        foreach ($rows as $index => $row) {
            if (isset($positions[(int) $row['tpid']])) {
                $rows[$index]['needs_lot'] = false;
                $rows[$index]['decided_by'] = 'lot';
            }
            $rows[$index]['rank'] = $index + 1;
        }

        return $rows;
    }

    /** @param  array<string, mixed>  $row */
    private function norm(array $row, string $key, bool $perMatch): float
    {
        $value = isset($row[$key]) ? (float) $row[$key] : 0.0;

        if (! $perMatch) {
            return $value;
        }

        $played = isset($row['played']) ? (int) $row['played'] : 0;

        return $played > 0 ? $value / $played : 0.0;
    }

    /**
     * Sums wins, losses and set and game difference of one player over the given matches.
     *
     * @param  list<array<string, mixed>>  $matches
     * @return array{wins: int, losses: int, played: int, set_diff: int, game_diff: int}
     */
    private function aggregate(int $tpid, array $matches): array
    {
        $wins = 0;
        $losses = 0;
        $played = 0;
        $setDiff = 0;
        $gameDiff = 0;

        foreach ($matches as $match) {
            $a = (int) $match['tpid_a'];
            $b = (int) $match['tpid_b'];

            if ($a !== $tpid && $b !== $tpid) {
                continue;
            }

            if (! isset($match['winner']) || ! $match['winner']) {
                continue;
            }

            $played++;

            $isA = ($a === $tpid);

            $ownSets = (int) ($isA ? $match['sets_a'] : $match['sets_b']);
            $oppSets = (int) ($isA ? $match['sets_b'] : $match['sets_a']);
            $ownGames = (int) ($isA ? $match['games_a'] : $match['games_b']);
            $oppGames = (int) ($isA ? $match['games_b'] : $match['games_a']);

            $setDiff += $ownSets - $oppSets;
            $gameDiff += $ownGames - $oppGames;

            if ((int) $match['winner'] === $tpid) {
                $wins++;
            } else {
                $losses++;
            }
        }

        return [
            'wins' => $wins,
            'losses' => $losses,
            'played' => $played,
            'set_diff' => $setDiff,
            'game_diff' => $gameDiff,
        ];
    }

    /**
     * Matches in which BOTH participants are in the given set.
     *
     * @param  list<int|string>  $tpids
     * @param  list<array<string, mixed>>  $matches
     * @return list<array<string, mixed>>
     */
    private function matchesAmong(array $tpids, array $matches): array
    {
        $lookup = array_flip(array_map('intval', $tpids));
        $out = [];

        foreach ($matches as $match) {
            if (isset($lookup[(int) $match['tpid_a']]) && isset($lookup[(int) $match['tpid_b']])) {
                $out[] = $match;
            }
        }

        return $out;
    }

    /**
     * Splits a list sorted by points into blocks of equal points.
     *
     * @param  array<array<string, mixed>>  $ordered
     * @return list<array<int, array<string, mixed>>>
     */
    private function blocksByPoints(array $ordered): array
    {
        $blocks = [];
        $current = [];
        $points = null;

        foreach ($ordered as $row) {
            if ($points !== null && $row['points'] != $points) {
                $blocks[] = $current;
                $current = [];
            }

            $current[] = $row;
            $points = $row['points'];
        }

        if ($current) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function runnerUpEqual(array $a, array $b, bool $perMatch): bool
    {
        foreach (['points', 'set_diff', 'game_diff'] as $key) {
            if (abs($this->norm($a, $key, $perMatch) - $this->norm($b, $key, $perMatch)) >= 0.0001) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, mixed>  $row */
    private function seedOf(array $row): int
    {
        return isset($row['seed']) ? (int) $row['seed'] : PHP_INT_MAX;
    }
}
