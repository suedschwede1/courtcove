<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services\Admin;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\TournamentBase\Support\ActionRefused;
use Illuminate\Support\Facades\DB;

/**
 * Couples that must not be drawn into the same group (ported from ZF2 AdminController::partnerAction).
 *
 * The reference is set on BOTH sides. Otherwise the block would depend on who picks first: only the player
 * WITH the reference would get it, the other could pick freely into the partner's group. Every update is
 * scoped to the tournament, so no tpid of another tournament can be touched.
 */
final class PartnerService
{
    public function __construct(private readonly TournamentManager $m) {}

    /** @throws ActionRefused */
    public function link(Tournament $t, int $a, int $b): void
    {
        $tid = (int) $t->tid;

        if ($a <= 0 || $b <= 0 || $a === $b) {
            throw new ActionRefused('tournament::admin.partner.two_players');
        }

        if ($this->m->player($tid, $a) === null || $this->m->player($tid, $b) === null) {
            throw new ActionRefused('tournament::admin.partner.not_in_tournament');
        }

        DB::transaction(function () use ($tid, $a, $b): void {
            // Clear earlier couples on both sides first, or C -> A and D -> B would stay as orphaned blocks.
            TournamentPlayer::query()->where('tid', $tid)->where('partner_tpid', $a)->update(['partner_tpid' => null]);
            TournamentPlayer::query()->where('tid', $tid)->where('partner_tpid', $b)->update(['partner_tpid' => null]);
            TournamentPlayer::query()->where('tid', $tid)->where('tpid', $a)->update(['partner_tpid' => null]);
            TournamentPlayer::query()->where('tid', $tid)->where('tpid', $b)->update(['partner_tpid' => null]);

            TournamentPlayer::query()->where('tid', $tid)->where('tpid', $a)->update(['partner_tpid' => $b]);
            TournamentPlayer::query()->where('tid', $tid)->where('tpid', $b)->update(['partner_tpid' => $a]);
        });

        $this->m->forget($tid);
    }

    /**
     * Clears both directions, including old one-sided references.
     *
     * @throws ActionRefused
     */
    public function unlink(Tournament $t, int $tpid): void
    {
        $tid = (int) $t->tid;

        if ($tpid <= 0 || $this->m->player($tid, $tpid) === null) {
            throw new ActionRefused('tournament::admin.partner.not_in_tournament');
        }

        DB::transaction(function () use ($tid, $tpid): void {
            TournamentPlayer::query()->where('tid', $tid)->where('partner_tpid', $tpid)->update(['partner_tpid' => null]);
            TournamentPlayer::query()->where('tid', $tid)->where('tpid', $tpid)->update(['partner_tpid' => null]);
        });

        $this->m->forget($tid);
    }
}
