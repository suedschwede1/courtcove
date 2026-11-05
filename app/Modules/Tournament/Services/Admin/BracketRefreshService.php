<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services\Admin;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Services\GeneratorService;
use App\Modules\Tournament\Services\ResolverService;
use App\Modules\Tournament\Services\TournamentManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "KO-Raster aktualisieren" (port of the old AdminController::refreshBracketAction).
 *
 * Per bracket phase, and only for phases whose feeding group phases are complete: resolves the line tpids
 * from the qualify rules (codes S1.., pool codes), applies the published swap rules, fills the first round
 * from the lines (a bye lets the sole player advance, without a score), and moves every winner on with
 * GeneratorService::advance. Everything runs in ONE transaction, as in ZF2; an exception rolls it back and
 * propagates. Idempotent: rows that already hold the right value are skipped and not counted.
 *
 * The resolver gets the manual lot orders and, for ties without a manual entry, the derivation from the
 * first executed draw (TournamentManager::derivedLotOrder). In ZF2 the resolver shared the StandingsService
 * instance the manager had configured from the format; here it receives that configured instance directly.
 */
final class BracketRefreshService
{
    public function __construct(
        private readonly TournamentManager $m,
        private readonly GeneratorService $generator,
    ) {}

    /** @return array{resolved:int, filled:int, advanced:int, notes: list<string>} */
    public function refresh(Tournament $t): array
    {
        $tid = (int) $t->tid;
        $this->m->forget($tid);

        try {
            return DB::transaction(fn (): array => $this->run($tid));
        } finally {
            // Rows read inside the transaction must not outlive it (committed or rolled back).
            $this->m->forget($tid);
        }
    }

    /** @return array{resolved:int, filled:int, advanced:int, notes: list<string>} */
    private function run(int $tid): array
    {
        $resolver = new ResolverService($this->m->standings($tid));

        // Manual entries AND the automatic derivation: without the derivation the resolver would report
        // "Losentscheidung erforderlich" for the cross-group levels that usually have no manual entry.
        $resolver->setLotOrders($this->m->lotOrders($tid));
        $resolver->setLotResolver(function (string $scope, array $tiedTpids) use ($tid): array {
            $rows = [];

            foreach ($tiedTpids as $tpid) {
                $rows[] = ['tpid' => $tpid, 'needs_lot' => true];
            }

            return $this->m->derivedLotOrder($tid, $scope, $rows);
        });

        $format = $this->m->format($tid);

        $resolvedCount = 0;
        $filled = 0;
        $advanced = 0;
        $notes = [];
        $promotedTpids = [];

        foreach ($format->bracketPhases() as $phase) {
            $phaseKey = (string) $phase['key'];
            $seedCount = count($phase['lines']['seeded']);

            // Qualify rules grouped by pool.

            $byPool = [];

            foreach ($phase['qualify'] ?? [] as $rule) {
                $pool = $rule['pool'] ?? 'default';
                $byPool[$pool][] = $rule;
            }

            $codes = [];
            $incomplete = false;

            try {
                foreach ($byPool as $pool => $rules) {
                    $from = (string) $rules[0]['from'];

                    if (! $this->m->phaseComplete($tid, $from)) {
                        $incomplete = true;
                        $notes[] = (string) __('tournament::admin.refresh.incomplete', ['phase' => $phaseKey, 'from' => $from]);

                        continue;
                    }

                    $standings = $this->m->phaseStandings($tid, $from);
                    $matches = $this->m->phaseMatches($tid, $from);

                    // The prefix of a pool is its key in capitals: pool "q" gives Q1..Qn, pool "ac" AC1..ACn.
                    // Seeded lines always carry S1..Sn.
                    $prefix = strtoupper((string) $pool);
                    $names = [];

                    foreach ($rules as $rule) {
                        $names[] = $rule['rule'];
                    }

                    if (in_array('all', $names, true)) {
                        $codes = array_merge($codes, $resolver->codesAllAdvance(
                            $standings, $matches, 'S', $prefix, $seedCount, $from));
                    } elseif (in_array('remainder', $names, true)) {
                        $codes = array_merge($codes, $resolver->codesRemainderSeeded(
                            $standings, $matches, $promotedTpids, $prefix, $seedCount, $from));
                    } else {
                        $secondsCount = 0;

                        foreach ($rules as $rule) {
                            if ($rule['rule'] === 'best_seconds') {
                                $secondsCount = isset($rule['count']) ? (int) $rule['count'] : 2;
                            }
                        }

                        $promoted = $resolver->codesPromoted($standings, $matches, $prefix, $secondsCount, $from);
                        $codes = array_merge($codes, $promoted);

                        foreach ($promoted as $entry) {
                            $promotedTpids[] = (int) $entry['tpid'];
                        }
                    }
                }
            } catch (RuntimeException $e) {
                $notes[] = sprintf('%s: %s', $phaseKey, $e->getMessage());

                continue;
            }

            if ($incomplete || ! $codes) {
                continue;
            }

            // Load and resolve the lines.

            $lines = [];

            foreach (TournamentLine::query()->where('tid', $tid)->where('phase', $phaseKey)->orderBy('line')->get() as $row) {
                $lines[(int) $row->line] = $row->getAttributes();
            }

            $resolvedLines = $resolver->resolveLines($lines, $codes, 'S');

            // Apply the swap rules published in advance. Only the bracket lines of the seed numbers are
            // swapped; the seed order itself stays.
            foreach ($phase['swap_rules'] ?? [] as $swapRule) {
                $checkLine = (int) $swapRule['if_same_group_as_line'];
                $seedA = (int) $swapRule['seed'];
                $seedB = (int) $swapRule['swap_with_seed'];
                $seedLines = [];

                foreach ($lines as $lineNo => $line) {
                    if ($line['kind'] === 'seed') {
                        $seedLines[(int) $line['seed_no']] = (int) $lineNo;
                    }
                }

                if (! isset($resolvedLines[$checkLine], $seedLines[$seedA], $seedLines[$seedB])) {
                    continue;
                }

                $players = $this->m->players($tid);
                $checkPlayer = $players[$resolvedLines[$checkLine]] ?? null;
                $seedPlayer = isset($resolvedLines[$seedLines[$seedA]], $players[$resolvedLines[$seedLines[$seedA]]])
                    ? $players[$resolvedLines[$seedLines[$seedA]]] : null;

                if ($checkPlayer && $seedPlayer && $checkPlayer['grp'] === $seedPlayer['grp']) {
                    $tmp = $resolvedLines[$seedLines[$seedA]];
                    $resolvedLines[$seedLines[$seedA]] = $resolvedLines[$seedLines[$seedB]];
                    $resolvedLines[$seedLines[$seedB]] = $tmp;
                    $notes[] = (string) __('tournament::admin.refresh.swapped', ['phase' => $phaseKey, 'a' => $seedA, 'b' => $seedB]);
                }
            }

            foreach ($resolvedLines as $lineNo => $tpid) {
                if (isset($lines[$lineNo]) && (int) $lines[$lineNo]['tpid'] === (int) $tpid) {
                    continue;
                }

                TournamentLine::query()->where('tid', $tid)->where('phase', $phaseKey)->where('line', (int) $lineNo)
                    ->update(['tpid' => (int) $tpid]);

                $lines[$lineNo]['tpid'] = (int) $tpid;
                $resolvedCount++;
            }

            // Fill round 1 from the lines.

            $pairs = $resolver->firstRoundPlayers($lines, $resolvedLines, (int) $phase['size']);

            $phaseMatches = [];

            foreach ($this->m->matches($tid) as $match) {
                if ($match['phase'] === $phaseKey) {
                    $phaseMatches[(int) $match['rnd']][(int) $match['slot']] = $match;
                }
            }

            foreach ($pairs as $slot => $pair) {
                if (! isset($phaseMatches[1][$slot])) {
                    continue;
                }

                $match = $phaseMatches[1][$slot];
                $data = [];

                if ($pair['tpid_a'] && (int) $match['tpid_a'] !== (int) $pair['tpid_a']) {
                    $data['tpid_a'] = (int) $pair['tpid_a'];
                }

                if ($pair['tpid_b'] && (int) $match['tpid_b'] !== (int) $pair['tpid_b']) {
                    $data['tpid_b'] = (int) $pair['tpid_b'];
                }

                // Bye: the sole player of the pairing advances. No result, no score, only the winner, so the
                // advance applies. An invented 6:0 6:0 would be a faked match report.
                if ($pair['walkover'] && ! $match['winner']) {
                    $only = $pair['tpid_a'] ?: $pair['tpid_b'];

                    if ($only) {
                        $data['winner'] = (int) $only;
                        $data['retired'] = 'none';
                    }
                }

                if ($data) {
                    TournamentMatch::query()->where('tmid', (int) $match['tmid'])->update($data);
                    $filled++;
                }
            }

            // Advance the winners, every round from the bottom up. Read fresh: a bye winner set just now has
            // to advance in this same run.

            $phaseMatches = [];

            foreach (TournamentMatch::query()->where('tid', $tid)->where('phase', $phaseKey)->orderBy('tmid')->get() as $fresh) {
                $phaseMatches[(int) $fresh->rnd][(int) $fresh->slot] = $fresh->getAttributes();
            }

            ksort($phaseMatches);

            foreach ($phaseMatches as $round => $slots) {
                ksort($slots);

                foreach ($slots as $slot => $match) {
                    if (! $match['winner']) {
                        continue;
                    }

                    $target = $this->generator->advance($format, $phaseKey, (int) $round, (int) $slot);

                    if (! $target) {
                        continue;
                    }

                    // Read the target fresh: an earlier iteration may have changed it.
                    $next = TournamentMatch::query()->where('tid', $tid)->where('phase', $phaseKey)
                        ->where('rnd', $target['rnd'])->where('slot', $target['slot'])->orderBy('tmid')->first();

                    if (! $next) {
                        continue;
                    }

                    $column = ($target['side'] === 'a') ? 'tpid_a' : 'tpid_b';

                    if ($next->winner && (int) $next->{$column} !== (int) $match['winner']) {
                        $notes[] = (string) __('tournament::admin.refresh.target_played', [
                            'phase' => $phaseKey, 'round' => $target['rnd'], 'slot' => $target['slot'],
                        ]);

                        continue;
                    }

                    if ((int) $next->{$column} === (int) $match['winner']) {
                        continue;
                    }

                    TournamentMatch::query()->where('tmid', (int) $next->tmid)->update([$column => (int) $match['winner']]);

                    $advanced++;
                }
            }
        }

        return [
            'resolved' => $resolvedCount,
            'filled' => $filled,
            'advanced' => $advanced,
            'notes' => array_values(array_unique($notes)),
        ];
    }
}
