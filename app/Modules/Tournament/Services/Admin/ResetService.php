<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services\Admin;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\TournamentBase\Support\ActionRefused;
use Illuminate\Support\Facades\DB;

/**
 * "Zurücksetzen" (port of the old AdminController::resetAction), in ZF2's step order inside ONE
 * transaction that commits at one of two places: right after the results for scope "results", otherwise at
 * the end.
 *
 *   results  every match of the tournament loses its result and confirmation (dates stay)
 *   draw     plus: everybody except the group heads back into the pot, group matches deleted, bracket lines
 *            emptied, every remaining match without players; draws of a TEST tournament are deleted with
 *            their tokens and eligible rows, draws of a REAL tournament are marked discarded and stay in the
 *            public record
 *   all      plus: every match and every bracket line deleted
 *
 * A real tournament needs the exact title as confirmation. Every statement is scoped to the tournament's
 * tid (draw children through the tournament's dids). The counts are the affected rows reported by the
 * driver, as ZF2 showed them; they are only displayed, never used as a guard.
 */
final class ResetService
{
    public const DEFAULT_REASON = 'Reset durch die Turnierleitung';

    public function __construct(private readonly TournamentManager $m) {}

    /**
     * @return list<string> the per-step lines, in order
     *
     * @throws ActionRefused unknown scope, or a real tournament without the exact title
     */
    public function reset(Tournament $t, string $scope, ?string $confirm, string $reason, User $by): array
    {
        if (! in_array($scope, ['results', 'draw', 'all'], true)) {
            throw new ActionRefused('tournament::admin.reset.unknown_scope');
        }

        $tid = (int) $t->tid;
        $isTest = ! empty($t->is_test);

        // A real tournament asks for an explicit confirmation: a misclick must not reset the championship.
        if (! $isTest && $confirm !== (string) $t->title) {
            throw new ActionRefused('tournament::admin.reset.not_test', ['title' => (string) $t->title]);
        }

        try {
            return DB::transaction(fn (): array => $this->run($tid, $isTest, $scope, $reason));
        } finally {
            $this->m->forget($tid);
        }
    }

    /** @return list<string> */
    private function run(int $tid, bool $isTest, string $scope, string $reason): array
    {
        $done = [];

        // ------------------------------------------------------------ results

        $n = TournamentMatch::query()->where('tid', $tid)->update([
            's1a' => null, 's1b' => null, 's2a' => null, 's2b' => null,
            'mtba' => null, 'mtbb' => null,
            'sets_a' => null, 'sets_b' => null, 'games_a' => null, 'games_b' => null,
            'score' => null, 'winner' => null, 'retired' => 'none',
            'entered_uid' => null, 'entered' => null,
            'confirmed_uid' => null, 'confirmed' => null,
        ]);
        $done[] = (string) __('tournament::admin.reset.done_results', ['count' => $n]);

        if ($scope === 'results') {
            return $done;
        }

        // ------------------------------------------------- allocation and draws

        // Everybody except the seeded group heads back into the pot. Excluded through the heads, not by
        // "grp_pos != 1": NULL != 1 is not true in SQL, so players already in the pot would be skipped.
        $headTpids = [];

        foreach (TournamentPlayer::query()->where('tid', $tid)->where('grp_pos', 1)->orderBy('tpid')->pluck('tpid') as $tpid) {
            $headTpids[] = (int) $tpid;
        }

        $players = TournamentPlayer::query()->where('tid', $tid);

        if ($headTpids) {
            $players->whereNotIn('tpid', $headTpids);
        }

        $n = $players->update(['grp' => null, 'grp_pos' => null, 'points_adjust' => 0]);
        $done[] = (string) __('tournament::admin.reset.done_pot', ['count' => $n, 'heads' => count($headTpids)]);

        $n = TournamentMatch::query()->where('tid', $tid)->where('phase', 'group')->delete();
        $done[] = (string) __('tournament::admin.reset.done_group_matches', ['count' => $n]);

        $n = TournamentLine::query()->where('tid', $tid)->update(['placeholder' => null, 'tpid' => null]);
        $done[] = (string) __('tournament::admin.reset.done_lines_emptied', ['count' => $n]);

        TournamentMatch::query()->where('tid', $tid)->update(['tpid_a' => null, 'tpid_b' => null]);

        $drawIds = [];

        foreach (Draw::query()->where('tid', $tid)->orderBy('did')->pluck('did') as $did) {
            $drawIds[] = (int) $did;
        }

        if ($drawIds) {
            if ($isTest) {
                foreach ($drawIds as $did) {
                    DrawToken::query()->where('did', $did)->delete();
                    DrawEligible::query()->where('did', $did)->delete();
                }

                Draw::query()->where('tid', $tid)->delete();
                $done[] = (string) __('tournament::admin.reset.done_draws_deleted', ['count' => count($drawIds)]);
            } else {
                $reason = trim($reason);

                Draw::query()->where('tid', $tid)->whereNull('discarded')->update([
                    'discarded' => now()->format('Y-m-d H:i:s'),
                    'discarded_reason' => $reason !== '' ? mb_substr($reason, 0, 160) : self::DEFAULT_REASON,
                ]);

                $done[] = (string) __('tournament::admin.reset.done_draws_discarded', ['count' => count($drawIds)]);
            }
        }

        // ---------------------------------------------------------------- all

        if ($scope === 'all') {
            $n = TournamentMatch::query()->where('tid', $tid)->delete();
            $done[] = (string) __('tournament::admin.reset.done_matches_deleted', ['count' => $n]);

            $n = TournamentLine::query()->where('tid', $tid)->delete();
            $done[] = (string) __('tournament::admin.reset.done_lines_deleted', ['count' => $n]);
        }

        return $done;
    }
}
