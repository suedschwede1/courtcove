<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use Closure;
use RuntimeException;

/**
 * Resolves the rank codes of a pre-draw to players (port of the ZF2 ResolverService).
 *
 * Background: the knockout phase A is drawn before the group phase is played. The bracket lines
 * therefore hold codes such as Q3 or AC7 first. After the group phase this class works out who they
 * mean. A purely mechanical step, no second draw.
 *
 * Codes of the club championship:
 *
 *   S1-S3    the three group winners of A-C, among each other by record
 *   S4       the best of the three runners-up of A-C
 *   AC5-AC9  first the two remaining runners-up by record, then the three thirds
 *   Q1-Q4    the four group winners of D-G by record
 *   Q5-Q6    the two best runners-up of D-G
 *
 * Principle: placement beats record. A group winner cannot be overtaken by a runner-up, a runner-up
 * not by a third. The record decides only WITHIN a placement level.
 *
 * All cross-group comparisons run through StandingsService::rankRunnersUp() and so in the format's mode
 * (drop_last by default). That deliberately includes the group winners, whose order is sporting
 * irrelevant: one rule, the same everywhere.
 *
 * Framework free and plain arrays, like the old code.
 */
final class ResolverService
{
    /** @var array<string, list<int|string>> */
    private array $lotOrders = [];

    /** @var (Closure(string, list<int>): list<int>)|null */
    private ?Closure $lotResolver = null;

    public function __construct(private readonly StandingsService $standingsService) {}

    /**
     * @param  array<string, list<int|string>>  $orders  manual lot results by scope
     */
    public function setLotOrders(array $orders): self
    {
        $this->lotOrders = $orders;

        return $this;
    }

    /**
     * Function that derives a lot decision when none was entered by hand.
     *
     * Signature: function (string $scope, list<int> $tiedTpids): ordered tpid list
     *
     * Why a function: this class stays framework free, it knows neither database nor seed. The
     * derivation needs both. Through an injected function the separation stays, and the resolver can
     * still ask at the moment of need: it is the only one that knows WHICH players are tied.
     *
     * Without this function the old behaviour stays: needs_lot is reported and the ranking refused.
     *
     * @param  (Closure(string, list<int>): list<int>)|null  $resolver
     */
    public function setLotResolver(?Closure $resolver): self
    {
        $this->lotResolver = $resolver;

        return $this;
    }

    /**
     * Lot decision for a context: manual entry, otherwise derived.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<int|string>
     */
    private function lotOrderFor(?string $scope, array $rows): array
    {
        if ($scope && isset($this->lotOrders[$scope]) && $this->lotOrders[$scope]) {
            return $this->lotOrders[$scope];
        }

        if (! $scope || ! $this->lotResolver) {
            return [];
        }

        $tied = [];

        foreach ($rows as $row) {
            if (! empty($row['needs_lot'])) {
                $tied[] = (int) $row['tpid'];
            }
        }

        if (count($tied) < 2) {
            return [];
        }

        return call_user_func($this->lotResolver, $scope, $tied);
    }

    /**
     * Candidates of one placement level, ranked across groups.
     *
     * @param  int  $place  1 = group winner, 2 = runner-up, 3 = third
     * @param  array<string, array<int, array<string, mixed>>>  $standings  grp => table from rank()
     * @param  list<array<string, mixed>>  $matches  scored matches of the phase
     * @return list<array<string, mixed>> ranked; each with tpid, label, grp, rank, needs_lot
     */
    public function byPlace(int $place, array $standings, array $matches, ?string $phaseKey = null): array
    {
        $candidates = [];

        foreach ($standings as $groupKey => $table) {
            $index = $place - 1;

            if (isset($table[$index])) {
                if (! empty($table[$index]['needs_lot'])) {
                    throw new RuntimeException(sprintf(
                        'Losentscheidung in Gruppe %s fuer Platz %u erforderlich.',
                        $groupKey, $place
                    ));
                }

                $candidates[] = array_merge($table[$index], ['grp' => $groupKey]);
            }
        }

        if (! $candidates) {
            return [];
        }

        $rows = $this->standingsService->rankRunnersUp($candidates, $standings, $matches);
        $scope = $phaseKey ? 'phase:'.$phaseKey.':place:'.$place : null;
        $order = $this->lotOrderFor($scope, $rows);

        return $order ? $this->standingsService->resolveLot($rows, $order) : $rows;
    }

    /**
     * Codes for a group phase from which ALL players advance.
     *
     * Used for A-C: 4 seeded plus 5 unseeded. The codes follow the order winners, runners-up, thirds,
     * inside each level by record.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $standings
     * @param  list<array<string, mixed>>  $matches
     * @param  string  $seedPrefix  prefix of the seeded codes, e.g. 'S'
     * @param  string  $poolPrefix  prefix of the unseeded, e.g. 'AC'
     * @param  int  $seedCount  how many are seeded
     * @return array<string, array<string, mixed>> code => entry (tpid, label, grp, place, needs_lot)
     */
    public function codesAllAdvance(array $standings, array $matches, string $seedPrefix, string $poolPrefix, int $seedCount, ?string $phaseKey = null): array
    {
        $ordered = [];
        $place = 1;

        // As long as a placement level still has candidates, go on: the number of levels follows from the
        // largest group.

        while (true) {
            $level = $this->byPlace($place, $standings, $matches, $phaseKey);

            if (! $level) {
                break;
            }

            $this->assertResolvedOrder($level);

            foreach ($level as $entry) {
                $ordered[] = array_merge($entry, ['place' => $place]);
            }

            $place++;

            if ($place > 20) {
                throw new RuntimeException('too many placement levels - broken standings?');
            }
        }

        $codes = [];

        foreach ($ordered as $index => $entry) {
            $number = $index + 1;

            $code = ($number <= $seedCount)
                ? $seedPrefix.$number
                : $poolPrefix.$number;

            $codes[$code] = $entry;
        }

        return $codes;
    }

    /**
     * Codes for a group phase from which only winners and the best runners-up advance. Used for D-G: Q1-Q4
     * are the winners, Q5-Q6 the two best runners-up.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $standings
     * @param  list<array<string, mixed>>  $matches
     * @param  int  $secondsCount  how many runners-up advance
     * @return array<string, array<string, mixed>> code => entry
     */
    public function codesPromoted(array $standings, array $matches, string $prefix, int $secondsCount, ?string $phaseKey = null): array
    {
        $winners = $this->byPlace(1, $standings, $matches, $phaseKey);
        $seconds = $this->byPlace(2, $standings, $matches, $phaseKey);

        $this->assertResolvedOrder($winners);
        $this->assertResolvedOrder($seconds, $secondsCount);

        $codes = [];
        $number = 1;

        foreach ($winners as $entry) {
            $codes[$prefix.$number] = array_merge($entry, ['place' => 1]);
            $number++;
        }

        foreach (array_slice($seconds, 0, $secondsCount) as $entry) {
            $codes[$prefix.$number] = array_merge($entry, ['place' => 2]);
            $number++;
        }

        return $codes;
    }

    /**
     * The players who did NOT advance: field for the consolation competition.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $standings
     * @param  list<array<string, mixed>>  $matches
     * @param  array<int|string>  $promotedTpids
     * @return array<string, array<string, mixed>> ranked like codesAllAdvance, without the promoted
     */
    public function remainder(array $standings, array $matches, array $promotedTpids, string $prefix, ?string $phaseKey = null): array
    {
        $codes = [];
        $number = 1;
        $place = 1;

        while (true) {
            $level = $this->byPlace($place, $standings, $matches, $phaseKey);

            if (! $level) {
                break;
            }

            $this->assertResolvedOrder($level);

            foreach ($level as $entry) {
                if (in_array((int) $entry['tpid'], array_map('intval', $promotedTpids), true)) {
                    continue;
                }

                $codes[$prefix.$number] = array_merge($entry, ['place' => $place]);
                $number++;
            }

            $place++;

            if ($place > 20) {
                throw new RuntimeException('too many placement levels - broken standings?');
            }
        }

        return $codes;
    }

    /**
     * Remaining field with the best $seedCount players as S1..Sn and the others as pool codes from the
     * following number on.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $standings
     * @param  list<array<string, mixed>>  $matches
     * @param  array<int|string>  $promotedTpids
     * @return array<string, array<string, mixed>>
     */
    public function codesRemainderSeeded(array $standings, array $matches, array $promotedTpids, string $prefix, int $seedCount, ?string $phaseKey = null): array
    {
        $ordered = array_values($this->remainder($standings, $matches, $promotedTpids, '__TMP', $phaseKey));
        $codes = [];

        foreach ($ordered as $index => $entry) {
            $number = $index + 1;
            $code = ($number <= $seedCount) ? 'S'.$number : $prefix.$number;
            $codes[$code] = $entry;
        }

        return $codes;
    }

    /**
     * A flagged lot decision must never be replaced by the stable seed order. For a selection the check
     * runs up to and including the first non-selected row at the border.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function assertResolvedOrder(array $rows, ?int $selectionCount = null): void
    {
        $limit = $selectionCount === null ? count($rows) : min(count($rows), $selectionCount + 1);

        for ($i = 0; $i < $limit; $i++) {
            if (! empty($rows[$i]['needs_lot'])) {
                throw new RuntimeException('Losentscheidung erforderlich; die vorlaeufige Setzplatzreihenfolge darf nicht verwendet werden.');
            }
        }
    }

    /**
     * Assigns players to the bracket lines.
     *
     * Seeded lines get their player via the seed number, pool lines via the placeholder entered at the draw.
     *
     * @param  array<int|string, array<string, mixed>>  $lines  line => row of bs_tournament_lines
     * @param  array<string, array<string, mixed>>  $codes  code => entry, merged from codes*()
     * @return array<int, int> line => tpid, only for resolvable lines
     */
    public function resolveLines(array $lines, array $codes, string $seedPrefix = 'S'): array
    {
        $out = [];

        foreach ($lines as $lineNo => $line) {
            if ($line['kind'] === 'bye') {
                continue;
            }

            $code = null;

            if ($line['kind'] === 'seed') {
                $code = $seedPrefix.(int) $line['seed_no'];
            } elseif ($line['placeholder']) {
                $code = $line['placeholder'];
            }

            if ($code === null || ! isset($codes[$code])) {
                continue;
            }

            $out[(int) $lineNo] = (int) $codes[$code]['tpid'];
        }

        return $out;
    }

    /**
     * First-round pairings from the resolved lines.
     *
     * Round 1, match i covers lines 2i-1 and 2i. A line with kind = bye yields no player: the opponent
     * stands alone and advances automatically.
     *
     * @param  array<int|string, array<string, mixed>>  $lines
     * @param  array<int, int>  $resolved
     * @return array<int, array{tpid_a: int|null, tpid_b: int|null, walkover: bool}> slot => ...
     */
    public function firstRoundPlayers(array $lines, array $resolved, int $size): array
    {
        $out = [];

        for ($slot = 1; $slot <= $size / 2; $slot++) {
            $lineA = 2 * $slot - 1;
            $lineB = 2 * $slot;

            $byeA = (isset($lines[$lineA]) && $lines[$lineA]['kind'] === 'bye');
            $byeB = (isset($lines[$lineB]) && $lines[$lineB]['kind'] === 'bye');

            $out[$slot] = [
                'tpid_a' => isset($resolved[$lineA]) ? $resolved[$lineA] : null,
                'tpid_b' => isset($resolved[$lineB]) ? $resolved[$lineB] : null,
                'walkover' => ($byeA || $byeB),
            ];
        }

        return $out;
    }
}
