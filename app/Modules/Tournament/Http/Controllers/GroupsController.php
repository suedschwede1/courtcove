<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers;

use App\Modules\Tournament\Services\TournamentManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Groups: table and matches of one group (port of IndexController::groupsAction, repo version).
 */
class GroupsController extends TournamentPageController
{
    public function __construct(private readonly TournamentManager $m) {}

    public function show(Request $request, ?string $group = null): View
    {
        return $this->page($request, 'groups', 'tournament::groups', function (array $c) use ($group): array {
            $tid = $c['tid'];

            // All group keys from the format, not hard-wired.
            $groups = [];

            foreach ($c['format']->groupPhases() as $phase) {
                foreach ($phase['groups'] as $g) {
                    $groups[$g['key']] = ['phase' => $phase['key'], 'label' => $phase['label'], 'size' => (int) $g['size']];
                }
            }

            $active = $group;

            if (! $active || ! isset($groups[$active])) {
                $active = array_key_first($groups);
            }

            if ($active === null) {
                return ['groups' => [], 'active' => null, 'table' => [], 'matches' => [], 'complete' => false, 'cleanedSecond' => null];
            }

            $active = (string) $active;
            $table = $this->m->table($tid, $active);
            $matches = $this->m->groupMatches($tid, $active);
            $complete = $this->m->groupComplete($tid, $active);

            // Adjusted record of the runner-up without the match against the last of the group, for display;
            // the comparison across groups happens in TournamentManager::promotion() the same way.
            $cleanedSecond = null;

            if ($complete && $c['format']->runnerUpMode() === 'drop_last' && count($table) > 3) {
                $lastRow = $table[count($table) - 1];

                if (empty($lastRow['needs_lot'])) {
                    $ranked = $this->m->standings($tid)->rankRunnersUp(
                        [array_merge($table[1], ['grp' => $active])],
                        [$active => $table],
                        $matches
                    );

                    $cleanedSecond = $ranked[0] ?? null;
                }
            }

            return [
                'groups' => $groups,
                'active' => $active,
                'table' => $table,
                'matches' => $matches,
                'complete' => $complete,
                'cleanedSecond' => $cleanedSecond,
            ];
        });
    }
}
