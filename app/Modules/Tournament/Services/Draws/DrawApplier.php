<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services\Draws;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\TournamentBase\Support\ActionRefused;
use Illuminate\Support\Facades\DB;

/**
 * Applies an executed draw to the tournament (ported from ZF2 Tournament AdminController::applyDrawAction):
 * a token draw writes the players' group and position, a direct draw the placeholders of the bracket
 * pool lines.
 *
 * Whether a draw ran is decided by final_seed, not by the status: a token draw goes committed -> picking
 * -> closed and is never 'executed'.
 *
 * Everything is read and validated first; before the transaction no row is changed. Inside it the row
 * updates and the conditional mark `applied_at IS NULL` run together: if a parallel apply marked the draw
 * first, not exactly one row is affected and everything rolls back (the written timestamp always differs
 * from the NULL guard, so the affected-rows count is the signal, as in ZF2).
 *
 * Not final only for beforeMark(), the test seam of the MySQL race test (ApplyRaceMysqlTest).
 */
class DrawApplier
{
    public function __construct(private readonly TournamentManager $m) {}

    /**
     * @return int the number of applied assignments
     *
     * @throws ActionRefused
     */
    public function apply(Draw $draw, int $currentTid, User $by): int
    {
        $applicableStatus = ($draw->mode === 'token' && $draw->status === 'closed')
            || ($draw->mode === 'direct' && $draw->status === 'executed');

        if (! $draw->final_seed || ! $applicableStatus || ($draw->discarded !== null && $draw->discarded !== '')
            || (int) $draw->tid !== $currentTid) {
            throw new ActionRefused('tournament::admin.apply.invalid');
        }

        if ($draw->applied_at) {
            throw new ActionRefused('tournament::admin.apply.already');
        }

        $did = (int) $draw->did;
        $updates = [];

        if ($draw->mode === 'direct') {
            $result = json_decode((string) $draw->result, true);

            if (! is_array($result)) {
                throw new ActionRefused('tournament::admin.apply.result_not_json');
            }

            foreach ($result as $slot => $placeholder) {
                if (! preg_match('/^([A-Z][A-Z0-9_]*):([0-9]+)$/', (string) $slot, $match)) {
                    throw new ActionRefused('tournament::admin.apply.slot_not_line', ['slot' => (string) $slot]);
                }

                $line = TournamentLine::query()
                    ->where('tid', $currentTid)->where('phase', $match[1])->where('line', (int) $match[2])->where('kind', 'pool')
                    ->first();

                if ($line === null) {
                    throw new ActionRefused('tournament::admin.apply.no_pool_line', ['slot' => (string) $slot]);
                }

                $updates[] = ['type' => 'line', 'id' => (int) $line->tlid, 'placeholder' => (string) $placeholder];
            }
        } elseif ($draw->mode === 'token') {
            $this->m->forget($currentTid); // read the participants fresh, not from an earlier step of the request

            foreach (DrawToken::query()->where('did', $did)->orderBy('dtid')->get() as $row) {
                if (! $row->slot || ! $row->claimed_tpid || ! preg_match('/^([A-Z]+)([0-9]+)$/', (string) $row->slot, $match)) {
                    throw new ActionRefused('tournament::admin.apply.token_incomplete', ['token' => (string) $row->token]);
                }

                if (! $this->m->player($currentTid, (int) $row->claimed_tpid)) {
                    throw new ActionRefused('tournament::admin.apply.foreign_player', ['token' => (string) $row->token]);
                }

                $updates[] = ['type' => 'player', 'id' => (int) $row->claimed_tpid, 'grp' => $match[1], 'pos' => (int) $match[2]];
            }
        } else {
            throw new ActionRefused('tournament::admin.apply.unknown_mode');
        }

        $uid = (int) $by->uid;

        DB::transaction(function () use ($updates, $currentTid, $did, $uid): void {
            foreach ($updates as $update) {
                if ($update['type'] === 'line') {
                    TournamentLine::query()->whereKey($update['id'])->update(['placeholder' => $update['placeholder']]);
                } else {
                    TournamentPlayer::query()->where('tid', $currentTid)->where('tpid', $update['id'])
                        ->update(['grp' => $update['grp'], 'grp_pos' => $update['pos']]);
                }
            }

            $this->beforeMark($did);

            $marked = Draw::query()->whereKey($did)->whereNull('applied_at')->update([
                'applied_at' => now()->format('Y-m-d H:i:s'),
                'applied_uid' => $uid,
            ]);

            if ($marked !== 1) {
                throw new ActionRefused('tournament::admin.apply.parallel');
            }
        });

        $this->m->forget($currentTid);

        return count($updates);
    }

    /** Runs inside the transaction after the row updates, right before the conditional mark. */
    protected function beforeMark(int $did): void {}
}
