<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Domain;

use App\Modules\MixedTournament\Models\MixedAvoidPair;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedMatch;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Models\MixedRoundPlayer;
use App\Modules\MixedTournament\Services\HistoryService;
use App\Modules\MixedTournament\Services\RotationService;
use App\Modules\MixedTournament\Services\RoundPreviewService;
use App\Modules\MixedTournament\Services\StandingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Rounds of an event: lifecycle (proposed -> confirmed -> completed),
 * persisted proposals, swaps, scores, history, standings and the preview
 * (DB-bound replacement of the round part of the old MixedManager).
 *
 * Row rules that must never be "simplified":
 * - bs_mixed_round_players has NO unique key. An extra-group filler has two
 *   rows (one per group), a Mexicano sitter one row with group_no NULL. Rows
 *   are only ever inserted, updated by id or deleted per round; never
 *   updateOrCreate/firstOrCreate/upsert, and NULL groups only via whereNull().
 * - Every multi-row read has an explicit order (rows by id, matches by
 *   group_no, match_no, id): MySQL may serve `round_id = ?` from the
 *   (round_id, participant_id) index and return participant order, which
 *   would change regenerated rotations and printed numbers.
 * - Lock order in every write: event row, then the round row.
 */
final class RoundService
{
    public const GROUP_SIZE_MODES = ['4_only', '4_and_5'];

    public const DISTRIBUTIONS = ['random', 'itn', 'itn_balanced', 'age', 'ladies_apart'];

    public const RESULTS = ['4:0', '3:1', '2:2', '1:3', '0:4'];

    private const TEAM_COLUMNS = ['team_a_p1', 'team_a_p2', 'team_b_p1', 'team_b_p2'];

    public function __construct(
        private readonly RotationService $rotation,
        private readonly HistoryService $history,
        private readonly StandingsService $standings,
        private readonly RoundPreviewService $preview,
    ) {}

    /**
     * Must run inside the caller's transaction so the round and its first
     * proposal are created atomically. The event row lock serialises not
     * only MAX+1 but also the Mexicano rule "at most one open round".
     *
     * @throws LogicException outside a transaction
     * @throws MixedRuleException invalid_round_config, mexicano_round_open
     */
    public function createRound(MixedEvent $e, string $groupSizeMode, string $distribution): MixedRound
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('RoundService::createRound() must be called inside a transaction.');
        }

        $event = MixedEvent::query()->whereKey($e->id)->lockForUpdate()->firstOrFail();
        $this->assertConfig($groupSizeMode, $distribution);

        $rounds = MixedRound::query()->where('event_id', $event->id)->orderBy('id')->get();

        if ($event->isMexicano()) {
            foreach ($rounds as $round) {
                if ($round->status !== 'completed') {
                    throw new MixedRuleException('mexicano_round_open', ['number' => (int) $round->round_no]);
                }
            }
        }

        $next = 1;
        foreach ($rounds as $round) {
            $next = max($next, (int) $round->round_no + 1);
        }

        return MixedRound::query()->create([
            'event_id' => $event->id,
            'round_no' => $next,
            'group_size_mode' => $groupSizeMode,
            'distribution' => $distribution,
            'status' => 'proposed',
        ]);
    }

    /**
     * Stores (or replaces) a proposal: one row per group member with the
     * group index, one row with group_no NULL per sitter (Mexicano), then the
     * matches. Participants not selected for the round get no row at all and
     * therefore never count as automatic sitters.
     *
     * @param  array<int, list<int>>  $groups  group index => participant ids
     * @param  list<array{group: int, match_no: int, team_a: array{int, int}, team_b: array{int, int}, is_extra: bool}>  $matches
     * @param  list<int>  $sitters
     *
     * @throws MixedRuleException round_state
     */
    public function persistProposal(MixedRound $r, array $groups, array $matches, array $sitters = []): void
    {
        DB::transaction(function () use ($r, $groups, $matches, $sitters): void {
            [, $round] = $this->lock($r);
            $this->requireStatus($round, 'proposed');

            MixedRoundPlayer::query()->where('round_id', $round->id)->delete();
            MixedMatch::query()->where('round_id', $round->id)->delete();

            foreach ($groups as $groupIndex => $group) {
                foreach ($group as $participantId) {
                    MixedRoundPlayer::query()->create([
                        'round_id' => $round->id,
                        'participant_id' => (int) $participantId,
                        'group_no' => (int) $groupIndex,
                    ]);
                }
            }

            foreach ($sitters as $participantId) {
                MixedRoundPlayer::query()->create([
                    'round_id' => $round->id,
                    'participant_id' => (int) $participantId,
                    'group_no' => null,
                ]);
            }

            foreach ($matches as $match) {
                MixedMatch::query()->create([
                    'round_id' => $round->id,
                    'group_no' => (int) $match['group'],
                    'match_no' => (int) $match['match_no'],
                    'team_a_p1' => (int) $match['team_a'][0],
                    'team_a_p2' => (int) $match['team_a'][1],
                    'team_b_p1' => (int) $match['team_b'][0],
                    'team_b_p2' => (int) $match['team_b'][1],
                    'is_extra' => $match['is_extra'] ? 1 : 0,
                ]);
            }
        });
    }

    /** @throws MixedRuleException round_state, invalid_round_config */
    public function updateConfig(MixedRound $r, string $groupSizeMode, string $distribution): void
    {
        DB::transaction(function () use ($r, $groupSizeMode, $distribution): void {
            [, $round] = $this->lock($r);
            $this->requireStatus($round, 'proposed');
            $this->assertConfig($groupSizeMode, $distribution);

            $this->conditionalUpdate($round, 'proposed', [
                'group_size_mode' => $groupSizeMode,
                'distribution' => $distribution,
            ]);
        });
    }

    /**
     * Swaps the groups of two players of a proposed Americano round and
     * rebuilds both groups' matches from the rotation over the group members
     * in row-id order. Refused on Mexicano events (design fix: a Mexicano
     * group has one fixed match that the rotation would replace by three;
     * there only swapSitter() applies) and for fillers, whose two rows make
     * the swap ambiguous.
     *
     * @throws MixedRuleException swap_mexicano, round_state, swap_same, filler_swap, swap_sitter_failed
     */
    public function swapPlayers(MixedRound $r, int $a, int $b): void
    {
        DB::transaction(function () use ($r, $a, $b): void {
            [$event, $round] = $this->lock($r);

            if ($event->isMexicano()) {
                throw new MixedRuleException('swap_mexicano');
            }
            $this->requireStatus($round, 'proposed');
            if ($a === $b) {
                throw new MixedRuleException('swap_same');
            }

            $rowsA = $this->participantRows($round, $a);
            $rowsB = $this->participantRows($round, $b);
            if (count($rowsA) !== 1 || count($rowsB) !== 1) {
                throw new MixedRuleException('filler_swap');
            }

            $rowA = $rowsA[0];
            $rowB = $rowsB[0];
            if ($rowA->group_no === null || $rowB->group_no === null) {
                throw new MixedRuleException('swap_sitter_failed');
            }

            $groupA = (int) $rowA->group_no;
            $groupB = (int) $rowB->group_no;

            MixedRoundPlayer::query()->whereKey($rowA->id)->update(['group_no' => $groupB]);
            MixedRoundPlayer::query()->whereKey($rowB->id)->update(['group_no' => $groupA]);

            foreach (array_unique([$groupA, $groupB]) as $groupNo) {
                $this->regenerateGroupMatches($round, $groupNo);
            }
        });
    }

    /**
     * Swaps a proposed sitter (Mexicano) with a playing participant. The
     * group keeps its one match; the player is replaced in place, in exactly
     * the team column he held.
     *
     * @throws MixedRuleException round_state, swap_sitter_failed
     */
    public function swapSitter(MixedRound $r, int $sitterId, int $playerId): void
    {
        DB::transaction(function () use ($r, $sitterId, $playerId): void {
            [, $round] = $this->lock($r);
            $this->requireStatus($round, 'proposed');

            if ($sitterId === $playerId) {
                throw new MixedRuleException('swap_sitter_failed');
            }

            $sitterRows = $this->participantRows($round, $sitterId);
            $playerRows = $this->participantRows($round, $playerId);
            if (count($sitterRows) !== 1 || count($playerRows) !== 1
                || $sitterRows[0]->group_no !== null || $playerRows[0]->group_no === null) {
                throw new MixedRuleException('swap_sitter_failed');
            }

            $groupNo = (int) $playerRows[0]->group_no;

            MixedRoundPlayer::query()->whereKey($sitterRows[0]->id)->update(['group_no' => $groupNo]);
            MixedRoundPlayer::query()->whereKey($playerRows[0]->id)->update(['group_no' => null]);

            // Exactly one of these hits: the player holds one team column of the group match.
            foreach (self::TEAM_COLUMNS as $column) {
                MixedMatch::query()
                    ->where('round_id', $round->id)
                    ->where('group_no', $groupNo)
                    ->where($column, $playerId)
                    ->update([$column => $sitterId]);
            }
        });
    }

    /**
     * Locks the groups (no swap/regeneration) and opens score entry.
     *
     * @throws MixedRuleException round_state, empty_round_confirm
     */
    public function confirm(MixedRound $r): void
    {
        DB::transaction(function () use ($r): void {
            [, $round] = $this->lock($r);
            $this->requireStatus($round, 'proposed');

            if (! MixedMatch::query()->where('round_id', $round->id)->exists()) {
                throw new MixedRuleException('empty_round_confirm');
            }

            $this->conditionalUpdate($round, 'proposed', ['status' => 'confirmed']);
        });
    }

    /** @throws MixedRuleException round_state, empty_round_complete, scores_missing */
    public function complete(MixedRound $r): void
    {
        DB::transaction(function () use ($r): void {
            [, $round] = $this->lock($r);
            $this->requireStatus($round, 'confirmed');

            $matches = $this->matchQuery($round)->get();
            if ($matches->isEmpty()) {
                throw new MixedRuleException('empty_round_complete');
            }
            foreach ($matches as $match) {
                if ($match->games_a === null || $match->games_b === null) {
                    throw new MixedRuleException('scores_missing');
                }
            }

            $this->conditionalUpdate($round, 'confirmed', ['status' => 'completed']);
        });
    }

    /** @throws MixedRuleException round_state */
    public function reopen(MixedRound $r): void
    {
        DB::transaction(function () use ($r): void {
            [, $round] = $this->lock($r);
            $this->requireStatus($round, 'completed');
            $this->conditionalUpdate($round, 'completed', ['status' => 'confirmed']);
        });
    }

    /** @throws MixedRuleException delete_confirmed */
    public function delete(MixedRound $r): void
    {
        DB::transaction(function () use ($r): void {
            [, $round] = $this->lock($r);
            if ($round->status !== 'proposed') {
                throw new MixedRuleException('delete_confirmed');
            }

            MixedMatch::query()->where('round_id', $round->id)->delete();
            MixedRoundPlayer::query()->where('round_id', $round->id)->delete();
            MixedRound::query()->whereKey($round->id)->where('status', 'proposed')->delete();
        });
    }

    /**
     * @param  string  $result  one of self::RESULTS (exactly four games)
     *
     * @throws ModelNotFoundException match not in this round
     * @throws MixedRuleException round_state, invalid_result
     */
    public function recordScore(MixedRound $r, int $matchId, string $result): void
    {
        DB::transaction(function () use ($r, $matchId, $result): void {
            [, $round] = $this->lock($r);
            $match = MixedMatch::query()->where('round_id', $round->id)->whereKey($matchId)->firstOrFail();
            $this->requireStatus($round, 'confirmed');

            if (! in_array($result, self::RESULTS, true)) {
                throw new MixedRuleException('invalid_result');
            }
            [$gamesA, $gamesB] = array_map('intval', explode(':', $result));

            MixedMatch::query()->whereKey($match->id)->update(['games_a' => $gamesA, 'games_b' => $gamesB]);
        });
    }

    /**
     * How often each participant sat out automatically. Only COMPLETED
     * rounds count: an open proposal can still be regenerated or deleted.
     *
     * @return array<int, int> participant id => count (every participant, 0 by default)
     */
    public function sitOutCounts(MixedEvent $e): array
    {
        $counts = [];
        foreach ($this->participantIds($e) as $id) {
            $counts[$id] = 0;
        }

        $rounds = MixedRound::query()->where('event_id', $e->id)->where('status', 'completed')->orderBy('id')->get();
        foreach ($rounds as $round) {
            foreach ($this->roundSitters($round) as $id) {
                if (isset($counts[$id])) {
                    $counts[$id]++;
                }
            }
        }

        return $counts;
    }

    /** @return list<int> participants of the round with group_no NULL, in row order */
    public function roundSitters(MixedRound $r): array
    {
        return MixedRoundPlayer::query()
            ->where('round_id', $r->id)
            ->whereNull('group_no')
            ->orderBy('id')
            ->pluck('participant_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /** @return list<int> distinct participants of the round (fillers once), first row first */
    public function roundParticipantIds(MixedRound $r): array
    {
        $ids = [];
        foreach (MixedRoundPlayer::query()->where('round_id', $r->id)->orderBy('id')->pluck('participant_id') as $id) {
            $ids[(int) $id] = true;
        }

        return array_keys($ids);
    }

    public function completedRoundCount(MixedEvent $e): int
    {
        return MixedRound::query()->where('event_id', $e->id)->where('status', 'completed')->count();
    }

    /** The first round of the event that is not completed, or null. */
    public function openRound(MixedEvent $e): ?MixedRound
    {
        return MixedRound::query()
            ->where('event_id', $e->id)
            ->where('status', '!=', 'completed')
            ->orderBy('id')
            ->first();
    }

    /**
     * Matches of the confirmed and completed rounds (rounds by id, matches in
     * round order): history for the next round and the mixing tab.
     *
     * @return list<array{team_a_p1: int, team_a_p2: int, team_b_p1: int, team_b_p2: int}>
     */
    public function pastMatches(MixedEvent $e, ?int $excludeRoundId = null): array
    {
        $matches = [];

        foreach ($this->scoredRounds($e) as $round) {
            if ($excludeRoundId !== null && $round->id === $excludeRoundId) {
                continue;
            }
            foreach ($this->matchQuery($round)->get() as $match) {
                $matches[] = [
                    'team_a_p1' => (int) $match->team_a_p1, 'team_a_p2' => (int) $match->team_a_p2,
                    'team_b_p1' => (int) $match->team_b_p1, 'team_b_p2' => (int) $match->team_b_p2,
                ];
            }
        }

        return $matches;
    }

    /**
     * Partner/opponent history of the event (HistoryService::build() over
     * pastMatches()).
     *
     * @return array{partners: array<string, int>, opponents: array<string, int>}
     */
    public function history(MixedEvent $e, ?int $excludeRoundId = null): array
    {
        return $this->history->build($this->pastMatches($e, $excludeRoundId));
    }

    /** @return list<array{id: int, points: int, played: int, games_won: int, games_avg: float, points_avg: float, rounds_played: int, eligible: bool, rounds_needed: int}> */
    public function standings(MixedEvent $e): array
    {
        $matches = [];

        foreach ($this->scoredRounds($e) as $round) {
            foreach ($this->matchQuery($round)->get() as $match) {
                $matches[] = [
                    // Required for the eligibility rule, see StandingsService.
                    'round' => (int) $round->id,
                    'team_a' => [(int) $match->team_a_p1, (int) $match->team_a_p2],
                    'team_b' => [(int) $match->team_b_p1, (int) $match->team_b_p2],
                    'games_a' => $match->games_a !== null ? (int) $match->games_a : null,
                    'games_b' => $match->games_b !== null ? (int) $match->games_b : null,
                ];
            }
        }

        return $this->standings->standings($this->participantIds($e), $matches);
    }

    /** @return array{repeated_encounters: list<array{players: array{int, int}, previous: int}>, avoid_collisions: list<array{int, int}>, match_counts: array<int, int>} */
    public function preview(MixedRound $r): array
    {
        $event = MixedEvent::query()->whereKey($r->event_id)->firstOrFail();

        $roundMatches = $this->matchQuery($r)->get()
            ->map(fn (MixedMatch $m): array => [
                'team_a_p1' => (int) $m->team_a_p1, 'team_a_p2' => (int) $m->team_a_p2,
                'team_b_p1' => (int) $m->team_b_p1, 'team_b_p2' => (int) $m->team_b_p2,
            ])
            ->values()
            ->all();

        $avoidPairs = MixedAvoidPair::query()->where('event_id', $event->id)->orderBy('id')->get()
            ->map(fn (MixedAvoidPair $p): array => ['a' => (int) $p->participant_a, 'b' => (int) $p->participant_b])
            ->values()
            ->all();

        return $this->preview->preview($roundMatches, $this->pastMatches($event, $r->id), $avoidPairs);
    }

    /**
     * Event row first, then the round row (fixed lock order).
     *
     * @return array{MixedEvent, MixedRound}
     */
    private function lock(MixedRound $r): array
    {
        $event = MixedEvent::query()->whereKey($r->event_id)->lockForUpdate()->firstOrFail();
        $round = MixedRound::query()->whereKey($r->id)->lockForUpdate()->firstOrFail();

        return [$event, $round];
    }

    private function requireStatus(MixedRound $round, string $expected): void
    {
        if ($round->status !== $expected) {
            throw new MixedRuleException('round_state');
        }
    }

    /** @param  array<string, string>  $values */
    private function conditionalUpdate(MixedRound $round, string $expected, array $values): void
    {
        $updated = MixedRound::query()->whereKey($round->id)->where('status', $expected)->update($values);

        if ($updated !== 1) {
            throw new MixedRuleException('round_state');
        }
    }

    private function assertConfig(string $groupSizeMode, string $distribution): void
    {
        if (! in_array($groupSizeMode, self::GROUP_SIZE_MODES, true) || ! in_array($distribution, self::DISTRIBUTIONS, true)) {
            throw new MixedRuleException('invalid_round_config');
        }
    }

    /** @return list<MixedRoundPlayer> every row of the participant in this round (a filler has two) */
    private function participantRows(MixedRound $round, int $participantId): array
    {
        return MixedRoundPlayer::query()
            ->where('round_id', $round->id)
            ->where('participant_id', $participantId)
            ->orderBy('id')
            ->get()
            ->values()
            ->all();
    }

    /**
     * Rebuilds the matches of one group from the rotation over its members
     * in row-id order, keeping is_extra of the group's first old match.
     */
    private function regenerateGroupMatches(MixedRound $round, int $groupNo): void
    {
        $first = MixedMatch::query()
            ->where('round_id', $round->id)
            ->where('group_no', $groupNo)
            ->orderBy('match_no')
            ->orderBy('id')
            ->first();
        $wasExtra = $first !== null && (bool) $first->is_extra;

        $members = MixedRoundPlayer::query()
            ->where('round_id', $round->id)
            ->where('group_no', $groupNo)
            ->orderBy('id')
            ->pluck('participant_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        MixedMatch::query()->where('round_id', $round->id)->where('group_no', $groupNo)->delete();

        foreach ($this->rotation->matches(count($members)) as $matchNo => $match) {
            MixedMatch::query()->create([
                'round_id' => $round->id,
                'group_no' => $groupNo,
                'match_no' => $matchNo,
                'team_a_p1' => $members[$match['teamA'][0]],
                'team_a_p2' => $members[$match['teamA'][1]],
                'team_b_p1' => $members[$match['teamB'][0]],
                'team_b_p2' => $members[$match['teamB'][1]],
                'is_extra' => $wasExtra ? 1 : 0,
            ]);
        }
    }

    /** @return Builder<MixedMatch> */
    private function matchQuery(MixedRound $round): Builder
    {
        return MixedMatch::query()
            ->where('round_id', $round->id)
            ->orderBy('group_no')
            ->orderBy('match_no')
            ->orderBy('id');
    }

    /** @return Collection<int, MixedRound> confirmed and completed rounds by id */
    private function scoredRounds(MixedEvent $e): Collection
    {
        return MixedRound::query()
            ->where('event_id', $e->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->orderBy('id')
            ->get()
            ->toBase();
    }

    /** @return list<int> participant ids of the event by id */
    private function participantIds(MixedEvent $e): array
    {
        return MixedParticipant::query()
            ->where('event_id', $e->id)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
