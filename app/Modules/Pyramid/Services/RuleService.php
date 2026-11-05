<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Services;

use InvalidArgumentException;

/**
 * Geometry and shift rules of the challenge pyramid.
 *
 * Deliberately free of database and framework code: the ranking is stored
 * linearly as position 1..n and the pyramid is only a presentation. Row n ends
 * at n*(n+1)/2 - row 1 at 1, row 2 at 3, row 3 at 6, row 4 at 10.
 *
 * Ported unchanged from the old application (Pyramid\Service\RuleService) and
 * checked against golden vectors generated from it.
 */
final class RuleService
{
    /**
     * Row of a position, 1-based.
     */
    public function rowOf(int|string $position): int
    {
        $original = $position;
        $position = (int) $position;

        if ($position < 1) {
            throw new InvalidArgumentException('Position must be at least 1, was '.var_export($original, true));
        }

        $row = 1;

        while ($this->lastOfRow($row) < $position) {
            $row++;
        }

        return $row;
    }

    /**
     * Column inside the row, 1-based. Column 1 is the best position of the row.
     */
    public function colOf(int|string $position): int
    {
        return (int) $position - $this->firstOfRow($this->rowOf($position)) + 1;
    }

    public function firstOfRow(int|string $row): int
    {
        $original = $row;
        $row = (int) $row;

        if ($row < 1) {
            throw new InvalidArgumentException('Row must be at least 1, was '.var_export($original, true));
        }

        return (int) ($row * ($row - 1) / 2) + 1;
    }

    public function lastOfRow(int|string $row): int
    {
        $original = $row;
        $row = (int) $row;

        if ($row < 1) {
            throw new InvalidArgumentException('Row must be at least 1, was '.var_export($original, true));
        }

        return (int) ($row * ($row + 1) / 2);
    }

    /**
     * Challengeable positions: everything to the left in the own row, plus the
     * complete row above.
     *
     * Rule 3.2 sentence 2 of the rulebook ("from place 3 on, every player may
     * challenge every player ahead") is NOT a special rule but an example:
     * position 3 already reaches 1 and 2 through the basic rule.
     *
     * $count (number of participants) cuts off positions that do not exist in
     * an incomplete last row. That only tells existing from missing positions
     * correctly when the ranking has no gaps; gaps (for example after a removed
     * player) are repaired by normalising the ranking, not here.
     *
     * @return list<int> ascending
     */
    public function challengeable(int|string $position, int|string|null $count = null): array
    {
        $row = $this->rowOf($position);
        $col = $this->colOf($position);
        $first = $this->firstOfRow($row);
        $targets = [];

        for ($c = 1; $c < $col; $c++) {
            $targets[] = $first + $c - 1;
        }

        if ($row > 1) {
            for ($p = $this->firstOfRow($row - 1); $p <= $this->lastOfRow($row - 1); $p++) {
                $targets[] = $p;
            }
        }

        if ($count !== null) {
            $limit = (int) $count;
            $targets = array_filter($targets, fn (int $p): bool => $p <= $limit);
        }

        sort($targets);

        return $targets;
    }

    /**
     * Rule 4.2 - the winner moves to the loser's place; the loser and everyone
     * in between drop one place.
     *
     * @param  array<int, int|string>  $positions  playerId => position
     * @param  int|string  $winnerPos  current position of the winner
     * @param  int|string  $loserPos  current position of the loser
     * @return array<int, int|string> playerId => new position, complete; the input unchanged when
     *                                the challenger already stands ahead
     *
     * @throws InvalidArgumentException when winner or loser position is not in $positions
     */
    public function shiftForWin(array $positions, int|string $winnerPos, int|string $loserPos): array
    {
        $winnerPos = (int) $winnerPos;
        $loserPos = (int) $loserPos;

        // Database values can be numeric strings (see the cast in the loop below),
        // so normalise first; a strict comparison would miss valid positions.
        $known = array_map('intval', $positions);

        if (! in_array($winnerPos, $known, true) || ! in_array($loserPos, $known, true)) {
            throw new InvalidArgumentException(
                'Winner and loser position must occur in the ranking, were '.$winnerPos.' and '.$loserPos
            );
        }

        // Rule 3.8: results entered in the meantime can put the challenger ahead of
        // the opponent already. Then there is nothing to win.
        if ($loserPos >= $winnerPos) {
            return $positions;
        }

        $result = [];

        foreach ($positions as $id => $pos) {
            $pos = (int) $pos;

            if ($pos === $winnerPos) {
                $result[$id] = $loserPos;
            } elseif ($pos >= $loserPos && $pos < $winnerPos) {
                $result[$id] = $pos + 1;
            } else {
                $result[$id] = $pos;
            }
        }

        return $result;
    }

    /**
     * Rule 6.1 - joining by challenging. The newcomer is NOT part of $positions,
     * having no position yet; the caller sets the newcomer to $loserPos.
     *
     * @param  array<int, int|string>  $positions  playerId => position
     * @param  int|string  $loserPos  position the newcomer moves to
     * @return array<int, int> playerId => new position
     */
    public function shiftForEntry(array $positions, int|string $loserPos): array
    {
        $loserPos = (int) $loserPos;
        $result = [];

        foreach ($positions as $id => $pos) {
            $pos = (int) $pos;
            $result[$id] = $pos >= $loserPos ? $pos + 1 : $pos;
        }

        return $result;
    }

    /**
     * Participants split into rows for display. Expects active players sorted
     * ascending by position; items are arrays with key 'position' or objects
     * with a position property.
     *
     * @param  iterable<mixed>  $players
     * @return array<int, list<mixed>> row (1-based) => players
     */
    public function pyramidRows(iterable $players): array
    {
        $rows = [];

        foreach ($players as $player) {
            $position = is_array($player) ? $player['position'] : $player->position;
            $rows[$this->rowOf($position)][] = $player;
        }

        return $rows;
    }
}
