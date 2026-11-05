<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Support;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Services\FormatService;
use App\Modules\Tournament\Services\TournamentManager;
use Exception;

/**
 * Data of the championship admin page (port of ZF2 AdminController::indexAction with tournamentDraws(),
 * executedDraws() and lotDecisions()).
 *
 * The page works on the admin's selected tournament (resolved by the caller), never blindly on current():
 * otherwise the screen would show the real tournament while the actions act on the selected test copy.
 */
final class AdminDashboard
{
    public function __construct(private readonly TournamentManager $m) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Tournament $t): array
    {
        $tid = (int) $t->tid;

        // Format check first: a broken configuration should show up here, not during the tournament.
        $format = null;
        $formatError = null;
        $formatNotes = [];

        try {
            $format = $this->m->format($tid);
            $formatNotes = $format->validate();
        } catch (Exception $e) {
            $formatError = $e->getMessage();
        }

        $players = $this->m->players($tid);
        $matches = $this->m->matches($tid);
        $tournaments = $this->m->all();

        $active = null;
        foreach ($tournaments as $candidate) {
            if (! empty($candidate->active) && empty($candidate->is_test)) {
                $active = $candidate;
            }
        }

        $state = $format !== null ? $this->groupState($tid, $format, $matches) : [];
        $brackets = $format !== null ? $this->bracketState($tid, $format, $matches) : [];
        $draws = $this->tournamentDraws($tid);
        $executedDraws = $this->executedDraws($tid);
        $lots = $format !== null ? $this->lotDecisions($tid, $format) : ['decided' => [], 'open' => []];

        return [
            'tournament' => $t,
            'tid' => $tid,
            'isTest' => ! empty($t->is_test),
            'tournaments' => $tournaments,
            'activeTournament' => $active,
            'formatError' => $formatError,
            'formatNotes' => $formatNotes,
            'playerCount' => count($players),
            'matchCount' => count($matches),
            'state' => $state,
            'brackets' => $brackets,
            'draws' => $draws,
            'executedDraws' => $executedDraws,
            'lotDecisions' => $lots['decided'],
            'openLots' => $lots['open'],
            'lotOrders' => $this->m->lotOrders($tid),
            'players' => $players,
            'couples' => $this->couples($players),
            'todo' => $this->todo($draws, $executedDraws, $lots['open']),
        ];
    }

    /**
     * Group phases: per group the assigned places and the generated matches, plus the pot size.
     *
     * @param  array<int, array<string, mixed>>  $matches
     * @return list<array{phase: array<mixed>, groups: array<string, array{size: int, assigned: int, complete: bool, matches: int}>, pool: int, played: int, total: int}>
     */
    private function groupState(int $tid, FormatService $format, array $matches): array
    {
        $state = [];

        foreach ($format->groupPhases() as $phase) {
            $groups = [];
            $played = 0;
            $total = 0;

            foreach ($phase['groups'] ?? [] as $group) {
                $key = (string) $group['key'];
                $assigned = count($this->m->groupPlayers($tid, $key));
                $groupMatches = $this->m->groupMatches($tid, $key);

                $groups[$key] = [
                    'size' => (int) $group['size'],
                    'assigned' => $assigned,
                    'complete' => $assigned === (int) $group['size'],
                    'matches' => count($groupMatches),
                ];

                foreach ($groupMatches as $match) {
                    $total++;
                    if ($match['winner']) {
                        $played++;
                    }
                }
            }

            try {
                $pool = count($this->m->drawPool($tid, (string) $phase['key']));
            } catch (Exception) {
                $pool = 0;
            }

            $state[] = ['phase' => $phase, 'groups' => $groups, 'pool' => $pool, 'played' => $played, 'total' => $total];
        }

        return $state;
    }

    /**
     * Bracket phases: generated lines and matches against the expected counts.
     *
     * @param  array<int, array<string, mixed>>  $matches
     * @return list<array{phase: array<mixed>, lines: int, expected_lines: int, matches: int, expected_matches: int, played: int}>
     */
    private function bracketState(int $tid, FormatService $format, array $matches): array
    {
        $out = [];

        foreach ($format->bracketPhases() as $phase) {
            $key = (string) $phase['key'];
            $bracketMatches = 0;
            $played = 0;

            foreach ($matches as $match) {
                if ($match['phase'] === $key) {
                    $bracketMatches++;
                    if ($match['winner']) {
                        $played++;
                    }
                }
            }

            $out[] = [
                'phase' => $phase,
                'lines' => TournamentLine::query()->where('tid', $tid)->where('phase', $key)->count(),
                'expected_lines' => (int) ($phase['size'] ?? 0),
                'matches' => $bracketMatches,
                'expected_matches' => (int) ($phase['size'] ?? 0) - 1,
                'played' => $played,
            ];
        }

        return $out;
    }

    /**
     * All token draws of this tournament with their token counts, the youngest first (old tournamentDraws()):
     * the running picking phases sit with their buttons right in the admin page.
     *
     * @return list<array{draw: Draw, tokens: int, claimed: int, free: int}>
     */
    private function tournamentDraws(int $tid): array
    {
        $out = [];

        foreach (Draw::query()->where('tid', $tid)->where('mode', 'token')->orderByDesc('did')->get() as $draw) {
            $total = 0;
            $claimed = 0;

            foreach (DrawToken::query()->where('did', $draw->did)->get(['claimed_tpid']) as $token) {
                $total++;
                if ($token->claimed_tpid) {
                    $claimed++;
                }
            }

            $out[] = ['draw' => $draw, 'tokens' => $total, 'claimed' => $claimed, 'free' => $total - $claimed];
        }

        return $out;
    }

    /**
     * Executed draws that still wait for the apply step (old executedDraws()). The criterion is the
     * final_seed, not the status: a token draw is never 'executed' but 'picking' or 'closed'.
     *
     * @return list<Draw>
     */
    private function executedDraws(int $tid): array
    {
        $out = [];

        foreach (Draw::query()->where('tid', $tid)->orderBy('did')->get() as $draw) {
            if ($draw->final_seed && ! $draw->applied_at && ! $draw->discarded) {
                $out[] = $draw;
            }
        }

        return $out;
    }

    /**
     * Lot decisions per ranking context (old lotDecisions()).
     *
     * decided: ties resolved by lot (manual entry or derived from the published seed) - automatic does not
     * mean invisible, the admin has to be able to explain the order. open: ties still flagged needs_lot
     * (no executed draw yet and no manual entry), with the participant numbers for the manual form.
     *
     * @return array{decided: list<array{scope: string, title: string, order: list<string>}>, open: list<array{scope: string, title: string, players: list<array{tpid: int, label: string}>}>}
     */
    private function lotDecisions(int $tid, FormatService $format): array
    {
        $decided = [];
        $open = [];

        foreach ($format->groupPhases() as $phase) {
            foreach ($phase['groups'] ?? [] as $group) {
                $key = (string) $group['key'];

                try {
                    $table = $this->m->table($tid, $key);
                } catch (Exception) {
                    continue;
                }

                $this->collectLots($table, 'group:'.$key, (string) __('tournament::admin.lot.group_title', ['group' => $key]), $decided, $open);
            }

            // Across groups: group winners and runners-up.
            try {
                $promotion = $this->m->promotion($tid, (string) $phase['key']);
            } catch (Exception) {
                continue;
            }

            foreach (['winners' => 1, 'seconds' => 2] as $list => $place) {
                $title = $place === 1
                    ? (string) __('tournament::admin.lot.winners_title', ['phase' => (string) $phase['label']])
                    : (string) __('tournament::admin.lot.seconds_title', ['phase' => (string) $phase['label']]);

                $this->collectLots($promotion[$list] ?? [], 'phase:'.$phase['key'].':place:'.$place, $title, $decided, $open);
            }
        }

        return ['decided' => $decided, 'open' => $open];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{scope: string, title: string, order: list<string>}>  $decided
     * @param  list<array{scope: string, title: string, players: list<array{tpid: int, label: string}>}>  $open
     */
    private function collectLots(array $rows, string $scope, string $title, array &$decided, array &$open): void
    {
        $tied = [];
        $unresolved = [];

        foreach ($rows as $row) {
            // Players without a played match are "tied" only because nothing happened yet: no lot there.
            if (isset($row['played']) && (int) $row['played'] === 0) {
                continue;
            }

            if (! empty($row['needs_lot'])) {
                $unresolved[] = ['tpid' => (int) $row['tpid'], 'label' => (string) $row['label']];
            } elseif (($row['decided_by'] ?? null) === 'lot') {
                // The rows arrive resolved: the order of these entries IS the lot decision.
                $tied[] = (string) $row['label'];
            }
        }

        if (count($tied) > 1) {
            $decided[] = ['scope' => $scope, 'title' => $title, 'order' => $tied];
        }

        if (count($unresolved) > 1) {
            $open[] = ['scope' => $scope, 'title' => $title, 'players' => $unresolved];
        }
    }

    /**
     * Recorded couples, each once; a reference into nothing stays visible instead of being hidden.
     *
     * @param  array<int, array<string, mixed>>  $players
     * @return list<array{0: array<string, mixed>, 1: array<string, mixed>|null}>
     */
    private function couples(array $players): array
    {
        $couples = [];
        $seen = [];

        foreach ($players as $player) {
            $tpid = (int) $player['tpid'];

            if (empty($player['partner_tpid']) || isset($seen[$tpid])) {
                continue;
            }

            $seen[$tpid] = true;
            $partner = $players[(int) $player['partner_tpid']] ?? null;

            if ($partner !== null) {
                $seen[(int) $partner['tpid']] = true;
            }

            $couples[] = [$player, $partner];
        }

        return $couples;
    }

    /**
     * "Zu erledigen" on phones: unapplied executed draws, open lot decisions and running picking phases.
     *
     * @param  list<array{draw: Draw, tokens: int, claimed: int, free: int}>  $draws
     * @param  list<Draw>  $executedDraws
     * @param  list<array{scope: string, title: string, players: list<array{tpid: int, label: string}>}>  $openLots
     * @return list<array{tone: string, title: string, sub: string, anchor: string}>
     */
    private function todo(array $draws, array $executedDraws, array $openLots): array
    {
        $todo = [];

        foreach ($executedDraws as $draw) {
            // Only what apply accepts now: a picking draw is listed below as a running picking phase.
            if (! (($draw->mode === 'token' && $draw->status === 'closed') || ($draw->mode === 'direct' && $draw->status === 'executed'))) {
                continue;
            }

            $todo[] = ['tone' => 'problem', 'title' => (string) __('tournament::admin.todo.apply'), 'sub' => (string) $draw->title, 'anchor' => 'turnierleitung-auslosung'];
        }

        foreach ($openLots as $lot) {
            $todo[] = ['tone' => 'problem', 'title' => (string) __('tournament::admin.todo.lot'), 'sub' => $lot['title'], 'anchor' => 'turnierleitung-losentscheid'];
        }

        foreach ($draws as $entry) {
            if ($entry['draw']->status === 'picking' && ! $entry['draw']->discarded) {
                $todo[] = [
                    'tone' => 'attention',
                    'title' => (string) __('tournament::admin.todo.picking'),
                    'sub' => (string) __('tournament::admin.todo.picking_sub', ['title' => (string) $entry['draw']->title, 'claimed' => $entry['claimed'], 'tokens' => $entry['tokens']]),
                    'anchor' => 'turnierleitung-auslosung',
                ];
            }
        }

        return $todo;
    }
}
