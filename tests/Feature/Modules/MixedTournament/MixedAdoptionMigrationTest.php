<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MixedAdoptionMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MIGRATIONS = [
        'bs_mixed_events' => '2026_11_06_100001_create_bs_mixed_events_table.php',
        'bs_mixed_participants' => '2026_11_06_100002_create_bs_mixed_participants_table.php',
        'bs_mixed_rounds' => '2026_11_06_100003_create_bs_mixed_rounds_table.php',
        'bs_mixed_round_players' => '2026_11_06_100004_create_bs_mixed_round_players_table.php',
        'bs_mixed_matches' => '2026_11_06_100005_create_bs_mixed_matches_table.php',
        'bs_mixed_avoid_pairs' => '2026_11_06_100006_create_bs_mixed_avoid_pairs_table.php',
        'bs_mixed_members' => '2026_11_06_100007_create_bs_mixed_members_table.php',
        'bs_mixed_event_grants' => '2026_11_06_100008_create_bs_mixed_event_grants_table.php',
        'bs_mixed_schema_versions' => '2026_11_06_100009_create_bs_mixed_schema_versions_table.php',
    ];

    private function migration(string $file): object
    {
        return require base_path('app/Modules/MixedTournament/Database/Migrations/'.$file);
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

    /** @return list<int> */
    private function versions(): array
    {
        return DB::table('bs_mixed_schema_versions')->pluck('version')->map(fn ($v): int => (int) $v)->all();
    }

    #[Test]
    public function fresh_database_has_the_live_columns(): void
    {
        $expected = [
            'bs_mixed_events' => ['id', 'title', 'event_date', 'status', 'is_default', 'is_test', 'court_count', 'mode', 'created'],
            'bs_mixed_participants' => ['id', 'event_id', 'uid', 'label', 'gender', 'itn', 'age', 'status', 'no_extra_match'],
            'bs_mixed_rounds' => ['id', 'event_id', 'round_no', 'group_size_mode', 'distribution', 'status'],
            'bs_mixed_round_players' => ['id', 'round_id', 'participant_id', 'group_no'],
            'bs_mixed_matches' => ['id', 'round_id', 'group_no', 'match_no', 'team_a_p1', 'team_a_p2', 'team_b_p1', 'team_b_p2', 'games_a', 'games_b', 'is_extra'],
            'bs_mixed_avoid_pairs' => ['id', 'event_id', 'participant_a', 'participant_b'],
            'bs_mixed_members' => ['id', 'label', 'gender', 'itn', 'birth_date'],
            'bs_mixed_event_grants' => ['id', 'event_id', 'uid', 'role', 'created'],
            'bs_mixed_schema_versions' => ['version', 'applied'],
        ];

        foreach ($expected as $table => $columns) {
            $this->assertSame($columns, Schema::getColumnListing($table), $table);
        }
    }

    #[Test]
    public function round_players_has_no_unique_key_and_accepts_two_rows(): void
    {
        $this->assertSame([], $this->indexes('bs_mixed_round_players', true));
        $this->assertSame([['round_id', 'group_no'], ['round_id', 'participant_id']], $this->indexes('bs_mixed_round_players', false));

        DB::table('bs_mixed_round_players')->insert(['round_id' => 1, 'participant_id' => 7, 'group_no' => 0]);
        DB::table('bs_mixed_round_players')->insert(['round_id' => 1, 'participant_id' => 7, 'group_no' => 3]);

        $this->assertSame(2, DB::table('bs_mixed_round_players')->where('participant_id', 7)->count());
    }

    #[Test]
    public function unique_keys_exist(): void
    {
        $this->assertSame([['event_id', 'round_no']], $this->indexes('bs_mixed_rounds', true));
        $this->assertSame([['event_id', 'uid']], $this->indexes('bs_mixed_participants', true));
        $this->assertSame([['event_id', 'uid']], $this->indexes('bs_mixed_event_grants', true));
        $this->assertSame([['event_id', 'participant_a', 'participant_b']], $this->indexes('bs_mixed_avoid_pairs', true));
        foreach (['bs_mixed_events', 'bs_mixed_matches', 'bs_mixed_members'] as $table) {
            $this->assertSame([], $this->indexes($table, true), $table);
        }
    }

    #[Test]
    public function plain_indexes_match_the_live_schema(): void
    {
        $this->assertSame([['event_id']], $this->indexes('bs_mixed_participants', false));
        $this->assertSame([['round_id', 'group_no']], $this->indexes('bs_mixed_matches', false));
        $this->assertSame([['event_id']], $this->indexes('bs_mixed_avoid_pairs', false));
        $this->assertSame([['label']], $this->indexes('bs_mixed_members', false));
        $this->assertSame([['event_id'], ['uid']], $this->indexes('bs_mixed_event_grants', false));
    }

    #[Test]
    public function grant_rows_cascade_with_the_user(): void
    {
        $user = User::factory()->create();
        DB::table('bs_mixed_event_grants')->insert([
            'event_id' => 1, 'uid' => $user->uid, 'role' => 'score', 'created' => '2027-04-01 09:00:00',
        ]);
        $this->assertSame(1, DB::table('bs_mixed_event_grants')->count());

        DB::table('bs_users')->where('uid', $user->uid)->delete();

        $this->assertSame(0, DB::table('bs_mixed_event_grants')->count());
    }

    #[Test]
    public function schema_version_row_is_20260803(): void
    {
        $this->assertSame([20260803], $this->versions());
    }

    #[Test]
    public function up_leaves_an_existing_table_untouched(): void
    {
        Schema::drop('bs_mixed_participants');
        Schema::create('bs_mixed_participants', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('event_id');
            $table->string('label', 120);
            $table->date('birth_date')->nullable();
        });
        DB::table('bs_mixed_participants')->insert(['event_id' => 1, 'label' => 'Spieler 01', 'birth_date' => '1990-01-01']);

        $this->migration(self::MIGRATIONS['bs_mixed_participants'])->up();

        $this->assertContains('birth_date', Schema::getColumnListing('bs_mixed_participants'));
        $this->assertSame(1, DB::table('bs_mixed_participants')->count());
    }

    #[Test]
    public function up_never_touches_an_existing_schema_version(): void
    {
        DB::table('bs_mixed_schema_versions')->delete();
        DB::table('bs_mixed_schema_versions')->insert(['version' => 20260701, 'applied' => '2026-07-01 00:00:00']);

        $this->migration(self::MIGRATIONS['bs_mixed_schema_versions'])->up();

        $this->assertSame([20260701], $this->versions());

        DB::table('bs_mixed_schema_versions')->delete();
        $this->migration(self::MIGRATIONS['bs_mixed_schema_versions'])->up();

        $this->assertSame([20260803], $this->versions());
    }

    #[Test]
    public function down_is_a_no_op_and_keeps_rows(): void
    {
        DB::table('bs_mixed_members')->insert(['label' => 'Spieler 01']);

        foreach (self::MIGRATIONS as $file) {
            $this->migration($file)->down();
        }

        foreach (array_keys(self::MIGRATIONS) as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
        $this->assertSame(1, DB::table('bs_mixed_members')->count());
    }
}
