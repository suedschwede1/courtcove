<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

/**
 * Tournament format as configuration (port of the ZF2 FormatService).
 *
 * Reads the JSON of bs_tournaments.format and derives everything the rest of the module needs: group
 * slots, pairings, draw pots, bracket lines. Nothing of this is a constant in code: another tournament
 * is a new configuration.
 *
 * validate() checks the configuration against the conditions it has to meet, in particular the bracket
 * lines: a split in which two promoted players could meet in round 1 is rejected instead of silently
 * accepted.
 *
 * Framework free and works on plain arrays, exactly like the old code.
 */
final class FormatService
{
    /** @var array<mixed> */
    private array $format;

    /**
     * Pairing orders from the design handoff. The order for 4 spreads breaks better than the naive
     * combination order: player 0 has two matches of rest after the first one.
     *
     * @var array<int, list<array{0:int,1:int}>>
     */
    private const PAIRING_ORDER = [
        2 => [[0, 1]],
        3 => [[0, 1], [0, 2], [1, 2]],
        4 => [[0, 1], [2, 3], [0, 2], [1, 3], [0, 3], [1, 2]],
    ];

    /** @param  array<mixed>|string|null  $json */
    public function __construct(array|string|null $json)
    {
        $format = is_array($json) ? $json : json_decode((string) $json, true);

        if (! is_array($format)) {
            throw new InvalidFormatException('format is not valid JSON');
        }

        $this->format = $format;
    }

    /** @return array<mixed> */
    public function raw(): array
    {
        return $this->format;
    }

    public function matchFormat(): string
    {
        // Stored data is never rewritten: a non-string value falls back to the default instead of failing.
        $value = $this->format['match_format'] ?? null;

        return is_string($value) ? $value : 'best_of_3_mtb';
    }

    /** @return array<mixed> */
    public function ranking(): array
    {
        $defaults = [
            'points_per_win' => 2,
            'tiebreak_scope' => 'tied',
            'criteria' => ['points', 'head_to_head', 'set_diff', 'game_diff', 'lot'],
        ];

        return isset($this->format['ranking'])
            ? array_merge($defaults, $this->format['ranking'])
            : $defaults;
    }

    public function runnerUpMode(): string
    {
        // Non-string stored values fall back to the default (ZF2's setRunnerUpMode did the same).
        $runnerUp = $this->format['runner_up'] ?? null;
        $value = is_array($runnerUp) ? ($runnerUp['mode'] ?? null) : null;

        return is_string($value) ? $value : 'drop_last';
    }

    /** @return list<array<mixed>> */
    public function phases(): array
    {
        return isset($this->format['phases']) ? $this->format['phases'] : [];
    }

    /** @return array<mixed> */
    public function phase(string $key): array
    {
        foreach ($this->phases() as $phase) {
            if (($phase['key'] ?? null) === $key) {
                return $phase;
            }
        }

        throw new InvalidFormatException(sprintf('phase "%s" is not defined', $key));
    }

    /** @return list<array<mixed>> */
    public function groupPhases(): array
    {
        return array_values(array_filter($this->phases(), function ($p) {
            return ($p['type'] ?? null) === 'groups';
        }));
    }

    /** @return list<array<mixed>> */
    public function bracketPhases(): array
    {
        return array_values(array_filter($this->phases(), function ($p) {
            return ($p['type'] ?? null) === 'bracket';
        }));
    }

    // ------------------------------------------------------------------ groups

    /**
     * All group slots of a phase, e.g. A1, A2, A3, B1, ...
     *
     * head = true marks the seeded group heads. They sit on the first positions of each group and are
     * not drawn.
     *
     * @return list<array{slot: string, group: string, position: int, head: bool}>
     */
    public function groupSlots(string $phaseKey): array
    {
        $phase = $this->phase($phaseKey);
        $heads = isset($phase['heads']) ? (int) $phase['heads'] : 0;

        $slots = [];

        foreach ($phase['groups'] as $group) {
            for ($pos = 1; $pos <= (int) $group['size']; $pos++) {
                $slots[] = [
                    'slot' => $group['key'].$pos,
                    'group' => $group['key'],
                    'position' => $pos,
                    'head' => ($pos <= $heads),
                ];
            }
        }

        return $slots;
    }

    /**
     * The group slots to draw, in the order in which they are filled: the slot list for the draw.
     *
     * slot_order 'by_position': first all position 2 across all groups, then all position 3, then 4, so
     * that two consecutively drawn lots do not land in the same group. The order is purely presentational
     * (the pot is permuted randomly, every fixed order is equally fair).
     *
     * @return list<string>
     */
    public function drawSlots(string $phaseKey): array
    {
        $phase = $this->phase($phaseKey);
        $order = isset($phase['draw']['slot_order']) ? $phase['draw']['slot_order'] : 'by_position';

        $open = array_values(array_filter($this->groupSlots($phaseKey), function ($s) {
            return ! $s['head'];
        }));

        if ($order !== 'by_position') {
            return array_map(function ($s) {
                return $s['slot'];
            }, $open);
        }

        $maxPos = 0;

        foreach ($open as $slot) {
            $maxPos = max($maxPos, $slot['position']);
        }

        $out = [];

        for ($pos = 1; $pos <= $maxPos; $pos++) {
            foreach ($open as $slot) {
                if ($slot['position'] === $pos) {
                    $out[] = $slot['slot'];
                }
            }
        }

        return $out;
    }

    /**
     * Pairings of one group as index pairs into the group list.
     *
     * Sizes without a given order fall back to the plain combination order: not hand-optimised but
     * complete.
     *
     * @return list<array{0:int,1:int}>
     */
    public function pairings(int $size): array
    {
        if (isset(self::PAIRING_ORDER[$size])) {
            return self::PAIRING_ORDER[$size];
        }

        $pairs = [];

        for ($a = 0; $a < $size; $a++) {
            for ($b = $a + 1; $b < $size; $b++) {
                $pairs[] = [$a, $b];
            }
        }

        return $pairs;
    }

    /**
     * All matches of a group phase with their key, e.g. F-3.
     *
     * @return list<array{mkey: string, group: string, index: int, a: int, b: int}> positions are 1-based
     */
    public function groupMatches(string $phaseKey): array
    {
        $phase = $this->phase($phaseKey);
        $matches = [];

        foreach ($phase['groups'] as $group) {
            foreach ($this->pairings((int) $group['size']) as $index => $pair) {
                $matches[] = [
                    'mkey' => $group['key'].'-'.$index,
                    'group' => $group['key'],
                    'index' => $index,
                    'a' => $pair[0] + 1,
                    'b' => $pair[1] + 1,
                ];
            }
        }

        return $matches;
    }

    // ------------------------------------------------------------------ bracket

    /**
     * First-round pairings of a bracket: (1,2) (3,4) (5,6) ...
     *
     * @return list<array{0:int,1:int}>
     */
    public function firstRoundPairs(int $size): array
    {
        $pairs = [];

        for ($line = 1; $line <= $size; $line += 2) {
            $pairs[] = [$line, $line + 1];
        }

        return $pairs;
    }

    /**
     * How each bracket line is filled: 'seed:N', 'bye' or 'pool:KEY'.
     *
     * @return array<int, string>
     */
    public function lineMap(string $phaseKey): array
    {
        $phase = $this->phase($phaseKey);
        $lines = $phase['lines'];

        $map = [];

        foreach ($lines['seeded'] as $seed => $line) {
            $map[(int) $line] = 'seed:'.(int) $seed;
        }

        foreach (($lines['bye'] ?? []) as $line) {
            $map[(int) $line] = 'bye';
        }

        foreach (($lines['pools'] ?? []) as $pool => $poolLines) {
            foreach ($poolLines as $line) {
                $map[(int) $line] = 'pool:'.$pool;
            }
        }

        ksort($map);

        return $map;
    }

    /**
     * Round names of a bracket, from the first round to the final.
     *
     * @return list<string>
     */
    public function rounds(int $size): array
    {
        $names = [16 => 'Achtelfinale', 8 => 'Viertelfinale', 4 => 'Halbfinale', 2 => 'Finale'];

        $out = [];
        $step = $size;

        while ($step > 1) {
            $out[] = $names[$step] ?? sprintf('Runde der letzten %u', $step);
            $step = intdiv($step, 2);
        }

        return $out;
    }

    // ------------------------------------------------------------------ validation

    /**
     * Checks the configuration and throws on violations, so that a broken configuration does not
     * surface only during the tournament.
     *
     * @return list<string> notes that are not errors (the old code returned them, not $this)
     */
    public function validate(): array
    {
        $notes = [];

        if (! $this->phases()) {
            throw new InvalidFormatException('format defines no phases');
        }

        $keys = [];

        foreach ($this->phases() as $phase) {
            foreach (['key', 'type'] as $required) {
                if (! isset($phase[$required])) {
                    throw new InvalidFormatException('phase is missing "'.$required.'"');
                }
            }

            if (in_array($phase['key'], $keys, true)) {
                throw new InvalidFormatException(sprintf('duplicate phase key "%s"', $phase['key']));
            }

            $keys[] = $phase['key'];

            if ($phase['type'] === 'groups') {
                $notes = array_merge($notes, $this->validateGroupPhase($phase));
            } elseif ($phase['type'] === 'bracket') {
                $notes = array_merge($notes, $this->validateBracketPhase($phase));
            } else {
                throw new InvalidFormatException(sprintf('unknown phase type "%s"', $phase['type']));
            }
        }

        return $notes;
    }

    /**
     * @param  array<mixed>  $phase
     * @return list<string>
     */
    private function validateGroupPhase(array $phase): array
    {
        $notes = [];

        if (empty($phase['groups'])) {
            throw new InvalidFormatException(sprintf('phase "%s" has no groups', $phase['key']));
        }

        $total = 0;
        $groupKeys = [];

        foreach ($phase['groups'] as $group) {
            if ((int) $group['size'] < 2) {
                throw new InvalidFormatException(sprintf('group "%s" needs at least 2 players', $group['key']));
            }

            if (in_array($group['key'], $groupKeys, true)) {
                throw new InvalidFormatException(sprintf('duplicate group key "%s"', $group['key']));
            }

            $groupKeys[] = $group['key'];
            $total += (int) $group['size'];
        }

        // Group slots have to match the seed range exactly.

        if (isset($phase['seed_range'])) {
            $expected = (int) $phase['seed_range'][1] - (int) $phase['seed_range'][0] + 1;

            if ($expected !== $total) {
                throw new InvalidFormatException(sprintf(
                    'phase "%s": seed range covers %u players but groups have %u places',
                    $phase['key'], $expected, $total
                ));
            }
        }

        $heads = isset($phase['heads']) ? (int) $phase['heads'] : 0;

        foreach ($phase['groups'] as $group) {
            if ($heads >= (int) $group['size']) {
                throw new InvalidFormatException(sprintf(
                    'phase "%s": %u heads leave nothing to draw in group "%s"',
                    $phase['key'], $heads, $group['key']
                ));
            }
        }

        // Unequal group sizes need an adjustment for cross-group comparisons.

        $sizes = array_unique(array_map(function ($g) {
            return (int) $g['size'];
        }, $phase['groups']));

        if (count($sizes) > 1) {
            $notes[] = sprintf(
                'Phase "%s" hat unterschiedliche Gruppengroessen (%s). Vergleiche '
                .'ueber Gruppengrenzen hinweg muessen eine gemeinsame Spielzahl verwendet werden.',
                $phase['key'], implode(', ', $sizes)
            );
        }

        return $notes;
    }

    /**
     * @param  array<mixed>  $phase
     * @return list<string>
     */
    private function validateBracketPhase(array $phase): array
    {
        $notes = [];
        $size = (int) $phase['size'];

        if ($size < 2 || ($size & ($size - 1)) !== 0) {
            throw new InvalidFormatException(sprintf(
                'bracket "%s": size %u must be a power of two', $phase['key'], $size
            ));
        }

        $map = $this->lineMap($phase['key']);

        // Every line is assigned exactly once.

        for ($line = 1; $line <= $size; $line++) {
            if (! isset($map[$line])) {
                throw new InvalidFormatException(sprintf(
                    'bracket "%s": line %u is not assigned', $phase['key'], $line
                ));
            }
        }

        foreach (array_keys($map) as $line) {
            if ($line < 1 || $line > $size) {
                throw new InvalidFormatException(sprintf(
                    'bracket "%s": line %u is outside the bracket', $phase['key'], $line
                ));
            }
        }

        // Double assignment: lineMap() overwrites silently, so count again.

        $lines = $phase['lines'];
        $assigned = array_values($lines['seeded']);

        foreach (($lines['bye'] ?? []) as $l) {
            $assigned[] = $l;
        }

        foreach (($lines['pools'] ?? []) as $poolLines) {
            foreach ($poolLines as $l) {
                $assigned[] = $l;
            }
        }

        if (count($assigned) !== count(array_unique($assigned))) {
            $dupes = array_diff_assoc($assigned, array_unique($assigned));

            throw new InvalidFormatException(sprintf(
                'bracket "%s": line(s) %s are assigned more than once',
                $phase['key'], implode(', ', array_unique($dupes))
            ));
        }

        if (count($assigned) !== $size) {
            throw new InvalidFormatException(sprintf(
                'bracket "%s": %u lines assigned but bracket has %u',
                $phase['key'], count($assigned), $size
            ));
        }

        // Protection rule: no two lots of the same pot in round 1. This is the condition that justifies
        // the split in the first place; with this check the number list in the configuration is proven
        // instead of merely asserted.

        $protect = $phase['protect']['no_same_pool_in_round_1'] ?? [];

        foreach ($protect as $pool) {
            foreach ($this->firstRoundPairs($size) as $pair) {
                $a = $map[$pair[0]] ?? null;
                $b = $map[$pair[1]] ?? null;

                if ($a === 'pool:'.$pool && $b === 'pool:'.$pool) {
                    throw new InvalidFormatException(sprintf(
                        'bracket "%s": lines %u and %u are both in pool "%s" and meet in '
                        .'round 1 - the pool allocation violates its own protection rule',
                        $phase['key'], $pair[0], $pair[1], $pool
                    ));
                }
            }
        }

        // Byes have to match the difference between bracket and field.

        $poolSize = 0;

        foreach (($lines['pools'] ?? []) as $poolLines) {
            $poolSize += count($poolLines);
        }

        $byes = count($lines['bye'] ?? []);
        $field = count($lines['seeded']) + $poolSize;

        if ($field + $byes !== $size) {
            throw new InvalidFormatException(sprintf(
                'bracket "%s": %u players plus %u byes do not fill %u lines',
                $phase['key'], $field, $byes, $size
            ));
        }

        // A bye next to an unseeded player is usually a mistake.

        foreach (($lines['bye'] ?? []) as $byeLine) {
            $partner = ($byeLine % 2 === 1) ? $byeLine + 1 : $byeLine - 1;

            if (isset($map[$partner]) && strpos($map[$partner], 'seed:') !== 0) {
                $notes[] = sprintf(
                    'Bracket "%s": das Freilos auf Linie %u steht neben Linie %u, die nicht '
                    .'gesetzt ist. Ueblich ist ein Freilos fuer die hoechsten Setznummern.',
                    $phase['key'], $byeLine, $partner
                );
            }
        }

        return $notes;
    }
}
