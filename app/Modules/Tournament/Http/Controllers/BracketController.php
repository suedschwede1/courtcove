<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers;

use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Services\FormatService;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\Tournament\Support\RoundNames;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * KO bracket of one competition (port of IndexController::bracketAction and bracket.phtml): phase chips,
 * all rounds as columns, the line codes with their meaning, and the qualification rules.
 */
class BracketController extends TournamentPageController
{
    public function __construct(private readonly TournamentManager $m) {}

    public function show(Request $request, ?string $phase = null): View
    {
        return $this->page($request, 'bracket', 'tournament::bracket', function (array $c) use ($phase): array {
            $tid = $c['tid'];
            /** @var FormatService $format */
            $format = $c['format'];

            $phases = [];

            foreach ($format->bracketPhases() as $p) {
                $phases[$p['key']] = $p;
            }

            $active = $phase;

            if (! $active || ! isset($phases[$active])) {
                $active = array_key_first($phases);
            }

            if ($active === null) {
                return ['phases' => [], 'active' => null];
            }

            $active = (string) $active;
            $lines = [];

            foreach (TournamentLine::query()->where('tid', $tid)->where('phase', $active)->orderBy('line')->get() as $line) {
                $lines[(int) $line->line] = $line->getAttributes();
            }

            $matches = [];

            foreach ($this->m->matches($tid) as $match) {
                if ($match['phase'] === $active) {
                    $matches[(int) $match['rnd']][(int) $match['slot']] = $match;
                }
            }

            ksort($matches);
            foreach ($matches as &$roundMatches) {
                ksort($roundMatches);
            }
            unset($roundMatches);

            [$legend, $codeMeaning] = $this->legend($format, $phases[$active]);

            return [
                'phases' => $phases,
                'active' => $active,
                'phase' => $phases[$active],
                'rounds' => RoundNames::for((int) $phases[$active]['size']),
                'lines' => $lines,
                'matches' => $matches,
                'legend' => $legend,
                'codeMeaning' => $codeMeaning,
            ];
        });
    }

    /**
     * Legend of the line codes and the meaning of every single code, derived from the qualification rules
     * and pool prefixes of the format. Ranks 1..n of n groups are the group winners, n+1..2n the
     * runners-up and so on (placement beats record, the record decides within one placement).
     *
     * @param  array<string, mixed>  $phase
     * @return array{0: list<array{code: string, text: string}>, 1: array<string, string>}
     */
    private function legend(FormatService $format, array $phase): array
    {
        $codeMeaning = [];
        $legend = [];

        $ordinal = static fn (int $n): string => $n >= 1 && $n <= 6
            ? __('tournament::bracket.ordinal.'.$n)
            : __('tournament::bracket.ordinal_other', ['n' => $n]);

        $describeRank = static function (int $rank, int $groupCount, string $range) use ($ordinal): string {
            $level = (int) ceil($rank / max(1, $groupCount));
            $within = $rank - ($level - 1) * $groupCount;
            $what = $level >= 1 && $level <= 4
                ? __('tournament::bracket.level.'.$level)
                : __('tournament::bracket.level_other', ['level' => $level]);

            return __('tournament::bracket.code.rank', ['rank' => $rank, 'range' => $range, 'how' => $ordinal($within), 'what' => $what]);
        };

        $seedCount = count($phase['lines']['seeded'] ?? []);

        foreach ($phase['qualify'] ?? [] as $rule) {
            $source = $format->phase((string) $rule['from']);
            $groupKeys = [];

            foreach ($source['groups'] ?? [] as $group) {
                $groupKeys[] = (string) $group['key'];
            }

            $groupCount = count($groupKeys);
            $range = $groupKeys ? reset($groupKeys).'–'.end($groupKeys) : (string) $rule['from'];

            $poolKey = $rule['pool'] ?? null;
            $codes = $phase['lines']['pool_codes'][$poolKey] ?? null;
            $prefix = $codes ? (string) $codes['prefix'] : '?';
            $start = $codes ? (int) $codes['start'] : 1;
            $poolSize = isset($phase['lines']['pools'][$poolKey]) ? count($phase['lines']['pools'][$poolKey]) : 0;

            if ($rule['rule'] === 'all') {
                // The strongest are seeded, the rest goes into the pool.
                for ($r = 1; $r <= $seedCount; $r++) {
                    $codeMeaning['S'.$r] = $describeRank($r, $groupCount, $range);
                }

                for ($i = 0; $i < $poolSize; $i++) {
                    $codeMeaning[$prefix.($start + $i)] = $describeRank($start + $i, $groupCount, $range);
                }

                $legend[] = ['code' => 'S1–S'.max(1, $seedCount - 1), 'text' => __('tournament::bracket.legend.all_winners', ['range' => $range])];
                $legend[] = ['code' => 'S'.$seedCount, 'text' => __('tournament::bracket.legend.all_best_second', ['range' => $range])];
                $legend[] = ['code' => $prefix.$start.'–'.$prefix.($start + $poolSize - 1), 'text' => __('tournament::bracket.legend.all_rest', ['range' => $range])];
            } elseif ($rule['rule'] === 'group_winners') {
                for ($i = 0; $i < $groupCount; $i++) {
                    $codeMeaning[$prefix.($start + $i)] = __('tournament::bracket.code.winner', ['n' => $start + $i, 'range' => $range, 'how' => $ordinal($i + 1)]);
                }

                $legend[] = ['code' => $prefix.$start.'–'.$prefix.($start + $groupCount - 1), 'text' => __('tournament::bracket.legend.group_winners', ['range' => $range])];
            } elseif ($rule['rule'] === 'best_seconds') {
                $count = isset($rule['count']) ? (int) $rule['count'] : 2;
                $from = $start + $groupCount;

                for ($i = 0; $i < $count; $i++) {
                    $codeMeaning[$prefix.($from + $i)] = __('tournament::bracket.code.second', ['n' => $from + $i, 'range' => $range, 'how' => $ordinal($i + 1)]);
                }

                $legend[] = ['code' => $prefix.$from.'–'.$prefix.($from + $count - 1), 'text' => __('tournament::bracket.legend.best_seconds', ['count' => $count, 'range' => $range])];
            } elseif ($rule['rule'] === 'remainder') {
                // The number in the code is the RANK in the remaining field, not a pool number.
                for ($r = 1; $r <= $seedCount; $r++) {
                    $codeMeaning['S'.$r] = __('tournament::bracket.code.rest', ['rank' => $r, 'range' => $range]);
                }

                for ($i = 0; $i < $poolSize; $i++) {
                    $codeMeaning[$prefix.($start + $i)] = __('tournament::bracket.code.rest', ['rank' => $start + $i, 'range' => $range]);
                }

                $legend[] = ['code' => 'S1–S'.$seedCount, 'text' => __('tournament::bracket.legend.remainder_seeded', ['range' => $range])];
                $legend[] = ['code' => $prefix.$start.'–'.$prefix.($start + $poolSize - 1), 'text' => __('tournament::bracket.legend.remainder_rest', ['range' => $range])];
            }
        }

        return [$legend, $codeMeaning];
    }
}
