<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Tournament\Services\Draws\DrawApplier;
use App\Modules\Tournament\Services\GenerationService;
use App\Modules\Tournament\Services\NoCurrentTournament;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\Tournament\Support\TournamentAccess;
use App\Modules\TournamentBase\Support\ActionRefused;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Closes the picking phase of a token draw (ported from ZF2 Draw AdminController::closeAction).
 *
 * Three separate transactions, as in ZF2:
 *
 *  1. Status change and fallback assignment. The conditional `status='closed' WHERE status='picking'`
 *     comes first: once it is written, claimWhilePicking() accepts no parallel pick any more. The free
 *     tokens go to the eligible non-pickers, shuffled with the draw's final seed (reproducible, not
 *     steerable by the tournament director), respecting the partner rule where possible.
 *  2. DrawApplier::apply (ZF2 forwarded to the tournament admin's applyDraw): the players assigned by the
 *     fallback never clicked, their groups are written here. It is guarded by applied_at.
 *  3. GenerationService::generate: the group matches.
 *
 * A failure in 2 or 3 leaves the close committed and only flashes the hint to apply in the tournament
 * admin; close never reports the assignment as done when apply failed.
 */
final class DrawCloser
{
    private const SLOT_GROUP = '/^([A-Z]+)[0-9]+$/';

    public function __construct(
        private readonly TokenService $tokens,
        private readonly DrawService $draws,
        private readonly TokenClaimer $claimer,
        private readonly TournamentManager $m,
        private readonly DrawApplier $applier,
        private readonly GenerationService $generation,
        private readonly TournamentAccess $access,
    ) {}

    /**
     * @return list<array{type: string, text: string}> the flashes, in order
     *
     * @throws ActionRefused when the draw is not in the picking phase
     */
    public function close(Draw $draw, User $by): array
    {
        if (($draw->discarded !== null && $draw->discarded !== '') || $draw->status !== 'picking') {
            throw new ActionRefused('draw::admin.close.not_picking');
        }

        $did = (int) $draw->did;

        try {
            [$claimed, $assignment] = DB::transaction(fn (): array => $this->closeAndAssign($draw, $did));
        } catch (ActionRefused $e) {
            return [['type' => 'error', 'text' => (string) __($e->key, $e->replace)]];
        } catch (Exception $e) {
            // ZF2 flashed any failure of the transaction raw. The known conditions an admin can act on (more
            // non-pickers than free tokens, ...) are shown translated; anything else may carry SQL (a lock
            // wait while a pick holds the draw row) or an internal text, so it is logged and a generic text
            // is shown. Nothing was changed either way.
            $known = $this->knownMessage($e);

            if ($known === null) {
                Log::error('Closing the picking phase failed', ['did' => $did, 'exception' => $e]);

                return [['type' => 'error', 'text' => (string) __('draw::admin.close.failed')]];
            }

            return [['type' => 'error', 'text' => $known]];
        }

        $flashes = [[
            'type' => 'success',
            'text' => (string) __('draw::admin.close.success', ['picked' => count($claimed), 'assigned' => count($assignment)]),
        ]];

        // The fallback assignment still has to reach the groups (the pickers got theirs on the click).
        if ($draw->tid) {
            try {
                $fresh = Draw::query()->findOrFail($did);
                $count = $this->applier->apply($fresh, (int) $this->access->resolveTournament($by)->tid, $by);
                $flashes[] = ['type' => 'success', 'text' => (string) __('tournament::admin.apply.success', ['count' => $count])];

                $applied = Draw::query()->find($did);

                if (! $applied || ! $applied->applied_at) {
                    throw new ActionRefused('draw::admin.close.not_marked');
                }

                $generated = $this->generation->generate((int) $draw->tid);

                $flashes[] = ['type' => 'success', 'text' => (string) __('draw::admin.close.generated', ['count' => (int) $generated['created']['matches']])];
            } catch (Exception $e) {
                if ($e instanceof ActionRefused) {
                    $message = (string) __($e->key, $e->replace);
                } else {
                    $message = $this->knownMessage($e);

                    if ($message === null) {
                        Log::error('Apply or generation after closing the picking phase failed', ['did' => $did, 'exception' => $e]);
                        $message = (string) __('draw::common.internal_error');
                    }
                }
                $flashes[] = ['type' => 'error', 'text' => (string) __('draw::admin.close.apply_failed', ['message' => $message])];
            }
        }

        return $flashes;
    }

    /**
     * The service exceptions an admin can act on, translated (the services keep their ZF2 wording); null
     * for anything unknown, which must never be shown raw.
     */
    private function knownMessage(Throwable $e): ?string
    {
        if ($e instanceof NoCurrentTournament) {
            return (string) __('draw::admin.close.no_tournament');
        }

        if (! $e instanceof RuntimeException) {
            return null;
        }

        $message = $e->getMessage();

        if (preg_match('/^more players without a token \((\d+)\) than free tokens \((\d+)\) - the token count does not match the pool$/', $message, $m) === 1) {
            return (string) __('draw::admin.close.pool_mismatch', ['players' => $m[1], 'tokens' => $m[2]]);
        }

        if (preg_match('/^tokens \((\d+)\) and slots \((\d+)\) must match$/', $message, $m) === 1) {
            return (string) __('draw::admin.close.slot_mismatch', ['tokens' => $m[1], 'slots' => $m[2]]);
        }

        if (preg_match('/^tournament (\d+) has no format configuration/', $message) === 1) {
            return (string) __('draw::admin.close.no_format');
        }

        return null;
    }

    /**
     * Transaction 1.
     *
     * @return array{0: list<int>, 1: list<array{dtid: int, tpid: int, uid: ?int}>} claimed tpids, fallback assignment
     */
    private function closeAndAssign(Draw $draw, int $did): array
    {
        $changed = Draw::query()->whereKey($did)->where('status', 'picking')->update([
            'status' => 'closed',
            'closed' => now()->format('Y-m-d H:i:s'),
        ]);

        if ($changed !== 1) {
            throw new ActionRefused('draw::admin.close.parallel');
        }

        $free = [];
        $claimed = [];
        $rows = DrawToken::query()->where('did', $did)->orderBy('dtid')->get();

        foreach ($rows as $row) {
            if ($row->claimed_tpid) {
                $claimed[] = (int) $row->claimed_tpid;
            } else {
                $group = '';
                if ($row->slot && preg_match(self::SLOT_GROUP, (string) $row->slot, $slotMatch)) {
                    $group = $slotMatch[1];
                }
                $free[] = ['dtid' => (int) $row->dtid, 'position' => (int) $row->position, 'group' => $group];
            }
        }

        $tid = $draw->tid ? (int) $draw->tid : (int) $this->m->current()->tid;
        $this->m->forget($tid);
        $players = $this->m->players($tid);
        $nonPickers = [];

        foreach (DrawEligible::query()->where('did', $did)->orderBy('deid')->get() as $row) {
            $tpid = (int) $row->tpid;

            if (in_array($tpid, $claimed, true)) {
                continue;
            }

            $nonPickers[] = [
                'tpid' => $tpid,
                'uid' => isset($players[$tpid]) ? $players[$tpid]['uid'] : null,
            ];
        }

        $partnerMap = [];
        $fixedGroups = [];

        foreach ($players as $player) {
            $tpid = (int) $player['tpid'];
            if (! empty($player['partner_tpid'])) {
                $partnerMap[$tpid] = (int) $player['partner_tpid'];
            }
            if (! empty($player['grp'])) {
                $fixedGroups[$tpid] = (string) $player['grp'];
            }
        }

        // For claimed tokens the slot is the binding group, even if the player row was not updated yet.
        foreach ($rows as $row) {
            if ($row->claimed_tpid && $row->slot && preg_match(self::SLOT_GROUP, (string) $row->slot, $slotMatch)) {
                $fixedGroups[(int) $row->claimed_tpid] = $slotMatch[1];
            }
        }

        // Shuffled with the draw's final seed: already set, because the draw runs BEFORE the picking phase.
        $assignment = $this->tokens->fallbackAssignment(
            $free,
            $nonPickers,
            $draw->final_seed,
            $this->draws,
            $partnerMap,
            $fixedGroups,
        );

        foreach ($assignment as $entry) {
            // The uid comes from the raw row: cast here instead of relying on the driver returning ints.
            $uid = $entry['uid'] === null ? null : (int) $entry['uid'];

            if (! $this->claimer->claim($entry['dtid'], $entry['tpid'], $uid, 'fallback')) {
                throw new ActionRefused('draw::admin.close.claim_race');
            }
        }

        return [$claimed, $assignment];
    }
}
