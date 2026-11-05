<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Services;

use App\Models\User;
use App\Models\UserMeta;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Support\EventText;
use App\Modules\Pyramid\Support\PyramidRuleException;
use App\Modules\Pyramid\Support\Verdict;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Everything an administrator does to a ladder (port of the old AdminController actions and helpers).
 *
 * Every write that touches positions takes the ladder row first and then all ladder players ORDER BY id, the same
 * order ResultService uses, and writes positions NULL first, then the new values, changed rows only. User errors are
 * PyramidRuleExceptions with a `pyramid::admin.*` key; the stored feed texts stay German (EventText).
 */
final class LadderAdminService
{
    public function __construct(
        private readonly PyramidRepository $repo,
        private readonly RuleService $rules,
        private readonly SeedService $seeds,
        private readonly ResultService $results,
        private readonly ChallengeService $challenges,
        private readonly ScheduleParser $parser,
    ) {}

    /**
     * @throws PyramidRuleException
     */
    public function createLadder(string $title, int $season, ?string $category): PyramidLadder
    {
        $title = trim($title);

        if ($title === '') {
            throw $this->error('title_required');
        }

        return PyramidLadder::query()->create([
            'title' => $title,
            'season' => $season,
            'category' => trim((string) $category) ?: null,
            'status' => 'setup',
            'itn_default' => 10.0,
            'days_to_play' => 7,
            'winner_protected_days' => 3,
            'loser_cooldown_days' => 7,
            'is_default' => 0,
            'created' => $this->now(),
        ]);
    }

    /**
     * Seeds all waiting players by ITN and starts the ladder; only while nobody holds a position.
     *
     * @return int number of seeded players
     *
     * @throws PyramidRuleException
     */
    public function seed(PyramidLadder $ladder, ?int $actorUid): int
    {
        return DB::transaction(function () use ($ladder, $actorUid): int {
            $fresh = $this->lock((int) $ladder->id);

            if ($this->repo->ranking((int) $fresh->id)->isNotEmpty()) {
                throw $this->error('has_ranking');
            }

            $waiting = $this->repo->waitingList((int) $fresh->id);

            if ($waiting->isEmpty()) {
                throw $this->error('no_players');
            }

            $seeded = $this->seeds->seed($waiting->map(fn (PyramidPlayer $player): array => $player->toArray())->all(), $fresh->itn_default);

            foreach ($seeded as $player) {
                PyramidPlayer::query()->whereKey((int) $player['id'])->update([
                    'position' => (int) $player['position'],
                    'itn_rated' => $player['itn_rated'],
                    'status' => 'active',
                ]);
            }

            PyramidLadder::query()->whereKey($fresh->id)->update(['status' => 'running']);
            $this->repo->addEvent((int) $fresh->id, 'admin', ['message' => EventText::SEEDED], null, $actorUid);

            return count($seeded);
        });
    }

    /**
     * Adds a participant to the waiting list. An e-mail that matches a real account wins over the typed name,
     * uid and phone: the browser preview is only a convenience, this is the actual resolution.
     *
     * @param  array<string, mixed>  $input  label, uid, phone, email, itn, gender, age
     *
     * @throws PyramidRuleException
     */
    public function addPlayer(PyramidLadder $ladder, array $input): PyramidPlayer
    {
        $label = trim((string) ($input['label'] ?? ''));
        $uid = (int) ($input['uid'] ?? 0);
        $phone = trim((string) ($input['phone'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $email = $email === '' ? null : $email;

        if ($email !== null) {
            $member = User::query()
                ->where('email', $email)
                ->whereNotIn('status', ['deleted', 'disabled'])
                ->orderBy('uid')
                ->first();

            if ($member) {
                $uid = (int) $member->uid;
                $label = (string) $member->alias;
                $memberPhone = UserMeta::query()->where('uid', $member->uid)->where('key', 'phone')->value('value');
                $phone = $memberPhone === null ? $phone : (string) $memberPhone;
            }
        }

        if ($uid > 0 && $this->repo->playerByUid((int) $ladder->id, $uid)) {
            throw $this->error('already_participant');
        }

        if ($label === '') {
            throw $this->error('label_required');
        }

        $itn = $this->itn($input['itn'] ?? '');

        return PyramidPlayer::query()->create([
            'ladder_id' => (int) $ladder->id,
            'uid' => $uid > 0 ? $uid : null,
            'label' => $label,
            'itn' => $itn,
            'itn_rated' => $itn ?? (float) $ladder->itn_default,
            'position' => null,
            'status' => 'waiting',
            'gender' => $this->gender($input['gender'] ?? ''),
            'age' => $this->age($input['age'] ?? 0),
            'phone' => $phone === '' ? null : $phone,
            'email' => $email,
            'joined' => $this->now(),
        ])->refresh();
    }

    /**
     * Takes a waiting player in at the bottom WITHOUT a match.
     *
     * @return int the new position
     *
     * @throws PyramidRuleException
     */
    public function admit(PyramidPlayer $player, string $itn, ?int $actorUid): int
    {
        return DB::transaction(function () use ($player, $itn, $actorUid): int {
            $ladder = $this->lock((int) $player->ladder_id);
            $current = $this->repo->player((int) $player->id);

            if (! $current || $current->status !== 'waiting') {
                throw $this->error('not_waiting');
            }

            $itn = trim($itn);

            if ($itn !== '') {
                $rating = (float) str_replace(',', '.', $itn);
                PyramidPlayer::query()->whereKey($current->id)->update(['itn' => $rating, 'itn_rated' => $rating]);
            }

            $position = $this->repo->appendToRanking((int) $current->id);
            $this->repo->addEvent((int) $ladder->id, 'join', [
                'player' => (int) $current->id,
                'position' => $position,
            ], null, $actorUid);

            return $position;
        });
    }

    /**
     * Releases a waiting player to challenge (rule 6.1) or takes the release back.
     *
     * @return string the new status, 'entering' or 'waiting'
     *
     * @throws PyramidRuleException
     */
    public function releaseEntry(PyramidPlayer $player, bool $release, ?int $actorUid): string
    {
        return DB::transaction(function () use ($player, $release, $actorUid): string {
            $ladder = $this->lock((int) $player->ladder_id);
            $current = $this->repo->player((int) $player->id);

            if (! $current || ! in_array($current->status, ['waiting', 'entering'], true)) {
                throw $this->error('not_waiting');
            }

            // Both directions: create() never changes the player status, so an unchecked withdrawal would leave
            // an open challenge pointing at a player who is back on 'waiting'.
            if ($this->repo->activeChallengeFor((int) $current->id)) {
                throw $this->error('entry_busy');
            }

            $target = $release ? 'entering' : 'waiting';
            PyramidPlayer::query()->whereKey($current->id)->update(['status' => $target]);

            if ($release) {
                $this->repo->addEvent((int) $ladder->id, 'admin', [
                    'message' => EventText::make(EventText::RELEASED, [':name' => $current->label]),
                ], null, $actorUid);
            }

            return $target;
        });
    }

    /**
     * Pause, reactivate, remove (rule 5.3: a removed player loses the position and returns at the end).
     *
     * @throws InvalidArgumentException on an unknown status
     */
    public function setStatus(PyramidPlayer $player, string $status, ?int $actorUid): void
    {
        if (! in_array($status, ['active', 'paused', 'removed', 'waiting'], true)) {
            throw new InvalidArgumentException('Unbekannter Status.');
        }

        DB::transaction(function () use ($player, $status, $actorUid): void {
            $ladder = $this->lock((int) $player->ladder_id);
            $current = $this->repo->player((int) $player->id);

            if (! $current) {
                return;
            }

            if ($status === 'removed' || $status === 'waiting') {
                PyramidPlayer::query()->whereKey($current->id)->update(['status' => $status, 'position' => null]);
                $this->normalize($ladder, $actorUid);

                return;
            }

            if ($status === 'active' && $current->position === null) {
                $this->repo->appendToRanking((int) $current->id);

                return;
            }

            PyramidPlayer::query()->whereKey($current->id)->update(['status' => $status]);
        });
    }

    /**
     * Master data and, as an intervention under rule 7.3, the position.
     *
     * @param  array<string, mixed>  $input  label, itn, gender, age, phone, note
     *
     * @throws PyramidRuleException
     */
    public function updatePlayer(PyramidPlayer $player, array $input, int $targetPosition): void
    {
        $label = trim((string) ($input['label'] ?? $player->label));

        if ($label === '') {
            throw $this->error('label_required');
        }

        DB::transaction(function () use ($player, $input, $label, $targetPosition): void {
            $ladder = $this->lock((int) $player->ladder_id);
            $current = $this->repo->player((int) $player->id);

            if (! $current) {
                return;
            }

            $itn = $this->itn($input['itn'] ?? '');

            PyramidPlayer::query()->whereKey($current->id)->update([
                'label' => $label,
                'itn' => $itn,
                'itn_rated' => $itn ?? (float) $ladder->itn_default,
                'gender' => $this->gender($input['gender'] ?? ''),
                'age' => $this->age($input['age'] ?? 0),
                'phone' => trim((string) ($input['phone'] ?? '')) ?: null,
                'note' => trim((string) ($input['note'] ?? '')) ?: null,
            ]);

            if ($targetPosition > 0 && $current->position !== null && $targetPosition !== (int) $current->position) {
                $this->movePlayer($ladder, $current, $targetPosition);
            }
        });
    }

    /**
     * Moves a player by hand (rule 7.3); everything in between closes up like after a regular result.
     */
    public function movePlayer(PyramidLadder $ladder, PyramidPlayer $player, int $target): void
    {
        DB::transaction(function () use ($ladder, $player, $target): void {
            $this->lock((int) $ladder->id);

            $current = [];

            foreach ($this->repo->ranking((int) $ladder->id) as $row) {
                $current[(int) $row->id] = (int) $row->position;
            }

            $playerId = (int) $player->id;

            if (! isset($current[$playerId])) {
                return;
            }

            $from = $current[$playerId];
            $target = max(1, min($target, count($current)));

            if ($target === $from) {
                return;
            }

            if ($target < $from) {
                $updated = $this->rules->shiftForWin($current, $from, $target);
            } else {
                // Backwards: the player leaves, everyone from from+1 to target moves up one place.
                $updated = [];

                foreach ($current as $id => $position) {
                    if ($id === $playerId) {
                        $updated[$id] = $target;
                    } elseif ($position > $from && $position <= $target) {
                        $updated[$id] = $position - 1;
                    } else {
                        $updated[$id] = $position;
                    }
                }
            }

            $this->persist($current, $updated);
        });
    }

    /**
     * Checks the ranking invariant: the unique index only prevents duplicates, not gaps and not active players
     * without a position (NULL is allowed any number of times).
     *
     * @return list<Verdict>
     */
    public function diagnose(PyramidLadder $ladder): array
    {
        $ladderId = (int) $ladder->id;
        $problems = [];

        $orphans = PyramidPlayer::query()
            ->where('ladder_id', $ladderId)
            ->whereIn('status', ['active', 'paused'])
            ->whereNull('position')
            ->count();

        if ($orphans > 0) {
            $problems[] = new Verdict('pyramid::admin.problem_orphans', ['count' => $orphans]);
        }

        $stray = PyramidPlayer::query()
            ->where('ladder_id', $ladderId)
            ->whereIn('status', ['waiting', 'removed'])
            ->whereNotNull('position')
            ->count();

        if ($stray > 0) {
            $problems[] = new Verdict('pyramid::admin.problem_stray', ['count' => $stray]);
        }

        $positions = $this->repo->ranking($ladderId)->map(fn (PyramidPlayer $player): int => (int) $player->position)->sort()->values()->all();

        if ($positions !== ($positions === [] ? [] : range(1, count($positions)))) {
            $problems[] = new Verdict('pyramid::admin.problem_gaps');
        }

        return $problems;
    }

    /**
     * Brings the positions to a gapless 1..n and keeps the order. The diagnosis goes to the feed first: a silent
     * repair would hide exactly the fault somebody is looking for.
     */
    public function normalize(PyramidLadder $ladder, ?int $actorUid): void
    {
        DB::transaction(function () use ($ladder, $actorUid): void {
            $fresh = $this->lock((int) $ladder->id);
            $problems = $this->diagnose($fresh);

            if ($problems !== []) {
                $this->repo->addEvent((int) $fresh->id, 'admin', [
                    'message' => EventText::make(EventText::NORMALIZED, [
                        ':problems' => implode('; ', array_map(fn (Verdict $verdict): string => $verdict->message('de'), $problems)),
                    ]),
                ], null, $actorUid);
            }

            $ranking = $this->repo->ranking((int) $fresh->id);

            foreach ($ranking as $player) {
                PyramidPlayer::query()->whereKey($player->id)->update(['position' => null]);
            }

            $position = 1;

            foreach ($ranking as $player) {
                PyramidPlayer::query()->whereKey($player->id)->update(['position' => $position++]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $input  days_to_play, winner_protected_days, loser_cooldown_days, title, category, is_default
     *
     * @throws PyramidRuleException
     */
    public function configure(PyramidLadder $ladder, array $input): void
    {
        $data = [];

        foreach (['days_to_play', 'winner_protected_days', 'loser_cooldown_days'] as $field) {
            $value = (int) ($input[$field] ?? 0);

            if ($value < 0 || $value > 365) {
                throw $this->error('days_range');
            }

            $data[$field] = $value;
        }

        $data['title'] = trim((string) ($input['title'] ?? $ladder->title));
        $data['category'] = trim((string) ($input['category'] ?? '')) ?: null;
        $data['is_default'] = ! empty($input['is_default']) ? 1 : 0;

        DB::transaction(function () use ($ladder, $data): void {
            // Only one default ladder, otherwise the sort order decides which one /pyramide shows.
            if ($data['is_default'] === 1) {
                PyramidLadder::query()->update(['is_default' => 0]);
            }

            PyramidLadder::query()->whereKey($ladder->id)->update($data);
        });
    }

    /**
     * Year change: copies master data of ranked and waiting players as waiting players, never positions and
     * never the e-mail.
     *
     * @return int number of copied players
     *
     * @throws PyramidRuleException
     */
    public function copyPlayers(PyramidLadder $target, ?PyramidLadder $source): int
    {
        if (! $source || (int) $source->id === (int) $target->id) {
            throw $this->error('copy_source');
        }

        try {
            return DB::transaction(function () use ($target, $source): int {
                $fresh = $this->lock((int) $target->id);
                $existing = [];

                foreach ($this->repo->ranking((int) $fresh->id)->concat($this->repo->waitingList((int) $fresh->id)) as $player) {
                    $existing[mb_strtolower((string) $player->label)] = true;
                }

                $copied = 0;

                foreach ($this->repo->ranking((int) $source->id)->concat($this->repo->waitingList((int) $source->id)) as $player) {
                    $key = mb_strtolower((string) $player->label);

                    if ($player->status === 'removed' || isset($existing[$key])) {
                        continue;
                    }

                    PyramidPlayer::query()->create([
                        'ladder_id' => (int) $fresh->id,
                        'uid' => $player->uid === null ? null : (int) $player->uid,
                        'label' => $player->label,
                        'itn' => $player->itn,
                        'itn_rated' => $player->itn === null ? (float) $fresh->itn_default : (float) $player->itn,
                        'position' => null,
                        'status' => 'waiting',
                        'gender' => $player->gender,
                        'age' => $player->age,
                        'phone' => $player->phone,
                        'joined' => $this->now(),
                    ]);

                    $existing[$key] = true;
                    $copied++;
                }

                return $copied;
            });
        } catch (UniqueConstraintViolationException) {
            // The same member under another label: the whole copy is rolled back, a half copy is worse than none.
            throw $this->error('copy_failed');
        }
    }

    public function close(PyramidLadder $ladder, ?int $actorUid): void
    {
        DB::transaction(function () use ($ladder, $actorUid): void {
            PyramidLadder::query()->whereKey($ladder->id)->lockForUpdate()->first();
            PyramidLadder::query()->whereKey($ladder->id)->update(['status' => 'closed']);
            $this->repo->addEvent((int) $ladder->id, 'admin', ['message' => EventText::CLOSED], null, $actorUid);
        });
    }

    /**
     * Extends the deadline of a running challenge by at most 28 days beyond the old one.
     *
     * The limit is ZF2's exact comparison: the new date counts up to 23:59, the old deadline from 00:00, so the
     * latest accepted date is old + 27 days.
     *
     * @return string the new deadline, Y-m-d
     *
     * @throws PyramidRuleException
     */
    public function extendDeadline(PyramidChallenge $challenge, string $rawDate, string $note, ?int $actorUid, ?CarbonImmutable $now = null): string
    {
        $note = $this->requireNote($note);
        $now ??= CarbonImmutable::now();
        $when = $this->parser->forDeadline($rawDate, $now);

        if ($when instanceof Verdict) {
            throw new PyramidRuleException($when);
        }

        $current = $this->repo->challenge((int) $challenge->id) ?? $challenge;
        $oldDeadline = $current->deadline ? strtotime((string) $current->deadline) : $now->getTimestamp();

        if ($when > strtotime('+28 days', $oldDeadline)) {
            throw $this->error('extend_limit');
        }

        $newDeadline = date('Y-m-d', $when);

        $this->challenges->transition((int) $challenge->id, ['deadline' => $newDeadline], 'admin', [
            'message' => EventText::make(EventText::EXTENDED, [':date' => $newDeadline, ':note' => $note]),
        ], $actorUid);

        return $newDeadline;
    }

    /**
     * Closes a challenge without a match (rule 3.4).
     *
     * @throws PyramidRuleException
     */
    public function expire(PyramidChallenge $challenge, string $note, ?int $actorUid, ?CarbonImmutable $now = null): void
    {
        $note = $this->requireNote($note);

        $this->challenges->transition((int) $challenge->id, [
            'status' => 'expired',
            'admin_note' => $note,
            'resolved' => ($now ?? CarbonImmutable::now())->format('Y-m-d H:i:s'),
        ], 'admin', [
            'message' => EventText::make(EventText::EXPIRED, [':note' => $note]),
        ], $actorUid);
    }

    /**
     * Rule 5.1: same effect on the ranking as a played match, hence ResultService.
     *
     * @throws PyramidRuleException
     */
    public function forfeit(PyramidChallenge $challenge, bool $challengerWon, string $note, ?int $actorUid): void
    {
        $note = $this->requireNote($note);
        $ladder = $this->repo->ladder((int) $challenge->ladder_id);

        if (! $ladder) {
            throw new PyramidRuleException(new Verdict('pyramid::check.ladder_closed'));
        }

        $this->results->recordForfeit($ladder, $challenge, $challengerWon, $note, $actorUid);
    }

    /**
     * Corrects a recorded result (score, winner, note). The ranking is deliberately NOT recalculated: once further
     * challenges were played the earlier shift can no longer be isolated (rule 3.8); the admin fixes positions by hand.
     *
     * @throws PyramidRuleException
     * @throws InvalidArgumentException when the winner is not one of the two participants
     */
    public function correctResult(PyramidChallenge $challenge, int $winnerId, string $score, string $note, ?int $actorUid): void
    {
        DB::transaction(function () use ($challenge, $winnerId, $score, $note, $actorUid): void {
            PyramidLadder::query()->whereKey($challenge->ladder_id)->lockForUpdate()->first();
            PyramidChallenge::query()->whereKey($challenge->id)->lockForUpdate()->first();
            $current = $this->repo->challenge((int) $challenge->id);

            if (! $current) {
                throw new PyramidRuleException(new Verdict('pyramid::check.challenge_missing'));
            }

            if (! in_array($current->status, ['played', 'forfeited'], true)) {
                throw $this->error('correct_unresolved');
            }

            if ($winnerId !== (int) $current->challenger_id && $winnerId !== (int) $current->opponent_id) {
                throw new InvalidArgumentException('Der Sieger muss einer der beiden Beteiligten sein.');
            }

            $note = trim($note);

            if ($note === '') {
                throw $this->error('note_required');
            }

            PyramidChallenge::query()->whereKey($current->id)->update([
                'score' => trim($score) ?: null,
                'winner_id' => $winnerId,
                'admin_note' => $note,
            ]);

            $this->repo->addEvent((int) $current->ladder_id, 'admin', [
                'message' => EventText::make(EventText::CORRECTED, [':note' => $note]),
            ], (int) $current->id, $actorUid);
        });
    }

    /**
     * Ladder row first, then all its players ORDER BY id (the order ResultService uses); returns the current ladder.
     */
    private function lock(int $ladderId): PyramidLadder
    {
        PyramidLadder::query()->whereKey($ladderId)->lockForUpdate()->first();
        $ladder = $this->repo->ladder($ladderId);

        if (! $ladder) {
            throw new PyramidRuleException(new Verdict('pyramid::check.ladder_closed'));
        }

        PyramidPlayer::query()->where('ladder_id', $ladderId)->orderBy('id')->lockForUpdate()->get(['id']);

        return $ladder;
    }

    /**
     * NULL first (UNIQUE (ladder_id, position) is checked row by row), then the new values, changed rows only.
     *
     * @param  array<int, int>  $before
     * @param  array<int, int|string>  $after
     */
    private function persist(array $before, array $after): void
    {
        $changed = [];

        foreach ($after as $id => $position) {
            if (! isset($before[$id]) || $before[$id] !== (int) $position) {
                $changed[(int) $id] = (int) $position;
            }
        }

        foreach (array_keys($changed) as $id) {
            PyramidPlayer::query()->whereKey($id)->update(['position' => null]);
        }

        foreach ($changed as $id => $position) {
            PyramidPlayer::query()->whereKey($id)->update(['position' => $position]);
        }
    }

    /**
     * @return string the trimmed note (ZF2 stored it trimmed)
     */
    private function requireNote(string $note): string
    {
        $note = trim($note);

        if ($note === '') {
            throw $this->error('note_required');
        }

        return $note;
    }

    private function error(string $key): PyramidRuleException
    {
        return new PyramidRuleException(new Verdict('pyramid::admin.'.$key));
    }

    private function now(): string
    {
        return CarbonImmutable::now()->format('Y-m-d H:i:s');
    }

    private function itn(mixed $raw): ?float
    {
        $raw = trim((string) $raw);

        return $raw === '' ? null : (float) str_replace(',', '.', $raw);
    }

    private function gender(mixed $raw): ?string
    {
        return in_array($raw, ['m', 'f'], true) ? $raw : null;
    }

    private function age(mixed $raw): ?int
    {
        $age = (int) $raw;

        return ($age > 0 && $age < 120) ? $age : null;
    }
}
