<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PyramidAdoptionMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MIGRATIONS = [
        'bs_pyramid_ladders' => '2026_11_06_090001_create_bs_pyramid_ladders_table.php',
        'bs_pyramid_players' => '2026_11_06_090002_create_bs_pyramid_players_table.php',
        'bs_pyramid_challenges' => '2026_11_06_090003_create_bs_pyramid_challenges_table.php',
        'bs_pyramid_events' => '2026_11_06_090004_create_bs_pyramid_events_table.php',
        'bs_pyramid_grants' => '2026_11_06_090005_create_bs_pyramid_grants_table.php',
        'bs_pyramid_schema_versions' => '2026_11_06_090006_create_bs_pyramid_schema_versions_table.php',
    ];

    private const EMAIL_MIGRATION = '2026_11_06_090007_add_email_to_bs_pyramid_players_table.php';

    private function migration(string $file): object
    {
        return require base_path('app/Modules/Pyramid/Database/Migrations/'.$file);
    }

    #[Test]
    public function fresh_database_has_the_live_columns(): void
    {
        $this->assertSame(
            ['id', 'title', 'season', 'category', 'status', 'itn_default', 'days_to_play', 'winner_protected_days',
                'loser_cooldown_days', 'is_default', 'rules_version', 'created'],
            Schema::getColumnListing('bs_pyramid_ladders'),
        );
        $this->assertSame(
            ['id', 'ladder_id', 'uid', 'label', 'itn', 'itn_rated', 'position', 'status', 'gender', 'age', 'phone',
                'email', 'joined', 'note'],
            Schema::getColumnListing('bs_pyramid_players'),
        );
        $this->assertSame(
            ['id', 'ladder_id', 'challenger_id', 'opponent_id', 'challenger_pos', 'opponent_pos', 'status', 'created',
                'deadline', 'scheduled_at', 'score', 'winner_id', 'entered_uid', 'entered', 'decline_reason',
                'admin_note', 'resolved'],
            Schema::getColumnListing('bs_pyramid_challenges'),
        );
        $this->assertSame(
            ['id', 'ladder_id', 'challenge_id', 'type', 'actor_uid', 'payload', 'created'],
            Schema::getColumnListing('bs_pyramid_events'),
        );
        $this->assertSame(
            ['id', 'ladder_id', 'uid', 'role', 'created'],
            Schema::getColumnListing('bs_pyramid_grants'),
        );
        $this->assertSame(['version', 'applied'], Schema::getColumnListing('bs_pyramid_schema_versions'));
    }

    #[Test]
    public function unique_and_plain_indexes_match_the_live_schema(): void
    {
        $indexes = fn (string $table, bool $unique): array => collect(Schema::getIndexes($table))
            ->filter(fn (array $index): bool => $index['unique'] === $unique && ! $index['primary'])
            ->map(fn (array $index): array => $index['columns'])
            ->sortBy(fn (array $columns): string => implode(',', $columns))
            ->values()
            ->all();

        $this->assertSame([['ladder_id', 'position'], ['ladder_id', 'uid']], $indexes('bs_pyramid_players', true));
        $this->assertSame([['ladder_id', 'uid']], $indexes('bs_pyramid_grants', true));
        foreach (['bs_pyramid_ladders', 'bs_pyramid_challenges', 'bs_pyramid_events'] as $table) {
            $this->assertSame([], $indexes($table, true), $table);
        }

        $this->assertSame([['season', 'category']], $indexes('bs_pyramid_ladders', false));
        $this->assertSame([['ladder_id', 'status']], $indexes('bs_pyramid_players', false));
        $this->assertSame(
            [['challenger_id'], ['ladder_id', 'deadline'], ['ladder_id', 'status'], ['opponent_id']],
            $indexes('bs_pyramid_challenges', false),
        );
        $this->assertSame([['ladder_id', 'created']], $indexes('bs_pyramid_events', false));
        $this->assertSame([['ladder_id']], $indexes('bs_pyramid_grants', false));

        $primary = collect(Schema::getIndexes('bs_pyramid_schema_versions'))
            ->firstWhere('primary', true);
        $this->assertSame(['version'], $primary['columns']);
    }

    #[Test]
    public function up_leaves_an_existing_table_untouched(): void
    {
        Schema::drop('bs_pyramid_grants');
        Schema::create('bs_pyramid_grants', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('ladder_id');
            $table->unsignedInteger('uid');
            $table->string('legacy_note')->nullable();
        });
        DB::table('bs_pyramid_grants')->insert(['ladder_id' => 1, 'uid' => 5, 'legacy_note' => 'keep']);

        $this->migration(self::MIGRATIONS['bs_pyramid_grants'])->up();

        $this->assertContains('legacy_note', Schema::getColumnListing('bs_pyramid_grants'));
        $this->assertSame(1, DB::table('bs_pyramid_grants')->count());
    }

    #[Test]
    public function down_is_a_no_op_and_keeps_rows(): void
    {
        PyramidLadder::factory()->withRanking(3)->create();

        foreach (self::MIGRATIONS as $file) {
            $this->migration($file)->down();
        }
        $this->migration(self::EMAIL_MIGRATION)->down();

        foreach (array_keys(self::MIGRATIONS) as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
        $this->assertSame(3, PyramidPlayer::count());
    }

    #[Test]
    public function email_migration_adds_the_column_once(): void
    {
        Schema::drop('bs_pyramid_players');
        Schema::create('bs_pyramid_players', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('ladder_id');
            $table->string('label', 120);
            $table->string('phone', 40)->nullable();
        });
        DB::table('bs_pyramid_players')->insert(['ladder_id' => 1, 'label' => 'Spieler 1']);
        // The fresh schema already ran the migration: reset to the pre-20260806 state.
        DB::table('bs_pyramid_schema_versions')->where('version', 20260806)->delete();
        $this->assertFalse(Schema::hasColumn('bs_pyramid_players', 'email'));
        $this->assertSame(0, DB::table('bs_pyramid_schema_versions')->where('version', 20260806)->count());

        $migration = $this->migration(self::EMAIL_MIGRATION);
        $migration->up();

        $this->assertTrue(Schema::hasColumn('bs_pyramid_players', 'email'));
        $this->assertSame(1, DB::table('bs_pyramid_schema_versions')->where('version', 20260806)->count());

        $migration->up();

        $this->assertSame(1, DB::table('bs_pyramid_players')->count());
        $this->assertSame(1, DB::table('bs_pyramid_schema_versions')->where('version', 20260806)->count());
    }

    #[Test]
    public function email_migration_without_a_versions_table_adds_the_column_and_inserts_nothing(): void
    {
        Schema::drop('bs_pyramid_players');
        Schema::create('bs_pyramid_players', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('ladder_id');
        });
        Schema::drop('bs_pyramid_schema_versions');

        $this->migration(self::EMAIL_MIGRATION)->up();

        $this->assertTrue(Schema::hasColumn('bs_pyramid_players', 'email'));
        $this->assertFalse(Schema::hasTable('bs_pyramid_schema_versions'));
    }

    #[Test]
    public function email_migration_skips_a_missing_table(): void
    {
        Schema::drop('bs_pyramid_players');

        $this->migration(self::EMAIL_MIGRATION)->up();

        $this->assertFalse(Schema::hasTable('bs_pyramid_players'));
    }
}
