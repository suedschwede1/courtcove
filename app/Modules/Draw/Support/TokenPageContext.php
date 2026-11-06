<?php

declare(strict_types=1);

namespace App\Modules\Draw\Support;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Draw\Services\TokenEligibility;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\Tournament\Support\AccessContext;
use App\Modules\Tournament\Support\TournamentAccess;

/**
 * Who picks on the token page, and whether he may (ported from ZF2 TokenController::load).
 *
 * In the test mode the pick is possible from the simulated player's view, otherwise a draw could not be
 * rehearsed without logging in as six different members. BUT only in a test tournament: in a real one the
 * record would say "player X picked AC-03" although the tournament director did.
 */
final readonly class TokenPageContext
{
    /**
     * @param  array<string, mixed>|null  $player  the picking player's row
     * @param  array<int, true>  $blockedTokens  dtid => true, never shown
     */
    public function __construct(
        public Draw $draw,
        public int $did,
        public int $tid,
        public Tournament $tournament,
        public AccessContext $access,
        public bool $isTest,
        public ?array $player,
        public bool $canPick,
        public array $blockedTokens,
        public bool $eligible,
    ) {}

    public static function load(Draw $draw, User $user): self
    {
        $m = app(TournamentManager::class);
        $did = (int) $draw->did;

        // The draw's tournament, or the current one for a draw without tid (NoCurrentTournament when none).
        $tid = $draw->tid ? (int) $draw->tid : (int) $m->current()->tid;
        $tournament = $m->get($tid);
        $access = app(TournamentAccess::class)->context($user, $tournament);

        $isTest = ! empty($tournament->is_test);
        $simulatedPlayer = null;

        if ($access->simulation !== null && isset($access->simulation['tpid'])) {
            $simulatedPlayer = $m->player($tid, (int) $access->simulation['tpid']);
        }

        if ($isTest && $simulatedPlayer) {
            $player = $simulatedPlayer;
            $canPick = true;
        } else {
            $player = $m->playerByUid($tid, (int) $user->uid);
            $canPick = $access->canWrite;
        }

        $discarded = $draw->discarded !== null && $draw->discarded !== '';

        return new self(
            draw: $draw,
            did: $did,
            tid: $tid,
            tournament: $tournament,
            access: $access,
            isTest: $isTest,
            player: $player,
            canPick: $canPick,
            blockedTokens: app(TokenEligibility::class)->blockedTokens($did, $player, $m, $tid),
            eligible: ! $discarded
                && $canPick
                && $player !== null
                && DrawEligible::query()->where('did', $did)->where('tpid', (int) $player['tpid'])->exists(),
        );
    }
}
