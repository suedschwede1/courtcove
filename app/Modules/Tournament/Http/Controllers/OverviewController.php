<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers;

use App\Modules\Tournament\Services\FormatService;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\Tournament\Support\NextMatch;
use App\Modules\Tournament\Support\RoundNames;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Overview and Modus pages (port of IndexController::indexAction/modeAction, repo version).
 */
class OverviewController extends TournamentPageController
{
    public function __construct(private readonly TournamentManager $m) {}

    /** Overview: next match, key figures, group progress, promotion; or the KO phase once it runs. */
    public function index(Request $request): View
    {
        return $this->page($request, 'overview', 'tournament::overview', function (array $c) use ($request): array {
            $tid = $c['tid'];

            // Key figures over all group matches.
            $counts = ['played' => 0, 'scheduled' => 0, 'open' => 0, 'total' => 0];

            foreach ($this->m->groupMatches($tid) as $match) {
                $counts['total']++;
                $counts[$this->m->matchState($match)]++;
            }

            // The viewer's next match: matches without a date first, then by date.
            $next = $c['access']->tpid ? NextMatch::of($this->m, $tid, $c['access']->tpid) : null;

            // Group phase and KO phase as two views to switch between.
            $bracket = $this->bracketOverview($tid, $c['format']);
            $koAvailable = (bool) $bracket['phases'];

            $view = (string) $request->query('ansicht', '');

            if ($view !== 'ko' && $view !== 'gruppe') {
                // Without a choice: KO as soon as the group phase is through, otherwise groups.
                $view = ($koAvailable && $counts['total'] > 0 && $counts['played'] === $counts['total'])
                    ? 'ko' : 'gruppe';
            }

            if ($view === 'ko' && ! $koAvailable) {
                $view = 'gruppe';
            }

            $progress = $this->m->progress($tid);
            $tables = [];
            foreach (array_keys($progress) as $key) {
                $tables[$key] = $this->m->table($tid, (string) $key);
            }

            return [
                'counts' => $counts,
                'progress' => $progress,
                'tables' => $tables,
                'next' => $next,
                'promotion' => $this->promotion($tid, $c['format']),
                'view' => $view,
                'koAvailable' => $koAvailable,
                'bracket' => $bracket,
                'placements' => $this->bracketPlacements($tid, $c['format']),
            ];
        });
    }

    /**
     * Modus: format and rules, derived from the format configuration and the tournament settings, so the
     * page changes with the format instead of claiming something that no longer holds.
     */
    public function mode(Request $request): View
    {
        return $this->page($request, 'mode', 'tournament::mode', function (array $c): array {
            /** @var FormatService $format */
            $format = $c['format'];
            $groupPhases = [];

            foreach ($format->groupPhases() as $phase) {
                $groups = [];

                foreach ($phase['groups'] as $group) {
                    $groups[] = [
                        'key' => $group['key'],
                        'size' => (int) $group['size'],
                        'matches' => count($format->pairings((int) $group['size'])),
                    ];
                }

                // What this group phase is for, derived from the qualification rules: seeding_all (everyone
                // advances), seeding_rest (the remaining players) and qualification (promotion).
                $purposes = [];

                foreach ($format->bracketPhases() as $bracket) {
                    $advancing = 0;
                    $kind = null;

                    foreach ($bracket['qualify'] ?? [] as $rule) {
                        if ($rule['from'] !== $phase['key']) {
                            continue;
                        }

                        if ($rule['rule'] === 'all') {
                            $kind = 'seeding_all';

                            continue;
                        }

                        if ($rule['rule'] === 'remainder') {
                            $kind = 'seeding_rest';

                            continue;
                        }

                        if ($rule['rule'] === 'group_winners') {
                            $advancing += count($phase['groups']);
                            $kind = $kind ?: 'qualification';
                        } elseif ($rule['rule'] === 'best_seconds') {
                            $advancing += isset($rule['count']) ? (int) $rule['count'] : 2;
                            $kind = $kind ?: 'qualification';
                        }
                    }

                    if (! $kind) {
                        continue;
                    }

                    $purposes[] = ['kind' => $kind, 'target' => $bracket['label'], 'advancing' => $advancing];
                }

                $groupPhases[] = [
                    'phase' => $phase,
                    'groups' => $groups,
                    'players' => array_sum(array_column($groups, 'size')),
                    'matches' => array_sum(array_column($groups, 'matches')),
                    'purposes' => $purposes,
                ];
            }

            $bracketPhases = [];

            foreach ($format->bracketPhases() as $phase) {
                $bracketPhases[] = [
                    'phase' => $phase,
                    'rounds' => RoundNames::for((int) $phase['size']),
                    'seeded' => count($phase['lines']['seeded']),
                    'byes' => count($phase['lines']['bye'] ?? []),
                ];
            }

            return [
                'matchFormat' => $format->matchFormat(),
                'ranking' => $format->ranking(),
                'runnerUpMode' => $format->runnerUpMode(),
                'groupPhases' => $groupPhases,
                'bracketPhases' => $bracketPhases,
                'playerCount' => count($c['players']),
            ];
        });
    }

    /**
     * Placements per fully played KO competition. Players who lose in the same round share the rank
     * (ex aequo). Only a complete competition gets placements.
     *
     * @return array<string, array{label: string, ranks: list<array{rank: int, tpids: list<int>}>}>
     */
    private function bracketPlacements(int $tid, FormatService $format): array
    {
        $all = $this->m->matches($tid);
        $placements = [];

        foreach ($format->bracketPhases() as $phaseDef) {
            $key = $phaseDef['key'];
            $byRound = [];
            $total = 0;
            $played = 0;

            foreach ($all as $match) {
                if ($match['phase'] !== $key || ! $match['tpid_a'] || ! $match['tpid_b']) {
                    continue;
                }

                $total++;

                if ($this->m->matchState($match) === 'played') {
                    $played++;
                }

                $byRound[(int) $match['rnd']][] = $match;
            }

            // A round whose winners are not yet carried into the next round has no matches there; without
            // the round check a played first round would look like a finished competition.
            $expectedRounds = count($format->rounds((int) $phaseDef['size']));

            if (! $total || $played !== $total || ! $byRound || max(array_keys($byRound)) !== $expectedRounds) {
                continue;
            }

            krsort($byRound);

            $ranks = [];
            $cumulative = 0;
            $first = true;

            foreach ($byRound as $roundMatches) {
                $losers = [];

                foreach ($roundMatches as $match) {
                    $winner = (int) $match['winner'];
                    $loser = ($winner === (int) $match['tpid_a']) ? (int) $match['tpid_b'] : (int) $match['tpid_a'];

                    if ($first) {
                        $ranks[] = ['rank' => 1, 'tpids' => [$winner]];
                    }

                    $losers[] = $loser;
                }

                $first = false;

                $ranks[] = ['rank' => 2 + $cumulative, 'tpids' => $losers];
                $cumulative += count($losers);
            }

            $placements[$key] = ['label' => (string) $phaseDef['label'], 'ranks' => $ranks];
        }

        return $placements;
    }

    /**
     * Key figures and current round per KO competition. Only matches with both players count.
     *
     * @return array{phases: list<array{key: string, label: string, counts: array<string, int>, round: int|null, roundLabel: string|null}>, totals: array<string, int>}
     */
    private function bracketOverview(int $tid, FormatService $format): array
    {
        $all = $this->m->matches($tid);
        $overview = [];
        $totals = ['played' => 0, 'scheduled' => 0, 'open' => 0, 'total' => 0];

        foreach ($format->bracketPhases() as $phaseDef) {
            $key = $phaseDef['key'];
            $rounds = RoundNames::for((int) $phaseDef['size']);
            $byRound = [];
            $counts = ['played' => 0, 'scheduled' => 0, 'open' => 0, 'total' => 0];
            foreach ($all as $match) {
                if ($match['phase'] !== $key || ! $match['tpid_a'] || ! $match['tpid_b']) {
                    continue;
                }

                $state = $this->m->matchState($match);
                $counts[$state]++;
                $counts['total']++;
                $totals[$state]++;
                $totals['total']++;

                $byRound[(int) $match['rnd']][] = $match;
            }

            if (! $counts['total']) {
                continue;
            }

            // Current round: the first with unplayed matches, otherwise the last.
            ksort($byRound);
            $currentRound = null;

            foreach ($byRound as $rnd => $roundMatches) {
                $done = true;

                foreach ($roundMatches as $m) {
                    if ($this->m->matchState($m) !== 'played') {
                        $done = false;
                        break;
                    }
                }

                if (! $done) {
                    $currentRound = $rnd;
                    break;
                }
            }

            if ($currentRound === null && $byRound) {
                $currentRound = max(array_keys($byRound));
            }

            $overview[] = [
                'key' => (string) $key,
                'label' => (string) $phaseDef['label'],
                'counts' => $counts,
                'round' => $currentRound,
                'roundLabel' => ($currentRound && isset($rounds[$currentRound - 1])) ? (string) $rounds[$currentRound - 1] : null,
            ];
        }

        return ['phases' => $overview, 'totals' => $totals];
    }

    /**
     * Promoted players into the main competition, as far as known: the group phase that feeds promotion
     * is read from the format, the ranking of the runners-up runs in the format's mode in the manager.
     *
     * @return array<string, mixed>
     */
    private function promotion(int $tid, FormatService $format): array
    {
        foreach ($format->groupPhases() as $phase) {
            $secondsCount = null;

            foreach ($format->bracketPhases() as $bracket) {
                foreach ($bracket['qualify'] ?? [] as $rule) {
                    if ($rule['from'] !== $phase['key']) {
                        continue;
                    }

                    if ($rule['rule'] === 'best_seconds') {
                        $secondsCount = isset($rule['count']) ? (int) $rule['count'] : 2;
                    } elseif ($rule['rule'] === 'group_winners' && $secondsCount === null) {
                        $secondsCount = 0;
                    }
                }
            }

            if ($secondsCount === null) {
                continue;
            }

            return $this->m->promotion($tid, (string) $phase['key'], $secondsCount);
        }

        return ['winners' => [], 'seconds' => [], 'seconds_count' => 0, 'pending' => [], 'standings' => [], 'matches' => []];
    }
}
