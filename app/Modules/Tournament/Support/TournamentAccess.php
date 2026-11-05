<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Support;

use App\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\TournamentBase\Support\TestModeSession;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Access levels of the championship (port of ZF2's Controller/Plugin/TournamentAccess).
 *
 * Returns a level instead of throwing, so views can filter: a member may see tables but no phone numbers.
 *
 *   MEMBER       every member: pairings, results, tables, names
 *   PARTICIPANT  additionally: contact data of ALL participants, own results
 *   ADMIN        everything, overrides results
 *
 * Guests never get here (the routes require a login).
 *
 * TEST MODE: an admin can take the view of a participant or of a plain member. The simulation lives in its
 * own session key (TestModeSession), the login stays untouched. It is read-only (canWrite false) and can
 * only LOWER the level, never raise it.
 */
final class TournamentAccess
{
    public const MEMBER = 1;

    public const PARTICIPANT = 2;

    public const ADMIN = 3;

    public function __construct(
        private readonly TestModeSession $testMode,
        private readonly TournamentManager $m,
    ) {}

    public function context(User $user, Tournament $t): AccessContext
    {
        $tid = (int) $t->tid;
        $realLevel = $user->can('admin.tournament') ? self::ADMIN : self::MEMBER;
        $ownPlayer = $this->m->playerByUid($tid, (int) $user->uid);

        if ($ownPlayer !== null && $realLevel < self::PARTICIPANT) {
            $realLevel = self::PARTICIPANT;
        }

        $selectedTid = $this->testMode->selectedTid($user);
        $simulation = $this->activeSimulation($user, $tid);

        if ($simulation === null) {
            return new AccessContext(
                level: $realLevel,
                player: $ownPlayer,
                tpid: $ownPlayer !== null ? (int) $ownPlayer['tpid'] : null,
                simulation: null,
                canWrite: true,
                selectedTid: $selectedTid,
            );
        }

        // Simulated view: the level is lowered, never raised.

        if ($simulation['mode'] === 'member') {
            return new AccessContext(
                level: self::MEMBER,
                player: null,
                tpid: null,
                simulation: ['mode' => 'member', 'label' => __('tournament::common.simulation_member'), 'realLevel' => $realLevel],
                canWrite: false,
                selectedTid: $selectedTid,
            );
        }

        $player = $simulation['player'];

        return new AccessContext(
            level: self::PARTICIPANT,
            player: $player,
            tpid: (int) $player['tpid'],
            simulation: ['mode' => 'player', 'label' => (string) $player['label'], 'tpid' => (int) $player['tpid'], 'realLevel' => $realLevel],
            canWrite: false,
            selectedTid: $selectedTid,
        );
    }

    public function level(User $user, Tournament $t): int
    {
        return $this->context($user, $t)->level;
    }

    public function canWrite(User $user, Tournament $t): bool
    {
        return $this->context($user, $t)->canWrite;
    }

    /**
     * The tournament of this request: the admin's selection if it still exists, otherwise the current one.
     *
     * Order matters: resolve the tournament FIRST and the access level after it. Checking the level against
     * the current tournament and switching afterwards would look for the simulated player in the wrong
     * tournament and silently end the test mode.
     */
    public function resolveTournament(User $user): Tournament
    {
        $selected = $this->testMode->selectedTid($user);

        if ($selected !== null && $selected > 0) {
            try {
                return $this->m->get($selected);
            } catch (ModelNotFoundException) {
                // The tournament no longer exists: drop the selection silently.
                $this->testMode->selectTid($user, null);
            }
        }

        return $this->m->current();
    }

    /**
     * The simulation from the session, checked against the database. A simulated player who does not
     * (or no longer) take part in this tournament ends the simulation silently.
     *
     * @return array{mode: string, player: array<string, mixed>|null}|null
     */
    private function activeSimulation(User $user, int $tid): ?array
    {
        $state = $this->testMode->simulation($user);

        if ($state === null) {
            return null;
        }

        if ($state['mode'] === 'member') {
            return ['mode' => 'member', 'player' => null];
        }

        $player = $state['tpid'] !== null ? $this->m->player($tid, $state['tpid']) : null;

        if ($player === null) {
            $this->testMode->stop();

            return null;
        }

        return ['mode' => 'player', 'player' => $player];
    }
}
