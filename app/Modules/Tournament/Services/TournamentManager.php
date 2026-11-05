<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

/**
 * Link between tables, format and ranking (port of the ZF2 TournamentManager, read side).
 *
 * Controllers talk only to this manager, not to the tables. That fixes in one place what comes from the
 * database and what is calculated.
 *
 * Principle: tables, ranks and promoted players are ALWAYS calculated and never stored. Stored are only
 * participants, group allocation, dates, results and comments.
 *
 * Rows are plain arrays (Model::getAttributes()), as ZF2's (array) $row. One instance lives per request
 * (scoped binding) and keeps ZF2's per-tid cache; forget() clears it after a write.
 */
class TournamentManager
{
    /** @var array<int, array<string, mixed>> cache per tid, so a page does not read several times */
    private array $cache = [];

    public function __construct(private readonly LotOrderService $lots) {}

    // ------------------------------------------------------------------ base

    /** @throws ModelNotFoundException */
    public function get(int $tid): Tournament
    {
        if (! isset($this->cache[$tid]['tournament'])) {
            $this->cache[$tid]['tournament'] = Tournament::query()->findOrFail($tid);
        }

        return $this->cache[$tid]['tournament'];
    }

    /**
     * Manual lot results by scope (see LotOrderService).
     *
     * @return array<string, mixed>
     */
    public function lotOrders(int $tid): array
    {
        return $this->lots->lotOrders($this->get($tid));
    }

    /**
     * Lot decision for a scope: a manual entry wins, otherwise derived from the published draw seed.
     *
     * @param  array<int, array<string, mixed>>  $rows  ranking rows that may carry needs_lot
     * @return list<int>
     */
    public function lotOrder(int $tid, string $scope, array $rows = []): array
    {
        return $this->lots->lotOrder($this->get($tid), $scope, $rows);
    }

    /**
     * Lot decision derived from the published seed of the first executed draw.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<int>
     */
    public function derivedLotOrder(int $tid, string $scope, array $rows): array
    {
        return $this->lots->derivedLotOrder($this->get($tid), $scope, $rows);
    }

    /**
     * All tournaments, the youngest first (season, then tid).
     *
     * @return list<Tournament>
     */
    public function all(): array
    {
        return Tournament::query()->orderByDesc('season')->orderByDesc('tid')->get()->all();
    }

    /**
     * The current tournament: the explicitly active one first, otherwise the newest. An installation
     * without the switch keeps working, and the migration does not have to guess.
     *
     * Test tournaments (is_test = 1) are hidden here. Without that a test copy with a higher tid would take
     * over the views of all members as soon as it exists.
     *
     * @throws NoCurrentTournament
     */
    public function current(): Tournament
    {
        $row = Tournament::query()->real()
            ->orderByDesc('active')->orderByDesc('season')->orderByDesc('tid')
            ->first();

        if (! $row) {
            throw new NoCurrentTournament('no tournament configured');
        }

        return $row;
    }

    public function format(int $tid): FormatService
    {
        if (! isset($this->cache[$tid]['format'])) {
            $tournament = $this->get($tid);

            if (! $tournament->format) {
                throw new RuntimeException(sprintf(
                    'tournament %u has no format configuration - see sql/tournament_schema_04_format.sql',
                    $tid
                ));
            }

            $this->cache[$tid]['format'] = new FormatService($tournament->format);
        }

        return $this->cache[$tid]['format'];
    }

    /**
     * Ranking service, configured from the format of the tournament (as ZF2: the format decides,
     * not the tournament columns).
     */
    public function standings(int $tid): StandingsService
    {
        $format = $this->format($tid);
        $ranking = $format->ranking();

        return (new StandingsService)
            ->setPointsPerWin((int) $ranking['points_per_win'])
            ->setTiebreakScope((string) $ranking['tiebreak_scope'])
            ->setRunnerUpMode($format->runnerUpMode());
    }

    // ------------------------------------------------------------------ participants

    /**
     * All participants ordered by seed, then tpid.
     *
     * @return array<int, array<string, mixed>> tpid => row
     */
    public function players(int $tid): array
    {
        if (! isset($this->cache[$tid]['players'])) {
            $players = [];

            $rows = TournamentPlayer::query()->where('tid', $tid)->orderBy('seed')->orderBy('tpid')->get();

            foreach ($rows as $row) {
                $players[(int) $row->tpid] = $row->getAttributes();
            }

            $this->cache[$tid]['players'] = $players;
        }

        return $this->cache[$tid]['players'];
    }

    /** @return array<string, mixed>|null */
    public function player(int $tid, int $tpid): ?array
    {
        $players = $this->players($tid);

        return $players[$tpid] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function playerByUid(int $tid, int $uid): ?array
    {
        foreach ($this->players($tid) as $player) {
            if ((int) $player['uid'] === $uid) {
                return $player;
            }
        }

        return null;
    }

    /**
     * Participants of a group ordered by group position.
     *
     * @return list<array<string, mixed>>
     */
    public function groupPlayers(int $tid, string $group): array
    {
        $out = [];

        foreach ($this->players($tid) as $player) {
            if ($player['grp'] === $group) {
                $out[] = $player;
            }
        }

        usort($out, function ($a, $b) {
            $pa = $a['grp_pos'] === null ? PHP_INT_MAX : (int) $a['grp_pos'];
            $pb = $b['grp_pos'] === null ? PHP_INT_MAX : (int) $b['grp_pos'];

            if ($pa !== $pb) {
                return $pa - $pb;
            }

            return (int) $a['seed'] - (int) $b['seed'];
        });

        return $out;
    }

    /**
     * The draw pot of a group phase: participants in the seed range that are not a seeded group head.
     *
     * @return list<array<string, mixed>>
     */
    public function drawPool(int $tid, string $phaseKey): array
    {
        $phase = $this->format($tid)->phase($phaseKey);

        if (! isset($phase['seed_range'])) {
            throw new RuntimeException(sprintf('phase "%s" has no seed_range', $phaseKey));
        }

        [$from, $to] = $phase['seed_range'];
        $out = [];

        foreach ($this->players($tid) as $player) {
            $seed = (int) $player['seed'];

            if ($seed >= (int) $from && $seed <= (int) $to && $player['grp_pos'] === null) {
                $out[] = $player;
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ matches

    /**
     * All matches ordered by phase, group, slot, tmid.
     *
     * @return array<int, array<string, mixed>> tmid => row
     */
    public function matches(int $tid): array
    {
        if (! isset($this->cache[$tid]['matches'])) {
            $matches = [];

            $rows = TournamentMatch::query()->where('tid', $tid)
                ->orderBy('phase')->orderBy('grp')->orderBy('slot')->orderBy('tmid')->get();

            foreach ($rows as $row) {
                $matches[(int) $row->tmid] = $row->getAttributes();
            }

            $this->cache[$tid]['matches'] = $matches;
        }

        return $this->cache[$tid]['matches'];
    }

    /** @return list<array<string, mixed>> */
    public function groupMatches(int $tid, ?string $group = null): array
    {
        $out = [];

        foreach ($this->matches($tid) as $match) {
            if ($match['phase'] !== 'group') {
                continue;
            }

            if ($group !== null && $match['grp'] !== $group) {
                continue;
            }

            $out[] = $match;
        }

        return $out;
    }

    /**
     * Matches of one player across all phases.
     *
     * @return list<array<string, mixed>>
     */
    public function playerMatches(int $tid, int $tpid): array
    {
        $out = [];

        foreach ($this->matches($tid) as $match) {
            if ((int) $match['tpid_a'] === $tpid || (int) $match['tpid_b'] === $tpid) {
                $out[] = $match;
            }
        }

        return $out;
    }

    /**
     * State of a match according to the design handoff:
     *   'open'      no date
     *   'scheduled' date set, no result
     *   'played'    result present
     *
     * @param  array<string, mixed>  $match
     * @return 'played'|'scheduled'|'open'
     */
    public function matchState(array $match): string
    {
        if ($match['winner']) {
            return 'played';
        }

        return $match['played'] ? 'scheduled' : 'open';
    }

    // ------------------------------------------------------------------ tables

    /**
     * Group table. Always calculated, never read.
     *
     * @return list<array<string, mixed>>
     */
    public function table(int $tid, string $group): array
    {
        $players = [];

        foreach ($this->groupPlayers($tid, $group) as $player) {
            $players[] = [
                'tpid' => (int) $player['tpid'],
                'label' => $player['label'],
                'seed' => (int) $player['seed'],
                'itn_rated' => $player['itn_rated'],
                'points_adjust' => (int) $player['points_adjust'],
            ];
        }

        $rows = $this->standings($tid)->rank($players, $this->completedMatches($tid, $group));
        // Hand over the rows so that the lot decision can be derived automatically from the draw seed.
        $order = $this->lotOrder($tid, 'group:'.$group, $rows);

        return $order ? $this->standings($tid)->resolveLot($rows, $order) : $rows;
    }

    /**
     * Only scored matches: open ones must not flow into the ranking.
     *
     * @return list<array<string, mixed>>
     */
    private function completedMatches(int $tid, string $group): array
    {
        $out = [];

        foreach ($this->groupMatches($tid, $group) as $match) {
            if ($match['winner']) {
                $out[] = $match;
            }
        }

        return $out;
    }

    /** Is a group fully played? Only completed groups yield promotion candidates. */
    public function groupComplete(int $tid, string $group): bool
    {
        $matches = $this->groupMatches($tid, $group);

        if (! $matches) {
            return false;
        }

        foreach ($matches as $match) {
            if (! $match['winner']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Complete tables of all groups of a phase.
     *
     * @return array<string, list<array<string, mixed>>> grp => table from StandingsService::rank()
     */
    public function phaseStandings(int $tid, string $phaseKey): array
    {
        $phase = $this->format($tid)->phase($phaseKey);
        $out = [];

        foreach ($phase['groups'] as $group) {
            $out[$group['key']] = $this->table($tid, $group['key']);
        }

        return $out;
    }

    /**
     * All scored matches of a group phase. Needed for the cross-group comparison: drop_last has to know
     * the match against the group's last in order to drop it.
     *
     * @return list<array<string, mixed>>
     */
    public function phaseMatches(int $tid, string $phaseKey): array
    {
        $phase = $this->format($tid)->phase($phaseKey);
        $keys = [];

        foreach ($phase['groups'] as $group) {
            $keys[] = $group['key'];
        }

        $out = [];

        foreach ($this->groupMatches($tid) as $match) {
            if (in_array($match['grp'], $keys, true) && $match['winner']) {
                $out[] = $match;
            }
        }

        return $out;
    }

    /** Is a whole phase played through? */
    public function phaseComplete(int $tid, string $phaseKey): bool
    {
        $phase = $this->format($tid)->phase($phaseKey);

        foreach ($phase['groups'] as $group) {
            if (! $this->groupComplete($tid, $group['key'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Promoted players of a group phase: group winners and the best runners-up.
     *
     * Only completed groups yield candidates. The runners-up comparison runs through
     * StandingsService::rankRunnersUp() and so in the format's mode (drop_last by default), which needs
     * the complete tables and matches.
     *
     * @return array{winners: list<array<string, mixed>>, seconds: list<array<string, mixed>>, seconds_count: int, pending: list<string>, standings: array<string, list<array<string, mixed>>>, matches: list<array<string, mixed>>}
     */
    public function promotion(int $tid, string $phaseKey, int $secondsCount = 2): array
    {
        $phase = $this->format($tid)->phase($phaseKey);

        $winners = [];
        $seconds = [];
        $pending = [];
        $standings = [];

        foreach ($phase['groups'] as $group) {
            $key = $group['key'];

            if (! $this->groupComplete($tid, $key)) {
                $pending[] = $key;

                continue;
            }

            $table = $this->table($tid, $key);
            $standings[$key] = $table;

            if (isset($table[0])) {
                $winners[] = array_merge($table[0], ['grp' => $key]);
            }

            if (isset($table[1])) {
                $seconds[] = array_merge($table[1], ['grp' => $key]);
            }
        }

        $matches = $this->phaseMatches($tid, $phaseKey);
        $service = $this->standings($tid);

        $rankedWinners = $winners ? $service->rankRunnersUp($winners, $standings, $matches) : [];
        $rankedSeconds = $seconds ? $service->rankRunnersUp($seconds, $standings, $matches) : [];
        $winnerOrder = $this->lotOrder($tid, 'phase:'.$phaseKey.':place:1', $rankedWinners);
        $secondOrder = $this->lotOrder($tid, 'phase:'.$phaseKey.':place:2', $rankedSeconds);
        if ($winnerOrder) {
            $rankedWinners = $service->resolveLot($rankedWinners, $winnerOrder);
        }
        if ($secondOrder) {
            $rankedSeconds = $service->resolveLot($rankedSeconds, $secondOrder);
        }

        return [
            'winners' => $rankedWinners,
            'seconds' => $rankedSeconds,
            'seconds_count' => $secondsCount,
            'pending' => $pending,
            'standings' => $standings,
            'matches' => $matches,
        ];
    }

    /**
     * Progress per group for the overview.
     *
     * @return array<string, array{phase: string, played: int, total: int, complete: bool, leader: string|null}>
     */
    public function progress(int $tid): array
    {
        $out = [];

        foreach ($this->format($tid)->groupPhases() as $phase) {
            foreach ($phase['groups'] as $group) {
                $key = $group['key'];
                $matches = $this->groupMatches($tid, $key);
                $played = 0;

                foreach ($matches as $match) {
                    if ($match['winner']) {
                        $played++;
                    }
                }

                $table = $this->table($tid, $key);

                $out[$key] = [
                    'phase' => $phase['key'],
                    'played' => $played,
                    'total' => count($matches),
                    'complete' => ($matches && $played === count($matches)),
                    'leader' => $table ? $table[0]['label'] : null,
                ];
            }
        }

        return $out;
    }

    /** Drops everything cached for a tournament; callers do this after writing. */
    public function forget(int $tid): void
    {
        unset($this->cache[$tid]);
        $this->lots->forget($tid);
    }
}
