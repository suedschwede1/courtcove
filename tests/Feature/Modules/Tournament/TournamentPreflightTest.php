<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Draw\Models\Draw;
use App\Modules\Tournament\Database\Seeders\TournamentDemoSeeder;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Preflight\TournamentPreflight;
use App\Modules\TournamentBase\Preflight\PreflightResult;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Preflight checks on the SQLite test database; charset and column types need MySQL (skipped here,
 * covered by TournamentMysqlAdoptionTest).
 */
class TournamentPreflightTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return list<string> */
    private function lines(): array
    {
        return array_map(fn (PreflightResult $r): string => $r->line(), app(TournamentPreflight::class)->run());
    }

    /** @return list<string> */
    private function withStatus(string $status): array
    {
        return array_values(array_filter($this->lines(), fn (string $l): bool => str_starts_with($l, "[{$status}]")));
    }

    #[Test]
    public function healthy_database_passes(): void
    {
        $this->seed(TournamentDemoSeeder::class);

        $lines = $this->lines();

        $this->assertSame([], $this->withStatus('FAIL'));
        $this->assertSame([], $this->withStatus('WARN'));
        $this->assertContains('[SKIP] charset:bs_tournaments: not MySQL', $lines);
        $this->assertContains('[PASS] unique:bs_tournament_matches(tid,mkey)', $lines);
        $this->assertContains('[PASS] unique:bs_tournament_players(tid,uid)', $lines);
        $this->assertContains('[PASS] unique:bs_tournament_lines(tid,phase,line)', $lines);
        $this->assertContains('[PASS] format: 3 formats valid', $lines);
        $this->assertContains('[PASS] lot_orders', $lines);
        $this->assertContains('[PASS] data:status: finished 1, setup 2 (informational, status is unmaintained)', $lines);
        $this->assertContains('[PASS] orphans:bs_draws.tid->bs_tournaments.tid', $lines);
        $this->assertSame(1, count(array_filter($lines, fn (string $l): bool => str_starts_with($l, '[PASS] data:active-tournament: tid '))));
    }

    #[Test]
    public function missing_table_fails_early(): void
    {
        Schema::drop('bs_tournament_lines');

        $this->assertSame(['[FAIL] tables: missing bs_tournament_lines'], $this->lines());
    }

    #[Test]
    public function missing_column_fails(): void
    {
        Schema::table('bs_tournament_players', function (Blueprint $table): void {
            $table->dropIndex(['partner_tpid']);
            $table->dropColumn('partner_tpid');
        });

        $this->assertContains('[FAIL] columns:bs_tournament_players: missing column partner_tpid', $this->lines());
    }

    #[Test]
    public function duplicate_match_keys_fail_with_tmids(): void
    {
        $t = Tournament::factory()->active()->create();
        Schema::table('bs_tournament_matches', function (Blueprint $table): void {
            $table->dropUnique(['tid', 'mkey']);
        });
        $ids = [];
        foreach (['A-0', 'A-0', 'B-1', 'A-1', 'B-1', 'B-1'] as $key) {
            $ids[] = (int) TournamentMatch::factory()->create(['tid' => $t->tid, 'mkey' => $key, 'grp' => $key[0]])->tmid;
        }
        TournamentMatch::factory()->count(2)->create(['tid' => $t->tid, 'mkey' => null]);

        $this->assertContains(
            "[FAIL] unique:bs_tournament_matches(tid,mkey): unique index missing, 2 duplicate groups (tid {$t->tid} A-0: tmids {$ids[0]}, {$ids[1]}; tid {$t->tid} B-1: tmids {$ids[2]}, {$ids[4]}, {$ids[5]})",
            $this->lines(),
        );
    }

    #[Test]
    public function missing_match_key_index_fails(): void
    {
        Schema::table('bs_tournament_matches', function (Blueprint $table): void {
            $table->dropUnique(['tid', 'mkey']);
        });

        $this->assertContains('[FAIL] unique:bs_tournament_matches(tid,mkey): unique index missing', $this->lines());
    }

    #[Test]
    public function invalid_format_fails(): void
    {
        $good = Tournament::factory()->active()->create();
        $broken = Tournament::factory()->create(['format' => '{"phases":']);
        $empty = Tournament::factory()->create(['format' => null]);
        $invalid = Tournament::factory()->create(['format' => json_encode(['version' => 1, 'phases' => []])]);

        $lines = $this->lines();

        $this->assertContains("[FAIL] format:{$broken->tid}: format is not valid JSON", $lines);
        $this->assertContains("[FAIL] format:{$empty->tid}: no format", $lines);
        $this->assertContains("[FAIL] format:{$invalid->tid}: format defines no phases", $lines);
        $this->assertContains('[FAIL] format: 3 of 4 formats invalid', $lines);
        $this->assertSame([], array_values(array_filter($lines, fn (string $l): bool => str_starts_with($l, "[FAIL] format:{$good->tid}:"))));
    }

    #[Test]
    public function bad_lot_orders_fail(): void
    {
        Tournament::factory()->active()->withLotOrders(['group:A' => [3, 1, 2]])->create();
        Tournament::factory()->create(['lot_orders' => '[]']);
        $list = Tournament::factory()->create(['lot_orders' => '[[1,2]]']);
        $strings = Tournament::factory()->create(['lot_orders' => '{"group:B":["1","2"]}']);
        $scalar = Tournament::factory()->create(['lot_orders' => '{"group:C":5}']);
        $garbage = Tournament::factory()->create(['lot_orders' => 'nope']);

        $this->assertSame([
            "[FAIL] lot_orders:{$list->tid}: a list, expected an object of scopes",
            "[FAIL] lot_orders:{$strings->tid}: scope group:B is not a list of player ids",
            "[FAIL] lot_orders:{$scalar->tid}: scope group:C is not a list of player ids",
            "[FAIL] lot_orders:{$garbage->tid}: not JSON",
        ], array_values(array_filter($this->lines(), fn (string $l): bool => str_starts_with($l, '[FAIL] lot_orders'))));
    }

    #[Test]
    public function orphans_warn(): void
    {
        $t = Tournament::factory()->active()->create();
        $player = TournamentPlayer::factory()->create(['tid' => $t->tid]);
        TournamentPlayer::factory()->create(['tid' => 9999]);
        TournamentPlayer::factory()->create(['tid' => $t->tid, 'partner_tpid' => 8888]);
        TournamentMatch::factory()->create(['tid' => $t->tid, 'mkey' => 'A-0', 'tpid_a' => $player->tpid, 'tpid_b' => 7777, 'winner' => 7777]);
        TournamentMatch::factory()->create(['tid' => 9999, 'mkey' => 'A-0']);
        DB::table('bs_tournament_lines')->insert(['tid' => 9999, 'phase' => 'KO', 'line' => 1, 'kind' => 'seed']);
        Draw::factory()->create(['tid' => 9999]);
        Draw::factory()->create(['tid' => null]);

        $warnings = $this->withStatus('WARN');

        $this->assertSame([
            '[WARN] orphans:bs_tournament_players.tid->bs_tournaments.tid: 1 orphaned rows',
            '[WARN] orphans:bs_tournament_matches.tid->bs_tournaments.tid: 1 orphaned rows',
            '[WARN] orphans:bs_tournament_lines.tid->bs_tournaments.tid: 1 orphaned rows',
            '[WARN] orphans:bs_tournament_matches.tpid_b->bs_tournament_players.tpid: 1 orphaned rows',
            '[WARN] orphans:bs_tournament_matches.winner->bs_tournament_players.tpid: 1 orphaned rows',
            '[WARN] orphans:bs_tournament_players.partner_tpid->bs_tournament_players.tpid: 1 orphaned rows',
            '[WARN] orphans:bs_draws.tid->bs_tournaments.tid: 1 orphaned rows',
        ], $warnings);
        $this->assertContains('[PASS] orphans:bs_tournament_matches.tpid_a->bs_tournament_players.tpid', $this->lines());
    }

    #[Test]
    public function active_tournament_count_warns(): void
    {
        Tournament::factory()->create();
        Tournament::factory()->test()->active()->create();

        $this->assertContains('[WARN] data:active-tournament: 0 real tournaments are active, expected exactly 1', $this->lines());

        $a = Tournament::factory()->active()->create();
        $b = Tournament::factory()->active()->create();

        $this->assertContains("[WARN] data:active-tournament: 2 real tournaments are active, expected exactly 1 (tids {$a->tid}, {$b->tid})", $this->lines());
    }

    #[Test]
    public function preflight_is_read_only(): void
    {
        $this->seed(TournamentDemoSeeder::class);
        Tournament::factory()->create(['format' => 'broken', 'lot_orders' => 'nope']);

        $statements = [];
        DB::listen(function (QueryExecuted $q) use (&$statements): void {
            $statements[] = $q->sql;
        });

        app(TournamentPreflight::class)->run();

        $this->assertNotEmpty($statements);
        foreach ($statements as $sql) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $sql);
        }
    }

    #[Test]
    public function command_runs_tournament_module(): void
    {
        $this->seed(TournamentDemoSeeder::class);

        $code = Artisan::call('phase2:preflight', ['module' => 'tournament']);
        $output = Artisan::output();

        $this->assertSame(0, $code, $output);
        $this->assertMatchesRegularExpression('/Preflight tournament: \d+ PASS, 0 WARN, 0 FAIL, \d+ SKIP/', $output);
        $this->assertContains('tournament', collect($this->app->tagged('phase2.preflight'))->map->module()->all());

        Tournament::query()->update(['format' => null]);
        $this->assertSame(1, Artisan::call('phase2:preflight', ['module' => 'tournament']));
    }
}
