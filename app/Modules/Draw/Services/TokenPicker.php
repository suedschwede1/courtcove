<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

use App\Models\User;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Support\TokenPageContext;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\GenerationService;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\TournamentBase\Support\ActionRefused;
use Exception;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * A participant picks a token (ported from ZF2 TokenController::pickAction).
 *
 * Lock order as in ZF2: lock the draw row, recompute the partner rule under that lock (two partners cannot
 * both see the old state "partner has nothing yet" and take the same group), then the atomic claim that
 * also checks the draw status, commit. Only after the commit the group is written to the player row and
 * the matches are generated: the token is claimed, that is what matters; a failure there leaves the claim
 * in place and the tournament director can catch up with "anwenden".
 */
final class TokenPicker
{
    private const SLOT = '/^([A-Z]+)([0-9]+)$/';

    public function __construct(
        private readonly TokenClaimer $claimer,
        private readonly TokenEligibility $eligibility,
        private readonly TournamentManager $m,
        private readonly GenerationService $generation,
    ) {}

    /**
     * @return list<array{type: string, text: string}> the flashes (a group failure adds an error before the success)
     *
     * @throws ActionRefused
     */
    public function pick(TokenPageContext $c, int $dtid, User $by): array
    {
        if ($c->draw->discarded !== null && $c->draw->discarded !== '') {
            throw new ActionRefused('draw::tokens.pick.discarded');
        }

        if (! $c->canPick) {
            throw new ActionRefused('draw::tokens.pick.test_mode');
        }

        if ($c->draw->status !== 'picking') {
            throw new ActionRefused('draw::tokens.pick.not_open');
        }

        if ($c->player === null) {
            throw new ActionRefused('draw::tokens.pick.not_player');
        }

        if (! $c->eligible) {
            throw new ActionRefused('draw::tokens.pick.not_eligible');
        }

        $tpid = (int) $c->player['tpid'];

        // Already picked? The unique key (did, claimed_tpid) would refuse it anyway, but with a clear message.
        $this->refuseIfHolding($c->did, $tpid);

        $token = DrawToken::query()->where('did', $c->did)->whereKey($dtid)->first();

        if ($token === null) {
            throw new ActionRefused('draw::tokens.pick.unknown');
        }

        $name = (string) $token->token;

        try {
            DB::transaction(function () use ($c, $dtid, $tpid, $name, $by): void {
                $locked = $this->claimer->lockDraw($c->did);

                if (! $locked || $locked->status !== 'picking' || ! empty($locked->discarded)) {
                    throw new ActionRefused('draw::tokens.pick.closed_meanwhile');
                }

                // The partner's group may have changed since the page was built: read it again under the lock.
                $this->m->forget($c->tid);
                $blockedNow = $this->eligibility->blockedTokens($c->did, $c->player, $this->m, $c->tid);

                if (isset($blockedNow[$dtid])) {
                    throw new ActionRefused('draw::tokens.pick.blocked', ['token' => $name]);
                }

                if (! $this->claimer->claimWhilePicking($c->did, $dtid, $tpid, (int) $by->uid)) {
                    throw new ActionRefused('draw::tokens.pick.taken', ['token' => $name]);
                }
            });
        } catch (UniqueConstraintViolationException $e) {
            // A parallel request of the same player claimed another token first.
            $this->refuseIfHolding($c->did, $tpid);

            throw $e;
        }

        // Make the assignment effective at once: the draw has already run, so the slot is on the token and
        // the player learns the group right away instead of waiting for the close. Deliberately not through
        // the admin's apply-draw (a participant acts here); one targeted write is enough.
        $flashes = [];
        $group = null;
        $fresh = DrawToken::query()->whereKey($dtid)->first();

        if ($fresh && $fresh->slot && preg_match(self::SLOT, (string) $fresh->slot, $parts)) {
            $group = $parts[1];

            try {
                TournamentPlayer::query()->whereKey($tpid)->update(['grp' => $parts[1], 'grp_pos' => (int) $parts[2]]);

                // Matches appear once both players are known; GenerationService skips incomplete pairs.
                $this->generation->generate($c->tid);
            } catch (Exception $e) {
                $flashes[] = ['type' => 'error', 'text' => (string) __('draw::tokens.pick.group_failed', ['message' => $e->getMessage()])];
            }
        }

        $flashes[] = $group !== null
            ? ['type' => 'success', 'text' => (string) __('draw::tokens.pick.success_group', ['token' => $name, 'group' => $group])]
            : ['type' => 'success', 'text' => (string) __('draw::tokens.pick.success', ['token' => $name])];

        return $flashes;
    }

    private function refuseIfHolding(int $did, int $tpid): void
    {
        $existing = DrawToken::query()->where('did', $did)->where('claimed_tpid', $tpid)->first();

        if ($existing !== null) {
            throw new ActionRefused('draw::tokens.pick.already', ['token' => (string) $existing->token]);
        }
    }
}
