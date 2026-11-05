<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers;

use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\Tournament\Support\NextMatch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * My matches: the viewer's (or, in the test mode, the simulated player's) matches (port of
 * IndexController::mineAction). In the test mode the page shows the simulated perspective; the write
 * rule of the match cards still applies.
 */
class MyMatchesController extends TournamentPageController
{
    public function __construct(private readonly TournamentManager $m) {}

    public function index(Request $request): View
    {
        return $this->page($request, 'mine', 'tournament::mine', function (array $c): array {
            $tid = $c['tid'];
            $tpid = $c['access']->tpid;
            $me = $tpid ? $this->m->player($tid, $tpid) : null;

            if (! $tpid || $me === null) {
                return ['me' => null, 'matches' => [], 'row' => null, 'next' => null, 'competition' => null];
            }

            $table = $me['grp'] ? $this->m->table($tid, (string) $me['grp']) : [];
            $row = null;

            foreach ($table as $candidate) {
                if ((int) $candidate['tpid'] === $tpid) {
                    $row = $candidate;
                    break;
                }
            }

            $competition = null;

            foreach ($c['format']->groupPhases() as $phase) {
                $range = $phase['seed_range'] ?? null;
                if (is_array($range) && (int) $me['seed'] >= (int) $range[0] && (int) $me['seed'] <= (int) $range[1]) {
                    $competition = (string) $phase['label'];
                    break;
                }
            }

            return [
                'me' => $me,
                'matches' => $this->m->playerMatches($tid, $tpid),
                'row' => $row,
                'next' => NextMatch::of($this->m, $tid, $tpid),
                'competition' => $competition,
            ];
        });
    }
}
