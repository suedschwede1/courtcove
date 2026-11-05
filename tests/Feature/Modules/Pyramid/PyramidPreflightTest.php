<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Modules\Pyramid\Database\Seeders\PyramidDemoSeeder;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidEvent;
use App\Modules\Pyramid\Preflight\PyramidPreflight;
use App\Modules\TournamentBase\Preflight\PreflightResult;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsPyramidLadders;
use Tests\TestCase;

/**
 * Preflight checks on the SQLite test database. Checks that need a real MySQL server are skipped there
 * (charset: "[SKIP] ... not MySQL"), and `FOR UPDATE` lock detection is not possible on SQLite at all
 * (`lockForUpdate()` compiles to nothing): lock order and ladder-row serialisation are only proven by
 * `PyramidMysqlLockTest`, which runs with `composer test:mysql`.
 */
class PyramidPreflightTest extends TestCase
{
    use BuildsPyramidLadders;
    use LazilyRefreshDatabase;

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        return array_map(fn (PreflightResult $r): string => $r->line(), app(PyramidPreflight::class)->run());
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

    #[Test]
    public function healthy_database_passes(): void
    {
        $this->seed(PyramidDemoSeeder::class);

        $lines = $this->lines();

        $this->assertSame([], array_values(array_filter($lines, fn (string $l): bool => str_starts_with($l, '[FAIL]'))));
        $this->assertContains('[SKIP] charset:bs_pyramid_players: not MySQL', $lines);
        $this->assertTrue($this->has('[PASS] schema-version'));
        $this->assertTrue($this->has('[PASS] unique:bs_pyramid_players(ladder_id,position)'));
    }

    #[Test]
    public function missing_email_column_fails(): void
    {
        Schema::table('bs_pyramid_players', function (Blueprint $table): void {
            $table->dropColumn('email');
        });

        $this->assertContains('[FAIL] columns:bs_pyramid_players: missing column email', $this->lines());
    }

    #[Test]
    public function missing_table_fails_early(): void
    {
        Schema::drop('bs_pyramid_grants');

        $this->assertSame(['[FAIL] tables: missing bs_pyramid_grants'], $this->lines());
    }

    #[Test]
    public function old_schema_version_warns(): void
    {
        DB::table('bs_pyramid_schema_versions')->where('version', 20260806)->delete();

        $this->assertTrue($this->has('[WARN] schema-version'));
    }

    #[Test]
    public function duplicate_positions_fail(): void
    {
        $ladder = $this->makeLadder(2);
        Schema::table('bs_pyramid_players', function (Blueprint $table): void {
            $table->dropUnique(['ladder_id', 'position']);
        });
        DB::table('bs_pyramid_players')->where('ladder_id', $ladder->id)->update(['position' => 1]);

        $this->assertTrue($this->has('[FAIL] unique:bs_pyramid_players(ladder_id,position): unique index missing, 1 duplicate groups'));
    }

    #[Test]
    public function duplicate_grants_fail(): void
    {
        $ladder = $this->makeLadder(1);
        Schema::table('bs_pyramid_grants', function (Blueprint $table): void {
            $table->dropUnique(['ladder_id', 'uid']);
        });
        DB::table('bs_pyramid_grants')->insert([
            ['ladder_id' => $ladder->id, 'uid' => 5, 'role' => 'admin', 'created' => '2026-08-01 10:00:00'],
            ['ladder_id' => $ladder->id, 'uid' => 5, 'role' => 'admin', 'created' => '2026-08-01 10:00:00'],
        ]);

        $this->assertTrue($this->has('[FAIL] unique:bs_pyramid_grants(ladder_id,uid)'));
    }

    #[Test]
    public function ranking_gap_warns(): void
    {
        $healthy = $this->makeLadder(2);
        $ladder = $this->makeLadder(4);
        DB::table('bs_pyramid_players')->where('ladder_id', $ladder->id)->where('position', 3)->update(['position' => 9]);

        $this->assertContains("[PASS] ranking:{$healthy->id}", $this->lines());
        $this->assertTrue($this->has("[WARN] ranking:{$ladder->id}: Lücken oder Dubletten in der Positionsfolge"));
        $this->assertFalse($this->has('[FAIL]'));
    }

    #[Test]
    public function active_player_without_position_warns(): void
    {
        $ladder = $this->makeLadder(3);
        DB::table('bs_pyramid_players')->where('ladder_id', $ladder->id)->where('position', 3)->update(['position' => null]);

        $this->assertTrue($this->has("[WARN] ranking:{$ladder->id}: 1 aktive oder pausierte Teilnehmer ohne Position"));
    }

    #[Test]
    public function orphan_challenge_warns(): void
    {
        $ladder = $this->makeLadder(2);
        PyramidChallenge::factory()->create(['ladder_id' => $ladder->id, 'challenger_id' => 9999, 'opponent_id' => 9998]);
        PyramidChallenge::factory()->create(['ladder_id' => 8888, 'challenger_id' => 9999, 'opponent_id' => 9998]);

        $this->assertTrue($this->has('[WARN] orphans:bs_pyramid_challenges.challenger_id->bs_pyramid_players.id: 2 orphaned rows'));
        $this->assertTrue($this->has('[WARN] orphans:bs_pyramid_challenges.opponent_id->bs_pyramid_players.id: 2 orphaned rows'));
        $this->assertTrue($this->has('[WARN] orphans:bs_pyramid_challenges.ladder_id->bs_pyramid_ladders.id: 1 orphaned rows'));
    }

    #[Test]
    public function player_with_unknown_uid_warns(): void
    {
        $ladder = $this->makeLadder(1);
        DB::table('bs_pyramid_players')->where('ladder_id', $ladder->id)->update(['uid' => 424242]);

        $this->assertTrue($this->has('[WARN] orphans:bs_pyramid_players.uid->bs_users.uid: 1 orphaned rows'));
    }

    #[Test]
    public function double_open_challenge_warns(): void
    {
        $ladder = $this->makeLadder(4);
        $one = $this->playerAt($ladder, 1);
        $two = $this->playerAt($ladder, 2);
        $three = $this->playerAt($ladder, 3);
        PyramidChallenge::factory()->create(['ladder_id' => $ladder->id, 'challenger_id' => $two->id, 'opponent_id' => $one->id]);
        PyramidChallenge::factory()->create(['ladder_id' => $ladder->id, 'challenger_id' => $three->id, 'opponent_id' => $one->id, 'status' => 'scheduled']);

        $this->assertContains('[WARN] open-per-player: 1 players in more than one open challenge', $this->lines());
    }

    #[Test]
    public function single_open_challenges_pass(): void
    {
        $ladder = $this->makeLadder(4);
        PyramidChallenge::factory()->create(['ladder_id' => $ladder->id, 'challenger_id' => $this->playerAt($ladder, 2)->id, 'opponent_id' => $this->playerAt($ladder, 1)->id]);
        PyramidChallenge::factory()->create(['ladder_id' => $ladder->id, 'challenger_id' => $this->playerAt($ladder, 4)->id, 'opponent_id' => $this->playerAt($ladder, 3)->id, 'status' => 'played']);

        $this->assertContains('[PASS] open-per-player', $this->lines());
    }

    #[Test]
    public function undecodable_event_payload_warns(): void
    {
        $ladder = $this->makeLadder(1);
        PyramidEvent::query()->create(['ladder_id' => $ladder->id, 'type' => 'admin', 'payload' => '{"message":', 'created' => '2026-08-01 10:00:00']);
        PyramidEvent::query()->create(['ladder_id' => $ladder->id, 'type' => 'admin', 'payload' => '{"message":"ok"}', 'created' => '2026-08-01 10:00:00']);

        $this->assertContains('[WARN] json:bs_pyramid_events: 1 of 2 payloads do not decode', $this->lines());
    }

    #[Test]
    public function duplicate_player_uid_fails(): void
    {
        $ladder = $this->makeLadder(2);
        Schema::table('bs_pyramid_players', function (Blueprint $table): void {
            $table->dropUnique(['ladder_id', 'uid']);
        });
        DB::table('bs_pyramid_players')->where('ladder_id', $ladder->id)->update(['uid' => 7]);

        $this->assertTrue($this->has('[FAIL] unique:bs_pyramid_players(ladder_id,uid): unique index missing, 1 duplicate groups'));
    }

    #[Test]
    public function duplicate_player_uid_with_index_fails(): void
    {
        $ladder = $this->makeLadder(1);
        DB::table('bs_pyramid_players')->where('ladder_id', $ladder->id)->update(['uid' => 7]);
        $row = (array) DB::table('bs_pyramid_players')->where('ladder_id', $ladder->id)->first();
        Schema::table('bs_pyramid_players', function (Blueprint $table): void {
            $table->dropUnique(['ladder_id', 'uid']);
        });
        unset($row['id']);
        $row['position'] = 2;
        DB::table('bs_pyramid_players')->insert($row);
        Schema::table('bs_pyramid_players', function (Blueprint $table): void {
            $table->index(['ladder_id', 'uid']);
        });

        $this->assertTrue($this->has('[FAIL] unique:bs_pyramid_players(ladder_id,uid): unique index missing, 1 duplicate groups'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function orphanCases(): array
    {
        return [
            'players.ladder_id' => ['orphans:bs_pyramid_players.ladder_id->bs_pyramid_ladders.id'],
            'events.ladder_id' => ['orphans:bs_pyramid_events.ladder_id->bs_pyramid_ladders.id'],
            'grants.ladder_id' => ['orphans:bs_pyramid_grants.ladder_id->bs_pyramid_ladders.id'],
            'challenges.winner_id' => ['orphans:bs_pyramid_challenges.winner_id->bs_pyramid_players.id'],
            'grants.uid' => ['orphans:bs_pyramid_grants.uid->bs_users.uid'],
        ];
    }

    #[Test]
    #[DataProvider('orphanCases')]
    public function orphan_references_warn(string $check): void
    {
        $ladder = $this->makeLadder(2);
        $now = '2026-08-01 10:00:00';

        match ($check) {
            'orphans:bs_pyramid_players.ladder_id->bs_pyramid_ladders.id' => DB::table('bs_pyramid_players')
                ->where('ladder_id', $ladder->id)->where('position', 2)->update(['ladder_id' => 8888]),
            'orphans:bs_pyramid_events.ladder_id->bs_pyramid_ladders.id' => DB::table('bs_pyramid_events')
                ->insert(['ladder_id' => 8888, 'type' => 'admin', 'payload' => '{}', 'created' => $now]),
            'orphans:bs_pyramid_grants.ladder_id->bs_pyramid_ladders.id' => DB::table('bs_pyramid_grants')
                ->insert(['ladder_id' => 8888, 'uid' => 5, 'role' => 'admin', 'created' => $now]),
            'orphans:bs_pyramid_challenges.winner_id->bs_pyramid_players.id' => PyramidChallenge::factory()->create([
                'ladder_id' => $ladder->id,
                'challenger_id' => $this->playerAt($ladder, 2)->id,
                'opponent_id' => $this->playerAt($ladder, 1)->id,
                'winner_id' => 9999,
            ]),
            'orphans:bs_pyramid_grants.uid->bs_users.uid' => DB::table('bs_pyramid_grants')
                ->insert(['ladder_id' => $ladder->id, 'uid' => 424242, 'role' => 'admin', 'created' => $now]),
        };

        $this->assertContains("[WARN] {$check}: 1 orphaned rows", $this->lines());
    }

    #[Test]
    public function waiting_player_with_position_warns(): void
    {
        $ladder = $this->makeLadder(3);
        DB::table('bs_pyramid_players')->where('ladder_id', $ladder->id)->where('position', 3)->update(['status' => 'waiting']);
        DB::table('bs_pyramid_players')->where('ladder_id', $ladder->id)->where('position', 2)->update(['status' => 'removed']);

        $this->assertTrue($this->has("[WARN] ranking:{$ladder->id}: 2 wartende oder entfernte Teilnehmer mit gesetzter Position"));
    }

    #[Test]
    public function unexpected_extra_column_warns(): void
    {
        Schema::table('bs_pyramid_players', function (Blueprint $table): void {
            $table->string('nickname', 20)->nullable();
        });

        $this->assertContains('[WARN] columns:bs_pyramid_players: unexpected extra column nickname', $this->lines());
        $this->assertFalse($this->has('[FAIL]'));
    }

    #[Test]
    public function challenge_against_oneself_counts_once(): void
    {
        $ladder = $this->makeLadder(2);
        $one = $this->playerAt($ladder, 1);
        PyramidChallenge::factory()->create(['ladder_id' => $ladder->id, 'challenger_id' => $one->id, 'opponent_id' => $one->id]);

        $this->assertContains('[PASS] open-per-player', $this->lines());
    }

    #[Test]
    public function broken_ladders_table_fails_instead_of_throwing(): void
    {
        Schema::table('bs_pyramid_ladders', function (Blueprint $table): void {
            $table->dropColumn('rules_version');
        });

        $lines = $this->lines();

        $this->assertContains('[FAIL] columns:bs_pyramid_ladders: missing column rules_version', $lines);
        $this->assertContains('[FAIL] ranking: bs_pyramid_ladders columns are absent or incomplete', $lines);
    }

    #[Test]
    public function preflight_is_read_only(): void
    {
        $this->seed(PyramidDemoSeeder::class);
        $tables = ['bs_pyramid_ladders', 'bs_pyramid_players', 'bs_pyramid_challenges', 'bs_pyramid_events', 'bs_pyramid_grants'];
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
    public function command_runs_the_pyramid_module(): void
    {
        $this->seed(PyramidDemoSeeder::class);

        $this->artisan('phase2:preflight', ['module' => 'pyramid'])
            ->expectsOutputToContain('[SKIP] charset:bs_pyramid_ladders: not MySQL')
            ->assertExitCode(0);
    }
}
