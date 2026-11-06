<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use RuntimeException;

/**
 * Produces group slots, matches and bracket lines from the tournament format (port of the ZF2
 * GeneratorService).
 *
 * Everything here is idempotent: a second call creates nothing twice. This matters because generation
 * runs several times during a tournament (after the group draw, after the knockout draw, after the
 * group phase).
 *
 * Deliberately framework free: the class only calculates and returns records, writing is up to the
 * caller. The logic is testable without a database.
 */
final class GeneratorService
{
    /**
     * Assigns the seeded group heads.
     *
     * The heads are the best ITN per competition, one per group, in seed-list order. With heads = 1 that
     * means: seed 1 on A1, seed 2 on B1, seed 3 on C1.
     *
     * @param  list<array<string, mixed>>  $players  participants with tpid and seed, ordered by seed
     * @return array<int, array{grp: string, grp_pos: int}> tpid => grp, grp_pos
     */
    public function assignHeads(FormatService $f, string $phaseKey, array $players): array
    {
        $phase = $f->phase($phaseKey);
        $heads = isset($phase['heads']) ? (int) $phase['heads'] : 0;

        if ($heads < 1) {
            return [];
        }

        $inRange = $this->playersInSeedRange($phase, $players);

        $out = [];
        $index = 0;

        // By position: first all position 1 across the groups, then position 2 (matters only for heads > 1).

        for ($pos = 1; $pos <= $heads; $pos++) {
            foreach ($phase['groups'] as $group) {
                if (! isset($inRange[$index])) {
                    throw new RuntimeException(sprintf(
                        'phase "%s": not enough players to fill the seeded heads', $phaseKey
                    ));
                }

                $player = $inRange[$index++];

                $out[(int) $player['tpid']] = [
                    'grp' => $group['key'],
                    'grp_pos' => $pos,
                ];
            }
        }

        return $out;
    }

    /**
     * Matches of a group phase.
     *
     * A match is created as soon as BOTH participants are known; the whole group does not have to wait. The
     * group head is fixed from the start, so every player has an opponent right after the allocation.
     * Players are filed by group position, not by array position: the format's pairing list refers to
     * positions 1..n.
     *
     * @param  array<string, array<array<string, mixed>>>  $playersByGroup  group => list
     * @param  list<string>  $existing  existing mkey values, to avoid double creation
     * @return array{matches: list<array<string, mixed>>, skipped: array<string, string>} each match: mkey,
     *                                                                                    phase, grp, rnd,
     *                                                                                    slot, tpid_a, tpid_b
     */
    public function groupMatches(FormatService $f, string $phaseKey, array $playersByGroup, array $existing = []): array
    {
        $phase = $f->phase($phaseKey);
        $out = [];
        $skipped = [];

        foreach ($phase['groups'] as $group) {
            $key = $group['key'];
            $size = (int) $group['size'];
            $players = isset($playersByGroup[$key]) ? array_values($playersByGroup[$key]) : [];

            $byPosition = [];

            foreach ($players as $player) {
                if ($player['grp_pos']) {
                    $byPosition[(int) $player['grp_pos']] = $player;
                }
            }

            $open = 0;

            foreach ($f->pairings($size) as $index => $pair) {
                $mkey = $key.'-'.$index;

                if (in_array($mkey, $existing, true)) {
                    continue;
                }

                $posA = $pair[0] + 1;
                $posB = $pair[1] + 1;

                // Not both known yet: this match is created later.
                if (! isset($byPosition[$posA]) || ! isset($byPosition[$posB])) {
                    $open++;

                    continue;
                }

                $out[] = [
                    'mkey' => $mkey,
                    'phase' => 'group',
                    'grp' => $key,
                    'rnd' => null,
                    'slot' => $index + 1,
                    'tpid_a' => (int) $byPosition[$posA]['tpid'],
                    'tpid_b' => (int) $byPosition[$posB]['tpid'],
                ];
            }

            if ($open) {
                $skipped[$key] = sprintf('%u von %u Plaetzen besetzt, %u Partie(n) noch offen',
                    count($byPosition), $size, $open);
            }
        }

        return ['matches' => $out, 'skipped' => $skipped];
    }

    /**
     * Bracket lines of a knockout phase, straight from the format.
     *
     * Placeholder and tpid stay empty: they come from the draw or from the group phase.
     *
     * @return list<array{line: int, kind: string|null, seed_no: int|null, pool: string|null}>
     */
    public function bracketLines(FormatService $f, string $phaseKey): array
    {
        $out = [];

        foreach ($f->lineMap($phaseKey) as $line => $marker) {
            $entry = ['line' => (int) $line, 'kind' => null, 'seed_no' => null, 'pool' => null];

            if ($marker === 'bye') {
                $entry['kind'] = 'bye';
            } elseif (strpos($marker, 'seed:') === 0) {
                $entry['kind'] = 'seed';
                $entry['seed_no'] = (int) substr($marker, 5);
            } elseif (strpos($marker, 'pool:') === 0) {
                $entry['kind'] = 'pool';
                $entry['pool'] = substr($marker, 5);
            } else {
                throw new RuntimeException(sprintf('unknown line marker "%s"', $marker));
            }

            $out[] = $entry;
        }

        return $out;
    }

    /**
     * All matches of a knockout bracket, across all rounds.
     *
     * A bracket of 16 has 8 + 4 + 2 + 1 = 15 matches. They are all created in advance so the bracket can be
     * shown in every state ("open" instead of missing cards). Round 1, match i covers lines 2i-1 and 2i.
     *
     * @param  list<string>  $existing
     * @return list<array<string, mixed>> each: mkey, phase, rnd, slot, line_a, line_b
     */
    public function bracketMatches(FormatService $f, string $phaseKey, array $existing = []): array
    {
        $phase = $f->phase($phaseKey);
        $size = (int) $phase['size'];

        $out = [];
        $round = 1;
        $count = (int) ($size / 2);

        while ($count >= 1) {
            for ($slot = 1; $slot <= $count; $slot++) {
                $mkey = $phaseKey.'-R'.$round.'-'.$slot;

                if (in_array($mkey, $existing, true)) {
                    continue;
                }

                $entry = [
                    'mkey' => $mkey,
                    'phase' => $phaseKey,
                    'grp' => null,
                    'rnd' => $round,
                    'slot' => $slot,
                    'tpid_a' => null,
                    'tpid_b' => null,
                ];

                if ($round === 1) {
                    $entry['line_a'] = 2 * $slot - 1;
                    $entry['line_b'] = 2 * $slot;
                }

                $out[] = $entry;
            }

            if ($count === 1) {
                break;
            }

            $round++;
            $count = (int) ($count / 2);
        }

        return $out;
    }

    /**
     * Where the winner of a knockout match advances to.
     *
     * The winner of round r, match i goes to round r+1, match ceil(i/2): side A for odd i, side B for even.
     *
     * @return array{rnd: int, slot: int, side: string}|null null for the final
     */
    public function advance(FormatService $f, string $phaseKey, int $round, int $slot): ?array
    {
        $phase = $f->phase($phaseKey);
        $size = (int) $phase['size'];

        $matchesInRound = (int) ($size / pow(2, $round));

        if ($matchesInRound <= 1) {
            return null;
        }

        return [
            'rnd' => $round + 1,
            'slot' => (int) ceil($slot / 2),
            'side' => ($slot % 2 === 1) ? 'a' : 'b',
        ];
    }

    /**
     * Placeholders for a pre-draw.
     *
     * Seeded lines get S1..Sn, pool lines the codes of their pot. The order is the counting order, not
     * the allocation: the drawing happens afterwards.
     *
     * @param  array<string, string>  $poolPrefix  pool => prefix, e.g. ['q' => 'Q', 'ac' => 'AC']
     * @return array<string, list<string>> pool => list of codes
     */
    public function poolPlaceholders(FormatService $f, string $phaseKey, array $poolPrefix): array
    {
        $phase = $f->phase($phaseKey);
        $pools = isset($phase['lines']['pools']) ? $phase['lines']['pools'] : [];

        $out = [];

        foreach ($pools as $pool => $lines) {
            if (! isset($poolPrefix[$pool])) {
                throw new RuntimeException(sprintf('no placeholder prefix for pool "%s"', $pool));
            }

            $entries = [];

            for ($i = 1; $i <= count($lines); $i++) {
                $entries[] = $poolPrefix[$pool].$i;
            }

            $out[$pool] = $entries;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $phase
     * @param  array<array<string, mixed>>  $players
     * @return list<array<string, mixed>>
     */
    private function playersInSeedRange(array $phase, array $players): array
    {
        if (! isset($phase['seed_range'])) {
            return array_values($players);
        }

        [$from, $to] = $phase['seed_range'];
        $out = [];

        foreach ($players as $player) {
            $seed = (int) $player['seed'];

            if ($seed >= (int) $from && $seed <= (int) $to) {
                $out[] = $player;
            }
        }

        usort($out, function ($a, $b) {
            return (int) $a['seed'] - (int) $b['seed'];
        });

        return $out;
    }
}
