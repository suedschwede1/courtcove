<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\RebuildsTournamentMysqlTables;
use Tests\MysqlTestCase;

/**
 * The guarded Tournament migrations against the live table shape (tests/Fixtures/tournament/live-schema.sql)
 * on a real MySQL server.
 */
#[Group('mysql')]
class TournamentMysqlAdoptionTest extends MysqlTestCase
{
    use RebuildsTournamentMysqlTables;

    private const TABLES = ['bs_tournament_lines', 'bs_tournament_matches', 'bs_tournament_players', 'bs_tournaments'];

    private function loadLiveShape(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }

        $this->loadSqlFixture('tests/Fixtures/tournament/live-schema.sql');
    }

    /** @return list<string> */
    private function indexNames(string $table): array
    {
        return array_column(Schema::getIndexes($table), 'name');
    }

    #[Test]
    public function guarded_migrations_adopt_the_live_shape(): void
    {
        $this->loadLiveShape();

        $tid = DB::table('bs_tournaments')->insertGetId([
            'title' => 'Demo-Meisterschaft', 'season' => 2027, 'created' => '2027-01-10 10:00:00',
        ]);
        $label = "Demo Spieler \u{1F3BE}";
        DB::table('bs_tournament_players')->insert(['tid' => $tid, 'label' => $label, 'itn_rated' => '10.0']);

        $this->runModuleMigrations();

        $this->assertSame(1, DB::table('bs_tournaments')->count());
        $this->assertSame($label, DB::table('bs_tournament_players')->value('label'), '4-byte label intact');
        $this->assertSame('setup', DB::table('bs_tournaments')->value('status'));

        $this->assertContains('tid_uid', $this->indexNames('bs_tournament_players'));
        $this->assertContains('partner_tpid', $this->indexNames('bs_tournament_players'));
        $this->assertContains('tid_mkey', $this->indexNames('bs_tournament_matches'));
        $this->assertContains('tid_phase_rnd', $this->indexNames('bs_tournament_matches'));
        $this->assertContains('tid_phase_line', $this->indexNames('bs_tournament_lines'));
        $this->assertContains('active', $this->indexNames('bs_tournaments'));
        $this->assertNotContains('bs_tournament_matches_tid_mkey_unique', $this->indexNames('bs_tournament_matches'), 'nothing recreated');

        // Running them again changes nothing.
        $this->runModuleMigrations();
        $this->assertSame(1, DB::table('bs_tournament_players')->count());
    }

    #[Test]
    public function live_shape_refuses_a_duplicate_match_key(): void
    {
        $this->loadLiveShape();
        $this->runModuleMigrations();

        DB::table('bs_tournament_matches')->insert(['tid' => 1, 'phase' => 'AC', 'mkey' => 'A-0', 'grp' => 'A']);

        try {
            DB::table('bs_tournament_matches')->insert(['tid' => 1, 'phase' => 'AC', 'mkey' => 'A-0', 'grp' => 'A']);
            $this->fail('tid_mkey must be unique on the live shape');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
            $this->assertStringContainsString('tid_mkey', $e->getMessage());
        }

        // A NULL key (old rows before step 02) may repeat.
        DB::table('bs_tournament_matches')->insert(['tid' => 1, 'phase' => 'AC', 'grp' => 'A']);
        DB::table('bs_tournament_matches')->insert(['tid' => 1, 'phase' => 'AC', 'grp' => 'A']);
        $this->assertSame(3, DB::table('bs_tournament_matches')->count());
    }

    #[Test]
    public function live_and_fresh_shape_have_the_same_columns(): void
    {
        $fresh = [];
        foreach (self::TABLES as $table) {
            $fresh[$table] = $this->columnShape($table);
        }

        $this->loadLiveShape();

        foreach (self::TABLES as $table) {
            $this->assertEqualsCanonicalizing($fresh[$table], $this->columnShape($table), $table);
        }
    }

    /** @return list<string> name, type (display width stripped) and nullability */
    private function columnShape(string $table): array
    {
        return array_map(
            fn (array $c): string => $c['name'].' '.preg_replace('/\b((?:tiny|small|medium|big)?int)\(\d+\)/', '$1', (string) $c['type']).($c['nullable'] ? ' null' : ''),
            Schema::getColumns($table),
        );
    }
}
