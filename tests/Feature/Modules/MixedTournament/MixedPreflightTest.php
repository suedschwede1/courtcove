<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Models\User;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use App\Modules\MixedTournament\Models\MixedMatch;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Models\MixedRoundPlayer;
use App\Modules\MixedTournament\Preflight\MixedPreflight;
use App\Modules\TournamentBase\Preflight\PreflightResult;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Preflight checks on the SQLite test database. Checks that need a real MySQL server are skipped there
 * (charset, fk): they are exercised against the legacy schema by the controller on staging.
 */
class MixedPreflightTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        return array_map(fn (PreflightResult $r): string => $r->line(), app(MixedPreflight::class)->run());
    }

    private function has(string $prefix): bool
    {
        foreach ($this->lines() as $line) {
            if (str_starts_with($line, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * One event with four participants, a confirmed round, one match, a grant.
     *
     * @return array{event: MixedEvent, round: MixedRound, ids: list<int>}
     */
    private function demoLikeData(string $mode = 'americano'): array
    {
        $event = MixedEvent::factory()->create(['mode' => $mode, 'is_default' => 1]);
        $ids = [];
        foreach (range(1, 4) as $n) {
            $ids[] = (int) MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => 'Spieler 0'.$n])->id;
        }
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 1, 'status' => 'confirmed']);
        foreach ($ids as $id) {
            MixedRoundPlayer::query()->create(['round_id' => $round->id, 'participant_id' => $id, 'group_no' => 1]);
        }
        MixedMatch::factory()->create([
            'round_id' => $round->id, 'group_no' => 1, 'match_no' => 1,
            'team_a_p1' => $ids[0], 'team_a_p2' => $ids[1], 'team_b_p1' => $ids[2], 'team_b_p2' => $ids[3],
            'games_a' => 3, 'games_b' => 1,
        ]);
        $user = User::factory()->create();
        MixedEventGrant::factory()->create(['event_id' => $event->id, 'uid' => $user->uid, 'role' => 'score']);

        return ['event' => $event, 'round' => $round, 'ids' => $ids];
    }

    #[Test]
    public function healthy_database_passes(): void
    {
        $this->demoLikeData();

        $lines = $this->lines();

        $this->assertSame([], array_values(array_filter($lines, fn (string $l): bool => str_starts_with($l, '[FAIL]'))));
        $this->assertSame([], array_values(array_filter($lines, fn (string $l): bool => str_starts_with($l, '[WARN]'))));
        $this->assertContains('[SKIP] charset:bs_mixed_events: not MySQL', $lines);
        $this->assertContains('[SKIP] fk:bs_mixed_event_grants.uid: not MySQL', $lines);
        $this->assertTrue($this->has('[PASS] version:bs_mixed_schema_versions'));
        $this->assertTrue($this->has('[PASS] no-unique:bs_mixed_round_players'));
        $this->assertTrue($this->has('[PASS] unique:bs_mixed_rounds(event_id,round_no)'));
    }

    #[Test]
    public function missing_table_fails_early(): void
    {
        Schema::drop('bs_mixed_avoid_pairs');

        $this->assertSame(['[FAIL] tables: missing bs_mixed_avoid_pairs'], $this->lines());
    }

    #[Test]
    public function missing_column_fails(): void
    {
        Schema::table('bs_mixed_events', function (Blueprint $table): void {
            $table->dropColumn('mode');
        });

        $this->assertContains('[FAIL] columns:bs_mixed_events: missing column mode', $this->lines());
    }

    #[Test]
    public function legacy_birth_date_is_tolerated(): void
    {
        Schema::table('bs_mixed_participants', function (Blueprint $table): void {
            $table->date('birth_date')->nullable();
        });

        $lines = $this->lines();

        $this->assertContains('[WARN] legacy:bs_mixed_participants.birth_date: tolerated', $lines);
        $this->assertContains('[WARN] columns:bs_mixed_participants: unexpected extra column birth_date', $lines);
        $this->assertFalse($this->has('[FAIL]'));
    }

    #[Test]
    public function low_schema_version_fails(): void
    {
        DB::table('bs_mixed_schema_versions')->update(['version' => 20260101]);

        $this->assertContains('[FAIL] version:bs_mixed_schema_versions: latest is 20260101, expected at least 20260803', $this->lines());
    }

    #[Test]
    public function newer_schema_version_passes(): void
    {
        DB::table('bs_mixed_schema_versions')->insert(['version' => 20261001, 'applied' => '2026-10-01 10:00:00']);

        $this->assertContains('[PASS] version:bs_mixed_schema_versions: 20261001', $this->lines());
    }

    #[Test]
    public function duplicate_round_numbers_fail(): void
    {
        $event = MixedEvent::factory()->create();
        Schema::table('bs_mixed_rounds', function (Blueprint $table): void {
            $table->dropUnique(['event_id', 'round_no']);
        });
        MixedRound::factory()->count(2)->create(['event_id' => $event->id, 'round_no' => 1]);

        $this->assertContains(
            '[FAIL] unique:bs_mixed_rounds(event_id,round_no): unique index missing, 1 duplicate groups',
            $this->lines()
        );
    }

    #[Test]
    public function unique_key_on_round_players_fails(): void
    {
        Schema::table('bs_mixed_round_players', function (Blueprint $table): void {
            $table->unique(['round_id', 'participant_id']);
        });

        $this->assertTrue($this->has('[FAIL] no-unique:bs_mixed_round_players: unique index on round_id, participant_id'));
    }

    #[Test]
    public function orphan_matches_warn(): void
    {
        $this->demoLikeData();
        MixedMatch::factory()->create(['round_id' => 9999, 'team_a_p1' => 9001, 'team_a_p2' => 9002, 'team_b_p1' => 9003, 'team_b_p2' => 9004]);

        $lines = $this->lines();

        $this->assertContains('[WARN] orphans:bs_mixed_matches.round_id->bs_mixed_rounds.id: 1 orphaned rows', $lines);
        $this->assertContains('[WARN] orphans:bs_mixed_matches.team_a_p1->bs_mixed_participants.id: 1 orphaned rows', $lines);
        $this->assertContains('[WARN] orphans:bs_mixed_matches.team_b_p2->bs_mixed_participants.id: 1 orphaned rows', $lines);
        $this->assertFalse($this->has('[FAIL]'));
    }

    #[Test]
    public function orphan_round_players_and_grants_warn(): void
    {
        $data = $this->demoLikeData();
        DB::table('bs_mixed_round_players')->insert(['round_id' => $data['round']->id, 'participant_id' => 8888, 'group_no' => 1]);
        DB::table('bs_mixed_round_players')->insert(['round_id' => 7777, 'participant_id' => $data['ids'][0], 'group_no' => 1]);
        DB::table('bs_mixed_participants')->insert(['event_id' => 6666, 'label' => 'Spieler 99', 'status' => 'active', 'no_extra_match' => 0]);
        Schema::table('bs_mixed_event_grants', fn (Blueprint $table) => $table->dropForeign(['uid']));
        DB::table('bs_mixed_event_grants')->insert(['event_id' => $data['event']->id, 'uid' => 424242, 'role' => 'admin', 'created' => '2026-08-01 10:00:00']);

        $lines = $this->lines();

        $this->assertContains('[WARN] orphans:bs_mixed_round_players.participant_id->bs_mixed_participants.id: 1 orphaned rows', $lines);
        $this->assertContains('[WARN] orphans:bs_mixed_round_players.round_id->bs_mixed_rounds.id: 1 orphaned rows', $lines);
        $this->assertContains('[WARN] orphans:bs_mixed_participants.event_id->bs_mixed_events.id: 1 orphaned rows', $lines);
        $this->assertContains('[WARN] orphans:bs_mixed_event_grants.uid->bs_users.uid: 1 orphaned rows', $lines);
    }

    #[Test]
    public function two_default_events_warn(): void
    {
        MixedEvent::factory()->count(2)->create(['is_default' => 1]);

        $this->assertContains('[WARN] data:default-events: 2 events are marked as default', $this->lines());
    }

    #[Test]
    public function score_sum_other_than_four_warns(): void
    {
        $data = $this->demoLikeData();
        MixedMatch::query()->where('round_id', $data['round']->id)->update(['games_a' => 3, 'games_b' => 3]);

        $this->assertContains('[WARN] data:score-sum: 1 matches do not add up to 4 games', $this->lines());
    }

    #[Test]
    public function participant_with_three_rows_in_a_round_warns(): void
    {
        $data = $this->demoLikeData();
        foreach ([2, 3] as $group) {
            DB::table('bs_mixed_round_players')->insert(['round_id' => $data['round']->id, 'participant_id' => $data['ids'][0], 'group_no' => $group]);
        }

        $this->assertContains('[WARN] data:round-player-rows: 1 participants with more than two rows in one round', $this->lines());
    }

    #[Test]
    public function filler_with_two_rows_and_sitter_with_null_group_are_fine(): void
    {
        $data = $this->demoLikeData();
        DB::table('bs_mixed_round_players')->insert(['round_id' => $data['round']->id, 'participant_id' => $data['ids'][0], 'group_no' => 2]);
        $sitter = MixedParticipant::factory()->create(['event_id' => $data['event']->id, 'label' => 'Spieler 05']);
        DB::table('bs_mixed_round_players')->insert(['round_id' => $data['round']->id, 'participant_id' => $sitter->id, 'group_no' => null]);

        $this->assertContains('[PASS] data:round-player-rows', $this->lines());
    }

    #[Test]
    public function mexicano_round_with_wrong_match_count_warns(): void
    {
        $data = $this->demoLikeData('mexicano');
        $this->assertContains('[PASS] data:mexicano-matches', $this->lines());

        DB::table('bs_mixed_round_players')->insert(['round_id' => $data['round']->id, 'participant_id' => $data['ids'][0], 'group_no' => 2]);

        $this->assertContains('[WARN] data:mexicano-matches: 1 Mexicano rounds whose match count differs from the group count', $this->lines());
    }

    #[Test]
    public function preflight_is_read_only(): void
    {
        $this->demoLikeData();
        $tables = ['bs_mixed_events', 'bs_mixed_participants', 'bs_mixed_rounds', 'bs_mixed_round_players', 'bs_mixed_matches', 'bs_mixed_event_grants'];
        $counts = fn (): array => array_map(fn (string $t): int => DB::table($t)->count(), $tables);
        $before = $counts();
        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = $query->sql;
        });

        $this->lines();

        $this->assertSame($before, $counts());
        $this->assertNotEmpty($sql);
        foreach ($sql as $statement) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|create|drop|alter|truncate|replace)\b/i', $statement);
        }
    }

    #[Test]
    public function command_runs_the_mixed_module(): void
    {
        $this->demoLikeData();

        $this->artisan('phase2:preflight', ['module' => 'mixed'])
            ->expectsOutputToContain('[SKIP] charset:bs_mixed_events: not MySQL')
            ->expectsOutputToContain('Preflight mixed:')
            ->assertExitCode(0);
    }
}
