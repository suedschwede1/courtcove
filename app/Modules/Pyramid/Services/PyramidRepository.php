<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Services;

use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidEvent;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * The single place where the pyramid is read and written (port of the old PyramidManager).
 *
 * Position shifts are NOT here: they need a transaction and the rule engine.
 * Every list carries an explicit id tie-break so equal timestamps order the same everywhere.
 */
final class PyramidRepository
{
    public function ladder(int $id): ?PyramidLadder
    {
        return PyramidLadder::query()->find($id);
    }

    /**
     * The ladder /pyramide shows without an id: the one flagged default, else the newest.
     */
    public function defaultLadder(): ?PyramidLadder
    {
        return PyramidLadder::query()->orderByDesc('is_default')->orderByDesc('id')->first();
    }

    /**
     * @return Collection<int, PyramidLadder>
     */
    public function ladders(): Collection
    {
        return PyramidLadder::query()->orderByDesc('season')->orderByDesc('id')->get();
    }

    public function player(int $id): ?PyramidPlayer
    {
        return PyramidPlayer::query()->find($id);
    }

    public function playerByUid(int $ladderId, ?int $uid): ?PyramidPlayer
    {
        if (! $uid) {
            return null;
        }

        return PyramidPlayer::query()->where('ladder_id', $ladderId)->where('uid', $uid)->first();
    }

    /**
     * @return Collection<int, PyramidPlayer>
     */
    public function ranking(int $ladderId): Collection
    {
        return PyramidPlayer::query()
            ->where('ladder_id', $ladderId)
            ->whereIn('status', ['active', 'paused'])
            ->whereNotNull('position')
            ->orderBy('position')
            ->get();
    }

    /**
     * Waiting list in the wider sense: 'waiting' and 'entering' (a released player
     * still has no position and still belongs here, only with the right to challenge).
     *
     * @return Collection<int, PyramidPlayer>
     */
    public function waitingList(int $ladderId): Collection
    {
        return PyramidPlayer::query()
            ->where('ladder_id', $ladderId)
            ->whereIn('status', ['waiting', 'entering'])
            ->orderBy('joined')
            ->orderBy('id')
            ->get();
    }

    /**
     * Ranked players, paused ones included.
     */
    public function countActive(int $ladderId): int
    {
        return $this->ranking($ladderId)->count();
    }

    /**
     * Ranked players (paused included) of every ladder in one grouped query; ladders without any are absent.
     *
     * @return array<int, int> ladder id => count
     */
    public function activeCounts(): array
    {
        return PyramidPlayer::query()
            ->whereIn('status', ['active', 'paused'])
            ->whereNotNull('position')
            ->groupBy('ladder_id')
            ->selectRaw('ladder_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'ladder_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    public function challenge(int $id): ?PyramidChallenge
    {
        return PyramidChallenge::query()->find($id);
    }

    /**
     * @return Collection<int, PyramidChallenge>
     */
    public function openChallenges(int $ladderId): Collection
    {
        return PyramidChallenge::query()
            ->where('ladder_id', $ladderId)
            ->whereIn('status', ['open', 'scheduled'])
            ->orderBy('created')
            ->orderBy('id')
            ->get();
    }

    /**
     * Running challenges (open or scheduled) of every ladder whose deadline day lies before $today,
     * oldest deadline first; ladder, challenger and opponent are loaded.
     *
     * @return Collection<int, PyramidChallenge>
     */
    public function overdueChallenges(string $today): Collection
    {
        return PyramidChallenge::query()
            ->with(['ladder', 'challenger', 'opponent'])
            ->whereIn('status', ['open', 'scheduled'])
            ->whereNotNull('deadline')
            ->where('deadline', '<', $today.' 00:00:00')
            ->orderBy('deadline')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, PyramidChallenge>
     */
    public function allChallenges(int $ladderId): Collection
    {
        return PyramidChallenge::query()
            ->where('ladder_id', $ladderId)
            ->orderByDesc('created')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, PyramidChallenge>
     */
    public function resolvedChallenges(int $ladderId, int $limit = 10): Collection
    {
        return PyramidChallenge::query()
            ->where('ladder_id', $ladderId)
            ->whereIn('status', ['played', 'forfeited'])
            ->orderByDesc('resolved')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The running challenge of a player on either side (rule 3.5 blocks both).
     */
    public function activeChallengeFor(int $playerId): ?PyramidChallenge
    {
        return $this->involving($playerId)
            ->whereIn('status', ['open', 'scheduled'])
            ->orderBy('id')
            ->first();
    }

    /**
     * Basis of the block windows (rules 4.3, 4.4), computed rather than stored so an admin correction applies at once.
     */
    public function lastResolvedChallengeFor(int $playerId): ?PyramidChallenge
    {
        return $this->involving($playerId)
            ->whereIn('status', ['played', 'forfeited'])
            ->orderByDesc('resolved')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return Collection<int, PyramidChallenge>
     */
    public function matchHistory(int $playerId, int $limit = 50): Collection
    {
        return $this->involving($playerId)
            ->whereIn('status', ['played', 'forfeited'])
            ->orderByDesc('resolved')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, PyramidEvent>
     */
    public function feed(int $ladderId, int $limit = 30): Collection
    {
        return PyramidEvent::query()
            ->where('ladder_id', $ladderId)
            ->orderByDesc('created')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addEvent(int $ladderId, string $type, array $payload, ?int $challengeId = null, ?int $actorUid = null): int
    {
        // No JSON flags on purpose: the old application reads the same rows after a rollback.
        $event = PyramidEvent::query()->create([
            'ladder_id' => $ladderId,
            'challenge_id' => $challengeId,
            'type' => $type,
            'actor_uid' => $actorUid,
            'payload' => json_encode($payload),
            'created' => now()->format('Y-m-d H:i:s'),
        ]);

        return (int) $event->id;
    }

    public function grant(int $ladderId, ?int $uid): ?PyramidGrant
    {
        if (! $uid) {
            return null;
        }

        return PyramidGrant::query()->where('ladder_id', $ladderId)->where('uid', $uid)->first();
    }

    public function addGrant(int $ladderId, int $uid): void
    {
        if ($this->grant($ladderId, $uid)) {
            return;
        }

        PyramidGrant::query()->create([
            'ladder_id' => $ladderId,
            'uid' => $uid,
            'role' => 'admin',
            'created' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function removeGrant(int $ladderId, int $uid): void
    {
        PyramidGrant::query()->where('ladder_id', $ladderId)->where('uid', $uid)->delete();
    }

    /**
     * Appends a player at the end of the ranking (rule 5.3 and the normal admission from the waiting list).
     */
    public function appendToRanking(int $playerId): int
    {
        $player = $this->player($playerId);

        if (! $player) {
            throw new RuntimeException('Spieler '.$playerId.' existiert nicht');
        }

        $last = PyramidPlayer::query()
            ->where('ladder_id', $player->ladder_id)
            ->whereNotNull('position')
            ->max('position');

        $position = $last === null ? 1 : ((int) $last) + 1;

        PyramidPlayer::query()->whereKey($playerId)->update(['position' => $position, 'status' => 'active']);

        return $position;
    }

    /**
     * Display labels of ranked and waiting players, keyed by player id.
     *
     * @return array<int, string>
     */
    public function labels(int $ladderId): array
    {
        $labels = [];

        foreach ($this->ranking($ladderId)->concat($this->waitingList($ladderId)) as $player) {
            $labels[(int) $player->id] = (string) $player->label;
        }

        return $labels;
    }

    /**
     * @return Builder<PyramidChallenge>
     */
    private function involving(int $playerId): Builder
    {
        return PyramidChallenge::query()->where(function (Builder $query) use ($playerId): void {
            $query->where('challenger_id', $playerId)->orWhere('opponent_id', $playerId);
        });
    }
}
