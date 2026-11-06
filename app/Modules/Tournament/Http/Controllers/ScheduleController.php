<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers;

use App\Modules\Tournament\Services\TournamentManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Schedule of all matches, group and KO, with a status filter (route segment) and an area filter
 * (query `grp`, combinable with the status), port of IndexController::scheduleAction.
 */
class ScheduleController extends TournamentPageController
{
    /** Status filter slugs of ZF2; an unknown slug filters nothing (ZF2's switch without default). */
    public const FILTERS = ['alle', 'offen', 'geplant', 'ohnetermin', 'gespielt', 'meine'];

    public function __construct(private readonly TournamentManager $m) {}

    public function show(Request $request, string $filter = 'alle'): View
    {
        return $this->page($request, 'schedule', 'tournament::schedule', function (array $c) use ($request, $filter): array {
            $tid = $c['tid'];
            $tpid = $c['access']->tpid;
            $all = $this->m->matches($tid);

            // Areas: groups and KO competitions in one bar, only those with matches.
            $areas = [];
            $labels = [];

            foreach ($c['format']->groupPhases() as $phase) {
                foreach ($phase['groups'] as $groupDef) {
                    $areas[] = $groupDef['key'];
                    $labels[$groupDef['key']] = __('tournament::schedule.group', ['group' => $groupDef['key']]);
                }
            }

            foreach ($c['format']->bracketPhases() as $phase) {
                $areas[] = $phase['key'];
                $labels[$phase['key']] = (string) $phase['label'];
            }

            $present = [];

            foreach ($all as $match) {
                $key = $match['phase'] === 'group' ? $match['grp'] : $match['phase'];

                if ($key && ! in_array($key, $present, true)) {
                    $present[] = $key;
                }
            }

            $groups = [];

            foreach ($areas as $key) {
                if (in_array($key, $present, true)) {
                    $groups[$key] = $labels[$key];
                }
            }

            $group = trim(is_string($request->query('grp')) ? (string) $request->query('grp') : '');

            if ($group !== '' && ! isset($groups[$group])) {
                $group = '';
            }

            $matches = [];

            foreach ($all as $match) {
                $state = $this->m->matchState($match);

                $keep = match ($filter) {
                    'offen' => $state !== 'played',
                    'geplant' => $state === 'scheduled',
                    'ohnetermin' => $state === 'open',
                    'gespielt' => $state === 'played',
                    'meine' => $tpid && ((int) $match['tpid_a'] === $tpid || (int) $match['tpid_b'] === $tpid),
                    default => true,
                };

                $area = $match['phase'] === 'group' ? $match['grp'] : $match['phase'];

                if ($keep && $group !== '' && $area !== $group) {
                    $keep = false;
                }

                // KO matches without both players have nothing to arrange yet.
                if ($keep && $match['phase'] !== 'group' && (! $match['tpid_a'] || ! $match['tpid_b'])) {
                    $keep = false;
                }

                if ($keep) {
                    $matches[] = $match;
                }
            }

            // Newest or next date first; matches without a date go to the end in their previous order.
            $keyed = $matches;
            uksort($keyed, function (int $ia, int $ib) use ($keyed): int {
                $a = $keyed[$ia];
                $b = $keyed[$ib];
                $at = $a['played'] ? strtotime($a['played'].' '.($a['played_time'] ?: '00:00:00')) : null;
                $bt = $b['played'] ? strtotime($b['played'].' '.($b['played_time'] ?: '00:00:00')) : null;

                if ($at === null && $bt === null) {
                    return $ia <=> $ib;
                }

                if ($at === null) {
                    return 1;
                }

                if ($bt === null) {
                    return -1;
                }

                if ($at === $bt) {
                    return $ia <=> $ib;
                }

                return $bt <=> $at;
            });
            $matches = array_values($keyed);

            $counts = ['played' => 0, 'scheduled' => 0, 'open' => 0, 'total' => 0];

            foreach ($all as $match) {
                if ($match['phase'] !== 'group' && (! $match['tpid_a'] || ! $match['tpid_b'])) {
                    continue;
                }

                $counts[$this->m->matchState($match)]++;
                $counts['total']++;
            }

            return [
                'filter' => $filter,
                'filters' => $tpid ? self::FILTERS : array_slice(self::FILTERS, 0, 5),
                'group' => $group,
                'groups' => $groups,
                'matches' => $matches,
                'counts' => $counts,
            ];
        });
    }
}
