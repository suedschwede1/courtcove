<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services\Admin;

use App\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\TestModeSession;
use Illuminate\Support\Facades\DB;

/**
 * Create, activate and test-copy championships (ported from ZF2 AdminController::createAction,
 * activateAction and cloneTestAction). Every write runs in one transaction as in ZF2; the admin's
 * tournament selection is switched only after the commit.
 */
final class TournamentLifecycleService
{
    public function __construct(
        private readonly TournamentManager $m,
        private readonly TestModeSession $testMode,
    ) {}

    /**
     * A new tournament with the template's format and settings. Not active: a fresh tournament has no
     * checked format, no players and no draw yet - it must not be what every member sees at once. The
     * participants are copied only on request (last year's players are not entered automatically), without
     * group, position and partner (the tpid references of the old tournament would be wrong there).
     *
     * @throws ActionRefused
     */
    public function create(string $title, int $season, ?int $templateTid, bool $copyPlayers, User $by): Tournament
    {
        $title = trim($title);

        if ($title === '') {
            throw new ActionRefused('tournament::admin.lifecycle.title_required');
        }

        if ($season < 2000 || $season > 2100) {
            throw new ActionRefused('tournament::admin.lifecycle.season_range');
        }

        $source = $templateTid ? Tournament::query()->find($templateTid) : null;

        if ($templateTid && $source === null) {
            throw new ActionRefused('tournament::admin.lifecycle.template_missing');
        }

        if ($source === null) {
            throw new ActionRefused('tournament::admin.lifecycle.template_required');
        }

        $tournament = DB::transaction(function () use ($title, $season, $source, $copyPlayers): Tournament {
            $tournament = Tournament::query()->create([
                'title' => mb_substr($title, 0, 160),
                'season' => $season,
                'is_test' => 0,
                'active' => 0,
                'status' => 'setup',
                'format' => $source->format,
                'itn_default' => $source->itn_default,
                'points_per_win' => $source->points_per_win,
                'tiebreak_scope' => $source->tiebreak_scope,
                'runnerup_mode' => $source->runnerup_mode,
                'created' => now()->format('Y-m-d H:i:s'),
            ]);

            if ($copyPlayers) {
                foreach ($this->m->players((int) $source->tid) as $player) {
                    TournamentPlayer::query()->create([
                        'tid' => (int) $tournament->tid,
                        'uid' => $player['uid'],
                        'label' => $player['label'],
                        'gender' => $player['gender'],
                        'itn' => $player['itn'],
                        'itn_rated' => $player['itn_rated'],
                        'seed' => $player['seed'],
                        'phone' => $player['phone'],
                        'grp' => null,
                        'grp_pos' => null,
                        'points_adjust' => 0,
                    ]);
                }
            }

            return $tournament;
        });

        // Switch straight to editing the new tournament.
        $this->testMode->selectTid($by, (int) $tournament->tid);

        return $tournament;
    }

    /**
     * Sets one tournament active - what the members see. First all to 0, then one to 1, in one transaction:
     * without it there would be a moment without an active tournament, and current() would fall back to the
     * newest. Test tournaments cannot become active, their results are invented.
     *
     * @throws ActionRefused
     */
    public function activate(Tournament $t): void
    {
        if (! empty($t->is_test)) {
            throw new ActionRefused('tournament::admin.lifecycle.activate_test');
        }

        DB::transaction(function () use ($t): void {
            Tournament::query()->where('active', 1)->update(['active' => 0]);
            Tournament::query()->whereKey((int) $t->tid)->update(['active' => 1]);
        });

        $this->m->forget((int) $t->tid);
    }

    /**
     * Copies the tournament as a test tournament: the row with its format and every participant, but no
     * matches, lines, draws or results. The group allocation is emptied except for the seeded heads, so the
     * draw can be rehearsed. Partner references are remapped to the copies, so the partner block works in
     * the rehearsal too. The copy is selected afterwards.
     *
     * @throws ActionRefused
     */
    public function cloneTest(Tournament $t, User $by): Tournament
    {
        if (! empty($t->is_test)) {
            throw new ActionRefused('tournament::admin.lifecycle.clone_test');
        }

        $sourceTid = (int) $t->tid;

        $copy = DB::transaction(function () use ($t, $sourceTid): Tournament {
            $copy = Tournament::query()->create([
                'title' => mb_substr('TEST '.$t->title, 0, 160),
                'season' => $t->season,
                'is_test' => 1,
                'cloned_from' => $sourceTid,
                'active' => 0,
                'status' => 'setup',
                'format' => $t->format,
                'itn_default' => $t->itn_default,
                'points_per_win' => $t->points_per_win,
                'tiebreak_scope' => $t->tiebreak_scope,
                'runnerup_mode' => $t->runnerup_mode,
                'created' => now()->format('Y-m-d H:i:s'),
            ]);

            $newTid = (int) $copy->tid;
            $players = $this->m->players($sourceTid);
            $map = [];

            foreach ($players as $tpid => $player) {
                // Seeded group heads keep their place, everybody else goes back into the pot.
                $isHead = (int) $player['grp_pos'] === 1;

                $map[(int) $tpid] = (int) TournamentPlayer::query()->create([
                    'tid' => $newTid,
                    'uid' => $player['uid'],
                    'label' => $player['label'],
                    'gender' => $player['gender'],
                    'itn' => $player['itn'],
                    'itn_rated' => $player['itn_rated'],
                    'seed' => $player['seed'],
                    'grp' => $isHead ? $player['grp'] : null,
                    'grp_pos' => $isHead ? 1 : null,
                    'points_adjust' => 0,
                    'phone' => $player['phone'],
                    'note' => $player['note'],
                ])->tpid;
            }

            foreach ($players as $tpid => $player) {
                $partner = (int) $player['partner_tpid'];

                if ($partner > 0 && isset($map[$partner])) {
                    TournamentPlayer::query()->whereKey($map[(int) $tpid])->update(['partner_tpid' => $map[$partner]]);
                }
            }

            return $copy;
        });

        $this->testMode->selectTid($by, (int) $copy->tid);

        return $copy;
    }
}
