<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers;

use App\Models\User;
use App\Modules\Tournament\Services\FormatService;
use App\Modules\Tournament\Services\TournamentManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Seed list with expandable rows (port of IndexController::playersAction).
 *
 * Names, ITN, seed, group and record for every member; contact data (phone, e-mail from bs_users) only
 * from level PARTICIPANT on, and only then are the e-mail addresses read at all.
 */
class PlayersController extends TournamentPageController
{
    public function __construct(private readonly TournamentManager $m) {}

    public function index(Request $request): View
    {
        return $this->page($request, 'players', 'tournament::players', function (array $c): array {
            $tid = $c['tid'];
            $balance = [];

            foreach ($c['players'] as $tpid => $player) {
                $wins = 0;
                $losses = 0;
                $open = 0;

                foreach ($this->m->playerMatches($tid, (int) $tpid) as $match) {
                    if (! $match['winner']) {
                        $open++;
                    } elseif ((int) $match['winner'] === (int) $tpid) {
                        $wins++;
                    } else {
                        $losses++;
                    }
                }

                $balance[$tpid] = ['wins' => $wins, 'losses' => $losses, 'open' => $open];
            }

            $emails = [];

            if ($c['showContacts']) {
                $uids = [];

                foreach ($c['players'] as $player) {
                    if ($player['uid']) {
                        $uids[] = (int) $player['uid'];
                    }
                }

                if ($uids) {
                    foreach (User::query()->whereIn('uid', $uids)->get(['uid', 'email']) as $user) {
                        $emails[(int) $user->uid] = (string) $user->email;
                    }
                }
            }

            $groups = [];

            foreach ($c['players'] as $player) {
                if ($player['grp'] && ! in_array($player['grp'], $groups, true)) {
                    $groups[] = $player['grp'];
                }
            }

            sort($groups);

            return [
                'balance' => $balance,
                'emails' => $emails,
                'groups' => $groups,
                'competitions' => $this->competitions($c['format']),
            ];
        });
    }

    /**
     * The group phases with their seed range and group keys (ZF2 hard-coded "1-9 oberer Bewerb (A-C)";
     * here it is read from the format so it matches the draw).
     *
     * @return list<array{from: int, to: int, label: string, range: string}>
     */
    private function competitions(FormatService $format): array
    {
        $out = [];

        foreach ($format->groupPhases() as $phase) {
            if (! isset($phase['seed_range'])) {
                continue;
            }

            $keys = array_map(fn (array $g): string => (string) $g['key'], $phase['groups']);
            $out[] = [
                'from' => (int) $phase['seed_range'][0],
                'to' => (int) $phase['seed_range'][1],
                'label' => (string) $phase['label'],
                'range' => $keys ? reset($keys).'–'.end($keys) : '',
            ];
        }

        return $out;
    }
}
