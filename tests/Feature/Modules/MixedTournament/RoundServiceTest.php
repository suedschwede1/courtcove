<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Modules\MixedTournament\Domain\MixedRuleException;
use App\Modules\MixedTournament\Domain\RoundService;
use App\Modules\MixedTournament\Models\MixedAvoidPair;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedMatch;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Models\MixedRoundPlayer;
use App\Modules\MixedTournament\Services\RotationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoundServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): RoundService
    {
        return $this->app->make(RoundService::class);
    }

    private function event(string $mode = 'americano'): MixedEvent
    {
        return MixedEvent::factory()->create(['mode' => $mode]);
    }

    /** @return list<int> */
    private function participants(MixedEvent $event, int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = MixedParticipant::factory()->create(['event_id' => $event->id])->id;
        }

        return $ids;
    }

    private function round(MixedEvent $event): MixedRound
    {
        return DB::transaction(fn (): MixedRound => $this->service()->createRound($event, '4_only', 'random'));
    }

    /**
     * Full rotation per group, as RoundGenerationService::propose() returns it.
     *
     * @param  list<list<int>>  $groups
     * @param  list<int>  $extraGroups
     * @return list<array{group: int, match_no: int, team_a: array{int, int}, team_b: array{int, int}, is_extra: bool}>
     */
    private function rotation(array $groups, array $extraGroups = []): array
    {
        $matches = [];
        foreach ($groups as $g => $members) {
            foreach ((new RotationService)->matches(count($members)) as $no => $m) {
                $matches[] = [
                    'group' => $g, 'match_no' => $no,
                    'team_a' => [$members[$m['teamA'][0]], $members[$m['teamA'][1]]],
                    'team_b' => [$members[$m['teamB'][0]], $members[$m['teamB'][1]]],
                    'is_extra' => in_array($g, $extraGroups, true),
                ];
            }
        }

        return $matches;
    }

    /**
     * One match per group (the shape of a Mexicano group or the old
     * short-form proposal of the integration test).
     *
     * @param  list<list<int>>  $groups
     * @return list<array{group: int, match_no: int, team_a: array{int, int}, team_b: array{int, int}, is_extra: bool}>
     */
    private function singleMatches(array $groups, bool $extra = false): array
    {
        $matches = [];
        foreach ($groups as $g => $m) {
            $matches[] = ['group' => $g, 'match_no' => 0, 'team_a' => [$m[0], $m[1]], 'team_b' => [$m[2], $m[3]], 'is_extra' => $extra];
        }

        return $matches;
    }

    /** @return list<array{id: int, participant_id: int, group_no: ?int}> */
    private function rows(MixedRound $round): array
    {
        return MixedRoundPlayer::query()->where('round_id', $round->id)->orderBy('id')->get()
            ->map(fn (MixedRoundPlayer $p): array => ['id' => $p->id, 'participant_id' => $p->participant_id, 'group_no' => $p->group_no])
            ->all();
    }

    /** @return list<array{group_no: int, match_no: int, a1: int, a2: int, b1: int, b2: int, is_extra: bool}> */
    private function matchRows(MixedRound $round): array
    {
        return MixedMatch::query()->where('round_id', $round->id)->orderBy('group_no')->orderBy('match_no')->orderBy('id')->get()
            ->map(fn (MixedMatch $m): array => [
                'group_no' => $m->group_no, 'match_no' => $m->match_no,
                'a1' => $m->team_a_p1, 'a2' => $m->team_a_p2, 'b1' => $m->team_b_p1, 'b2' => $m->team_b_p2,
                'is_extra' => $m->is_extra,
            ])
            ->all();
    }

    private function scoreAll(MixedRound $round, string $result = '3:1'): void
    {
        foreach (MixedMatch::query()->where('round_id', $round->id)->orderBy('id')->pluck('id') as $id) {
            $this->service()->recordScore($round, (int) $id, $result);
        }
    }

    private function assertRule(string $key, callable $call): MixedRuleException
    {
        try {
            $call();
        } catch (MixedRuleException $e) {
            $this->assertSame($key, $e->key);

            return $e;
        }

        $this->fail("MixedRuleException {$key} expected");
    }

    #[Test]
    public function filler_keeps_two_rows_and_cannot_be_swapped(): void
    {
        $event = $this->event();
        [$p1, $p2, $p3, $p4, $p5, $p6, $p7] = $this->participants($event, 7);
        $round = $this->round($event);
        $groups = [[$p1, $p2, $p3, $p4], [$p5, $p6, $p7, $p1]];

        $this->service()->persistProposal($round, $groups, $this->rotation($groups, [1]));
        // Regenerating replaces the proposal instead of adding to it.
        $this->service()->persistProposal($round, $groups, $this->rotation($groups, [1]));

        $this->assertSame(2, MixedRoundPlayer::query()->where('round_id', $round->id)->where('participant_id', $p1)->count());
        $this->assertCount(8, $this->rows($round));
        $this->assertSame([$p1, $p2, $p3, $p4, $p5, $p6, $p7], $this->service()->roundParticipantIds($round));

        $rows = $this->rows($round);
        $matches = $this->matchRows($round);

        $this->assertRule('filler_swap', fn () => $this->service()->swapPlayers($round, $p1, $p5));
        $this->assertRule('filler_swap', fn () => $this->service()->swapPlayers($round, $p6, $p1));

        $this->assertSame($rows, $this->rows($round));
        $this->assertSame($matches, $this->matchRows($round));
    }

    #[Test]
    public function sitters_are_rows_with_null_group_and_count_only_in_completed_rounds(): void
    {
        $event = $this->event('mexicano');
        [$p1, $p2, $p3, $p4, $sitter] = $this->participants($event, 5);
        $round = $this->round($event);
        $groups = [[$p1, $p2, $p3, $p4]];

        $this->service()->persistProposal($round, $groups, $this->singleMatches($groups), [$sitter]);

        $this->assertSame([$sitter], $this->service()->roundSitters($round));
        $this->assertSame(1, MixedRoundPlayer::query()->where('round_id', $round->id)->whereNull('group_no')->count());
        $this->assertSame([$p1, $p2, $p3, $p4, $sitter], $this->service()->roundParticipantIds($round));
        $this->assertSame(0, $this->service()->sitOutCounts($event)[$sitter]);

        $this->service()->confirm($round);
        $this->assertSame(0, $this->service()->sitOutCounts($event)[$sitter]);

        $this->scoreAll($round, '4:0');
        $this->assertSame(0, $this->service()->sitOutCounts($event)[$sitter]);

        $this->service()->complete($round);
        $counts = $this->service()->sitOutCounts($event);
        $this->assertSame(1, $counts[$sitter]);
        $this->assertSame(0, $counts[$p1]);
        $this->assertSame(1, $this->service()->completedRoundCount($event));
        $this->assertNull($this->service()->openRound($event));

        // Not selected for the round at all = no automatic sitter.
        [$late] = $this->participants($event, 1);
        $this->assertSame(0, $this->service()->sitOutCounts($event)[$late]);
    }

    #[Test]
    public function swap_refuses_mexicano_rounds(): void
    {
        $event = $this->event('mexicano');
        $ids = $this->participants($event, 8);
        $round = $this->round($event);
        $groups = [array_slice($ids, 0, 4), array_slice($ids, 4, 4)];
        $this->service()->persistProposal($round, $groups, $this->singleMatches($groups));
        $matches = $this->matchRows($round);
        $rows = $this->rows($round);

        $this->assertRule('swap_mexicano', fn () => $this->service()->swapPlayers($round, $ids[1], $ids[4]));

        $this->assertCount(2, $matches);
        $this->assertSame($matches, $this->matchRows($round));
        $this->assertSame($rows, $this->rows($round));
    }

    #[Test]
    public function swap_regenerates_both_groups_from_the_rotation(): void
    {
        $event = $this->event();
        [$p0, $p1, $p2, $p3, $p4, $p5, $p6, $p7] = $this->participants($event, 8);
        $round = $this->round($event);
        $groups = [[$p0, $p1, $p2, $p3], [$p4, $p5, $p6, $p7]];
        $matches = $this->singleMatches([$groups[0]]);
        $matches[] = ['group' => 1, 'match_no' => 0, 'team_a' => [$p4, $p5], 'team_b' => [$p6, $p7], 'is_extra' => true];
        $this->service()->persistProposal($round, $groups, $matches);

        $this->assertRule('swap_same', fn () => $this->service()->swapPlayers($round, $p1, $p1));
        $this->service()->swapPlayers($round, $p1, $p4);

        // Group members by row id: group 0 = p0, p2, p3, p4 (p4 row now
        // carries group 0), group 1 = p1, p5, p6, p7; is_extra kept per group.
        $this->assertSame([
            ['group_no' => 0, 'match_no' => 0, 'a1' => $p0, 'a2' => $p2, 'b1' => $p3, 'b2' => $p4, 'is_extra' => false],
            ['group_no' => 0, 'match_no' => 1, 'a1' => $p0, 'a2' => $p3, 'b1' => $p2, 'b2' => $p4, 'is_extra' => false],
            ['group_no' => 0, 'match_no' => 2, 'a1' => $p0, 'a2' => $p4, 'b1' => $p2, 'b2' => $p3, 'is_extra' => false],
            ['group_no' => 1, 'match_no' => 0, 'a1' => $p1, 'a2' => $p5, 'b1' => $p6, 'b2' => $p7, 'is_extra' => true],
            ['group_no' => 1, 'match_no' => 1, 'a1' => $p1, 'a2' => $p6, 'b1' => $p5, 'b2' => $p7, 'is_extra' => true],
            ['group_no' => 1, 'match_no' => 2, 'a1' => $p1, 'a2' => $p7, 'b1' => $p5, 'b2' => $p6, 'is_extra' => true],
        ], $this->matchRows($round));

        // History after confirming: the full rotation (6 matches) counts.
        $this->service()->confirm($round);
        $this->assertCount(6, $this->service()->pastMatches($event));
        $this->assertSame([], $this->service()->pastMatches($event, $round->id));
    }

    #[Test]
    public function swap_sitter_replaces_the_player_in_place(): void
    {
        $event = $this->event('mexicano');
        [$p1, $p2, $p3, $p4, $sitter] = $this->participants($event, 5);
        $round = $this->round($event);
        $groups = [[$p1, $p2, $p3, $p4]];
        $this->service()->persistProposal($round, $groups, $this->singleMatches($groups), [$sitter]);

        $this->assertRule('swap_sitter_failed', fn () => $this->service()->swapSitter($round, $p1, $p2));
        $this->assertRule('swap_sitter_failed', fn () => $this->service()->swapSitter($round, $sitter, $sitter));

        $this->service()->swapSitter($round, $sitter, $p3);

        $this->assertSame([
            ['group_no' => 0, 'match_no' => 0, 'a1' => $p1, 'a2' => $p2, 'b1' => $sitter, 'b2' => $p4, 'is_extra' => false],
        ], $this->matchRows($round));
        $this->assertSame([$p3], $this->service()->roundSitters($round));
        $this->assertSame(0, MixedRoundPlayer::query()->where('round_id', $round->id)->where('participant_id', $sitter)->value('group_no'));

        $this->service()->confirm($round);
        $this->assertRule('round_state', fn () => $this->service()->swapSitter($round, $p3, $p1));
    }

    #[Test]
    public function lifecycle_rules(): void
    {
        $event = $this->event();
        $ids = $this->participants($event, 4);
        $round = $this->round($event);

        $this->assertRule('empty_round_confirm', fn () => $this->service()->confirm($round));
        $this->assertRule('round_state', fn () => $this->service()->complete($round));
        $this->assertRule('round_state', fn () => $this->service()->reopen($round));
        $this->assertRule('invalid_round_config', fn () => $this->service()->updateConfig($round, '6_only', 'random'));

        $this->service()->updateConfig($round, '4_and_5', 'itn');
        $this->assertSame(['4_and_5', 'itn'], [$round->fresh()->group_size_mode, $round->fresh()->distribution]);

        $this->service()->persistProposal($round, [$ids], $this->rotation([$ids]));
        $this->service()->confirm($round);
        $this->assertSame('confirmed', $round->fresh()->status);

        $this->assertRule('round_state', fn () => $this->service()->confirm($round));
        $this->assertRule('round_state', fn () => $this->service()->persistProposal($round, [$ids], $this->rotation([$ids])));
        $this->assertRule('round_state', fn () => $this->service()->updateConfig($round, '4_only', 'random'));
        $this->assertRule('delete_confirmed', fn () => $this->service()->delete($round));

        $matchIds = MixedMatch::query()->where('round_id', $round->id)->orderBy('id')->pluck('id')->all();
        $this->service()->recordScore($round, (int) $matchIds[0], '3:1');
        $this->service()->recordScore($round, (int) $matchIds[1], '2:2');
        $this->assertRule('scores_missing', fn () => $this->service()->complete($round));

        $this->service()->recordScore($round, (int) $matchIds[2], '0:4');
        $this->service()->complete($round);
        $this->assertSame('completed', $round->fresh()->status);
        $this->assertRule('round_state', fn () => $this->service()->recordScore($round, (int) $matchIds[0], '4:0'));
        $this->assertRule('delete_confirmed', fn () => $this->service()->delete($round));

        $this->service()->reopen($round);
        $this->assertSame('confirmed', $round->fresh()->status);

        $standings = $this->service()->standings($event);
        $this->assertEqualsCanonicalizing($ids, array_column($standings, 'id'));
        $this->assertSame([3, 3, 3, 3], array_column($standings, 'played'));

        // A confirmed round without matches cannot be completed.
        $empty = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 9, 'status' => 'confirmed']);
        $this->assertRule('empty_round_complete', fn () => $this->service()->complete($empty));

        // Deleting a proposal removes its players and matches.
        $second = $this->round($event);
        $this->assertSame(10, $second->round_no);
        $this->service()->persistProposal($second, [$ids], $this->rotation([$ids]));
        $this->service()->delete($second);
        $this->assertNull(MixedRound::query()->find($second->id));
        $this->assertSame(0, MixedRoundPlayer::query()->where('round_id', $second->id)->count());
        $this->assertSame(0, MixedMatch::query()->where('round_id', $second->id)->count());
    }

    #[Test]
    public function update_config_accepts_an_unchanged_config(): void
    {
        // MySQL reports 0 affected rows for an identical update (changed, not
        // matched rows); SQLite counts matched rows, so the MySQL case itself
        // is covered by the Task 26 MySQL suite. Here: no affected-row check.
        $event = $this->event();
        $round = $this->round($event);

        $this->service()->updateConfig($round, '4_and_5', 'itn');
        $this->service()->updateConfig($round, '4_and_5', 'itn');

        $fresh = $round->fresh();
        $this->assertSame(['4_and_5', 'itn', 'proposed'], [$fresh->group_size_mode, $fresh->distribution, $fresh->status]);
    }

    #[Test]
    public function score_only_while_confirmed_and_four_games(): void
    {
        $event = $this->event();
        $ids = $this->participants($event, 4);
        $round = $this->round($event);
        $this->service()->persistProposal($round, [$ids], $this->rotation([$ids]));
        $matchId = (int) MixedMatch::query()->where('round_id', $round->id)->orderBy('id')->value('id');

        $this->assertRule('round_state', fn () => $this->service()->recordScore($round, $matchId, '3:1'));
        // The result is validated before the round state (old controller order).
        $this->assertRule('invalid_result', fn () => $this->service()->recordScore($round, $matchId, '5:0'));

        $this->service()->confirm($round);
        $this->assertRule('invalid_result', fn () => $this->service()->recordScore($round, $matchId, '5:0'));
        $this->assertRule('invalid_result', fn () => $this->service()->recordScore($round, $matchId, '4:4'));
        $this->assertRule('invalid_result', fn () => $this->service()->recordScore($round, $matchId, ''));

        $foreignEvent = $this->event();
        $foreign = $this->round($foreignEvent);
        try {
            $this->service()->recordScore($foreign, $matchId, '3:1');
            $this->fail('ModelNotFoundException expected');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $this->service()->recordScore($round, $matchId, '3:1');
        $match = MixedMatch::query()->findOrFail($matchId);
        $this->assertSame([3, 1], [$match->games_a, $match->games_b]);
    }

    #[Test]
    public function create_round_requires_a_transaction_and_numbers_rounds(): void
    {
        $this->event(); // opens the lazy test wrapper transaction

        // The test wrapper transaction is closed for this one call and
        // reopened afterwards so the trait still rolls back at the end.
        $wrapper = DB::transactionLevel();
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        try {
            $this->service()->createRound(new MixedEvent(['id' => 1]), '4_only', 'random');
            $this->fail('LogicException expected');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        } finally {
            for ($i = 0; $i < $wrapper; $i++) {
                DB::beginTransaction();
            }
        }

        $event = $this->event();
        $first = $this->round($event);
        $second = $this->round($event);
        $this->assertSame([1, 2], [$first->round_no, $second->round_no]);
        $this->assertSame(['proposed', 'proposed'], [$first->fresh()->status, $second->fresh()->status]);
        $this->assertSame($first->id, $this->service()->openRound($event)?->id);

        $this->assertRule('invalid_round_config', fn () => DB::transaction(fn () => $this->service()->createRound($event, '4_only', 'skill')));
        $this->assertSame(2, MixedRound::query()->where('event_id', $event->id)->count());
    }

    #[Test]
    public function mexicano_blocks_while_a_round_is_not_completed(): void
    {
        $event = $this->event('mexicano');
        $ids = $this->participants($event, 5);
        $round = $this->round($event);
        $groups = [array_slice($ids, 0, 4)];

        $e = $this->assertRule('mexicano_round_open', fn () => $this->round($event));
        $this->assertSame(['number' => 1], $e->replace);

        $this->service()->persistProposal($round, $groups, $this->singleMatches($groups), [$ids[4]]);
        $this->service()->confirm($round);
        $this->assertRule('mexicano_round_open', fn () => $this->round($event));

        // Even with every score entered, confirmed is not enough.
        $this->scoreAll($round, '4:0');
        $this->assertRule('mexicano_round_open', fn () => $this->round($event));
        $this->assertSame(1, MixedRound::query()->where('event_id', $event->id)->count());

        $this->service()->complete($round);
        $next = $this->round($event);
        $this->assertSame(2, $next->round_no);
        $this->assertSame($next->id, $this->service()->openRound($event)?->id);
        $this->assertSame(1, $this->service()->completedRoundCount($event));
    }

    #[Test]
    public function every_mixed_query_orders_its_rows(): void
    {
        $event = $this->event();
        $ids = $this->participants($event, 10);
        MixedAvoidPair::query()->create(['event_id' => $event->id, 'participant_a' => $ids[0], 'participant_b' => $ids[1]]);
        MixedAvoidPair::query()->create(['event_id' => $event->id, 'participant_a' => $ids[2], 'participant_b' => $ids[5]]);

        // Completed round with two sitters, a second completed round, then a proposal.
        $groups = [array_slice($ids, 0, 4), array_slice($ids, 4, 4)];
        foreach ([0, 1] as $n) {
            $done = $this->round($event);
            $this->service()->persistProposal($done, $groups, $this->rotation($groups), [$ids[8], $ids[9]]);
            $this->service()->confirm($done);
            $this->scoreAll($done);
            $this->service()->complete($done);
        }
        $open = $this->round($event);
        $this->service()->persistProposal($open, $groups, $this->rotation($groups));

        $tables = '/\b(from|join|update)\s+["`]?bs_mixed_(round_players|matches|participants|rounds)["`]?(\s|$)/i';
        $checked = [];
        $unordered = [];
        $replaying = false;

        DB::listen(function (QueryExecuted $q) use ($tables, &$checked, &$unordered, &$replaying): void {
            if ($replaying || ! str_starts_with(strtolower(ltrim($q->sql)), 'select') || ! preg_match($tables, $q->sql)) {
                return;
            }
            $replaying = true;
            $count = count(DB::select($q->sql, $q->bindings));
            $replaying = false;
            if ($count > 1) {
                $checked[] = $q->sql;
                if (! str_contains(strtolower($q->sql), 'order by')) {
                    $unordered[] = $q->sql;
                }
            }
        });

        $this->service()->sitOutCounts($event);
        $this->service()->roundSitters(MixedRound::query()->where('event_id', $event->id)->orderBy('id')->firstOrFail());
        $this->service()->roundParticipantIds($open);
        $this->service()->pastMatches($event);
        $this->service()->standings($event);
        $this->service()->preview($open);
        $this->service()->swapPlayers($open, $ids[1], $ids[5]);

        $this->assertSame([], $unordered);
        $this->assertGreaterThanOrEqual(8, count($checked));
    }
}
