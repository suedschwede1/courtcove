<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Services;

use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Support\PyramidRuleException;
use App\Modules\Pyramid\Support\Verdict;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only place that decides whether a challenge is allowed (port of the old ChallengeService).
 *
 * The block windows of rules 4.3 and 4.4 are COMPUTED from the last resolved challenge, never stored:
 * a stored "no_challenge_until" would be wrong after every admin correction of a result.
 * They are strtotime(resolved) + days * 86400 seconds, as in the old code, so a DST change
 * moves the wall-clock end by an hour exactly like it did there.
 */
final class ChallengeService
{
    public function __construct(
        private readonly PyramidRepository $repo,
        private readonly RuleService $rules,
    ) {}

    /**
     * @return Verdict|null null = the challenge is allowed
     */
    public function check(PyramidLadder $ladder, PyramidPlayer $challenger, PyramidPlayer $opponent, ?CarbonImmutable $now = null): ?Verdict
    {
        $now ??= CarbonImmutable::now();

        if ($ladder->status !== 'running') {
            return new Verdict('pyramid::check.not_running');
        }

        if ((int) $challenger->id === (int) $opponent->id) {
            return new Verdict('pyramid::check.self');
        }

        if ($opponent->status !== 'active' || $opponent->position === null) {
            return new Verdict('pyramid::check.opponent_inactive', ['name' => $opponent->label]);
        }

        // Rule 6.1: a released player enters by challenging and has no position, so the position rule does not
        // apply. Only 'entering' may do so, never 'waiting' (Spec 3.2.1): without the club's release every
        // waiting player could challenge anyone at once. The block windows below still apply.
        $isEntry = $challenger->status === 'entering';

        if (! $isEntry && $challenger->status === 'waiting') {
            return new Verdict('pyramid::check.not_released');
        }

        if (! $isEntry) {
            if ($challenger->status !== 'active' || $challenger->position === null) {
                return new Verdict('pyramid::check.not_participating');
            }

            $allowed = $this->rules->challengeable(
                (int) $challenger->position,
                $this->repo->countActive((int) $ladder->id)
            );

            if (! in_array((int) $opponent->position, $allowed, true)) {
                return new Verdict('pyramid::check.position', [
                    'position' => $challenger->position,
                    'allowed' => implode(', ', $allowed),
                ]);
            }
        }

        // Rule 3.5: an open challenge blocks both parties.
        if ($this->repo->activeChallengeFor((int) $challenger->id)) {
            return new Verdict('pyramid::check.own_open');
        }

        if ($this->repo->activeChallengeFor((int) $opponent->id)) {
            return new Verdict('pyramid::check.opponent_open', ['name' => $opponent->label]);
        }

        // Rule 4.4: the winner of a challenge is briefly protected.
        $protectedUntil = $this->blockedUntil($opponent, (int) $ladder->winner_protected_days, $now, winner: true);

        if ($protectedUntil !== null) {
            return new Verdict('pyramid::check.protected', [
                'name' => $opponent->label,
                'date' => date('d.m.Y', $protectedUntil),
            ]);
        }

        // Rules 4.3 and 4.4: a pause after a defeat.
        $cooldownUntil = $this->blockedUntil($challenger, (int) $ladder->loser_cooldown_days, $now, winner: false);

        if ($cooldownUntil !== null) {
            return new Verdict('pyramid::check.cooldown', ['date' => date('d.m.Y', $cooldownUntil)]);
        }

        return null;
    }

    /**
     * End of the block as a timestamp, or null when the player is not blocked (the end itself is free).
     */
    private function blockedUntil(PyramidPlayer $player, int $days, CarbonImmutable $now, bool $winner): ?int
    {
        $last = $this->repo->lastResolvedChallengeFor((int) $player->id);

        if (! $last || ! $last->winner_id) {
            return null;
        }

        if (((int) $last->winner_id === (int) $player->id) !== $winner) {
            return null;
        }

        $until = strtotime((string) $last->resolved) + ($days * 86400);

        return $now->getTimestamp() < $until ? $until : null;
    }

    /**
     * Creates the challenge.
     *
     * Rule 3.5 is an invariant over TWO players, and a lock cannot cover a row that does not exist yet, so the
     * guard hangs on the ladder row and then the player rows, which always exist. The player rows are locked in
     * ascending id order: that is why crossed challenges A -> B and B -> A cannot deadlock.
     *
     * @throws PyramidRuleException
     */
    public function create(PyramidLadder $ladder, PyramidPlayer $challenger, PyramidPlayer $opponent, ?int $actorUid): PyramidChallenge
    {
        return DB::transaction(function () use ($ladder, $challenger, $opponent, $actorUid): PyramidChallenge {
            PyramidLadder::query()->whereKey($ladder->id)->lockForUpdate()->first();
            $ladder = $this->repo->ladder((int) $ladder->id);

            PyramidPlayer::query()
                ->whereIn('id', [(int) $challenger->id, (int) $opponent->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $challenger = $this->repo->player((int) $challenger->id);
            $opponent = $this->repo->player((int) $opponent->id);

            if (! $ladder || ! $challenger || ! $opponent) {
                throw new PyramidRuleException(new Verdict('pyramid::check.not_running'));
            }

            $verdict = $this->check($ladder, $challenger, $opponent);

            if ($verdict !== null) {
                throw new PyramidRuleException($verdict);
            }

            $created = CarbonImmutable::now();
            $challenge = PyramidChallenge::query()->create([
                'ladder_id' => (int) $ladder->id,
                'challenger_id' => (int) $challenger->id,
                'opponent_id' => (int) $opponent->id,
                'challenger_pos' => $challenger->position === null ? null : (int) $challenger->position,
                'opponent_pos' => (int) $opponent->position,
                'status' => 'open',
                'created' => $created->format('Y-m-d H:i:s'),
                'deadline' => $created->addDays((int) $ladder->days_to_play)->format('Y-m-d'),
            ]);

            $this->repo->addEvent((int) $ladder->id, 'challenge', [
                'challenger' => (int) $challenger->id,
                'opponent' => (int) $opponent->id,
                'challenger_pos' => $challenger->position,
                'opponent_pos' => $opponent->position,
            ], (int) $challenge->id, $actorUid);

            return $challenge;
        });
    }

    /**
     * Atomic state change of a running challenge.
     *
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $payload
     * @return PyramidChallenge the row as it was BEFORE the update, like the old code
     *
     * @throws PyramidRuleException
     */
    public function transition(int $challengeId, array $changes, string $eventType, array $payload, ?int $actorUid): PyramidChallenge
    {
        $initial = $this->repo->challenge($challengeId);

        if (! $initial) {
            throw new PyramidRuleException(new Verdict('pyramid::check.challenge_missing'));
        }

        return DB::transaction(function () use ($initial, $challengeId, $changes, $eventType, $payload, $actorUid): PyramidChallenge {
            PyramidLadder::query()->whereKey($initial->ladder_id)->lockForUpdate()->first();
            $ladder = $this->repo->ladder((int) $initial->ladder_id);

            if (! $ladder || $ladder->status !== 'running') {
                throw new PyramidRuleException(new Verdict('pyramid::check.ladder_closed'));
            }

            PyramidChallenge::query()->whereKey($challengeId)->lockForUpdate()->first();
            $fresh = $this->repo->challenge($challengeId);

            if (! $fresh || ! $fresh->isRunning()) {
                throw new PyramidRuleException(new Verdict('pyramid::check.challenge_closed'));
            }

            PyramidChallenge::query()->whereKey($challengeId)->update($changes);
            $this->repo->addEvent((int) $ladder->id, $eventType, $payload, $challengeId, $actorUid);

            return $fresh;
        });
    }

    /**
     * For the pyramid view: per player either null (challengeable) or the reason why not.
     * Without a participant row the reason is "log in" for a guest and "not taking part" for a logged-in user.
     *
     * @param  Collection<int, PyramidPlayer>  $ranking
     * @return array<int, Verdict|null>
     */
    public function annotate(PyramidLadder $ladder, ?PyramidPlayer $viewer, Collection $ranking, ?CarbonImmutable $now = null, bool $loggedIn = false): array
    {
        $result = [];

        foreach ($ranking as $player) {
            $result[(int) $player->id] = $viewer
                ? $this->check($ladder, $viewer, $player, $now)
                : new Verdict($loggedIn ? 'pyramid::check.not_participating' : 'pyramid::check.login');
        }

        return $result;
    }
}
