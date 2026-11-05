<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TournamentAdoptionMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MIGRATIONS = [
        'bs_tournaments' => '2026_11_20_090001_create_bs_tournaments_table.php',
        'bs_tournament_players' => '2026_11_20_090002_create_bs_tournament_players_table.php',
        'bs_tournament_matches' => '2026_11_20_090003_create_bs_tournament_matches_table.php',
        'bs_tournament_lines' => '2026_11_20_090004_create_bs_tournament_lines_table.php',
    ];

    private function migration(string $file): object
    {
        return require base_path('app/Modules/Tournament/Database/Migrations/'.$file);
    }

    /** @return list<list<string>> */
    private function indexes(string $table, bool $unique): array
    {
        return collect(Schema::getIndexes($table))
            ->filter(fn (array $index): bool => $index['unique'] === $unique && ! $index['primary'])
            ->map(fn (array $index): array => $index['columns'])
            ->sortBy(fn (array $columns): string => implode(',', $columns))
            ->values()
            ->all();
    }

    #[Test]
    public function fresh_database_has_the_live_columns_in_order(): void
    {
        $expected = [
            'bs_tournaments' => ['tid', 'title', 'season', 'is_test', 'cloned_from', 'status', 'active', 'format', 'lot_orders',
                'itn_default', 'points_per_win', 'tiebreak_scope', 'runnerup_mode', 'created'],
            'bs_tournament_players' => ['tpid', 'tid', 'uid', 'label', 'gender', 'itn', 'itn_rated', 'seed', 'grp', 'grp_pos',
                'points_adjust', 'phone', 'partner_tpid', 'note'],
            'bs_tournament_matches' => ['tmid', 'tid', 'phase', 'mkey', 'grp', 'rnd', 'slot', 'tpid_a', 'tpid_b', 'deadline',
                'played', 'played_time', 'court', 'score', 's1a', 's1b', 's2a', 's2b', 'mtba', 'mtbb', 'sets_a', 'sets_b',
                'games_a', 'games_b', 'retired', 'winner', 'entered_uid', 'entered', 'confirmed_uid', 'confirmed'],
            'bs_tournament_lines' => ['tlid', 'tid', 'phase', 'line', 'kind', 'seed_no', 'pool', 'placeholder', 'tpid'],
        ];

        foreach ($expected as $table => $columns) {
            $this->assertSame($columns, Schema::getColumnListing($table), $table);
        }
    }

    #[Test]
    public function up_leaves_an_existing_table_untouched(): void
    {
        Schema::drop('bs_tournament_lines');
        Schema::create('bs_tournament_lines', function (Blueprint $table): void {
            $table->increments('tlid');
            $table->unsignedInteger('tid');
            $table->string('legacy_note', 20)->nullable();
        });
        DB::table('bs_tournament_lines')->insert(['tid' => 1, 'legacy_note' => 'kept']);

        $this->migration(self::MIGRATIONS['bs_tournament_lines'])->up();

        $this->assertContains('legacy_note', Schema::getColumnListing('bs_tournament_lines'));
        $this->assertSame('kept', DB::table('bs_tournament_lines')->value('legacy_note'));
        $this->assertSame(1, DB::table('bs_tournament_lines')->count());
    }

    #[Test]
    public function down_is_a_no_op_and_keeps_rows(): void
    {
        Tournament::factory()->create();
        TournamentPlayer::factory()->create();
        TournamentMatch::factory()->group('A', 1, 1)->create();
        TournamentLine::factory()->create();

        foreach (self::MIGRATIONS as $file) {
            $this->migration($file)->down();
        }

        foreach (array_keys(self::MIGRATIONS) as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
            $this->assertSame(1, DB::table($table)->count(), $table);
        }
    }

    #[Test]
    public function unique_keys_exist(): void
    {
        $this->assertSame([], $this->indexes('bs_tournaments', true));
        $this->assertSame([['tid', 'uid']], $this->indexes('bs_tournament_players', true));
        $this->assertSame([['tid', 'mkey']], $this->indexes('bs_tournament_matches', true));
        $this->assertSame([['tid', 'phase', 'line']], $this->indexes('bs_tournament_lines', true));
    }

    #[Test]
    public function plain_indexes_match_the_live_schema(): void
    {
        $this->assertSame([['active']], $this->indexes('bs_tournaments', false));
        $this->assertSame([['partner_tpid'], ['tid', 'grp']], $this->indexes('bs_tournament_players', false));
        $this->assertSame(
            [['tid', 'deadline'], ['tid', 'phase', 'grp'], ['tid', 'phase', 'rnd', 'slot']],
            $this->indexes('bs_tournament_matches', false),
        );
        $this->assertSame([['tid', 'phase', 'tpid']], $this->indexes('bs_tournament_lines', false));
    }

    #[Test]
    public function duplicate_match_key_is_rejected(): void
    {
        TournamentMatch::factory()->create(['tid' => 1, 'mkey' => 'A-1']);

        $this->expectException(QueryException::class);

        TournamentMatch::factory()->create(['tid' => 1, 'mkey' => 'A-1']);
    }

    #[Test]
    public function null_match_keys_do_not_collide(): void
    {
        TournamentMatch::factory()->count(2)->create(['tid' => 1, 'mkey' => null]);

        $this->assertSame(2, TournamentMatch::query()->count());
    }
}
