<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Services\ChallengeService;
use App\Modules\Pyramid\Services\ResultService;
use App\Modules\Pyramid\Support\PyramidRuleException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsPyramidLadders;
use Tests\MysqlTestCase;

/**
 * Adopts the live bs_pyramid_* shape (tests/Fixtures/pyramid/live-schema.sql, the old DDL without comments and
 * without the email column) and runs the Pyramid services on it.
 *
 * No wrapping transaction (DDL commits implicitly in MySQL): the database is migrated fresh once per process and
 * every test starts by dropping the six tables. The fresh shape is rebuilt afterwards for the other MySQL tests.
 */
#[Group('mysql')]
class PyramidMysqlAdoptionTest extends MysqlTestCase
{
    use BuildsPyramidLadders;

    private static bool $migrated = false;

    private const TABLES = [
        'bs_pyramid_events', 'bs_pyramid_challenges', 'bs_pyramid_grants', 'bs_pyramid_players',
        'bs_pyramid_ladders', 'bs_pyramid_schema_versions',
    ];

    private const MIGRATIONS = [
        '2026_11_06_090001_create_bs_pyramid_ladders_table.php',
        '2026_11_06_090002_create_bs_pyramid_players_table.php',
        '2026_11_06_090003_create_bs_pyramid_challenges_table.php',
        '2026_11_06_090004_create_bs_pyramid_events_table.php',
        '2026_11_06_090005_create_bs_pyramid_grants_table.php',
        '2026_11_06_090006_create_bs_pyramid_schema_versions_table.php',
        '2026_11_06_090007_add_email_to_bs_pyramid_players_table.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        $this->dropTables();
    }

    protected function tearDown(): void
    {
        if (self::$migrated) {
            $this->dropTables();
            $this->runMigrations();
        }

        parent::tearDown();
    }

    private function dropTables(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function runMigrations(): void
    {
        foreach (self::MIGRATIONS as $file) {
            $migration = require base_path('app/Modules/Pyramid/Database/Migrations/'.$file);
            $migration->up();
        }
    }

    private function loadLiveSchema(): void
    {
        $this->dropTables();

        $sql = (string) file_get_contents(base_path('tests/Fixtures/pyramid/live-schema.sql'));

        foreach (explode(';', $sql) as $statement) {
            $code = trim((string) preg_replace('/^\s*--.*$/m', '', $statement));

            if ($code !== '') {
                DB::unprepared($code);
            }
        }
    }

    #[Test]
    public function guarded_migrations_adopt_the_live_shape(): void
    {
        $this->loadLiveSchema();

        $this->assertFalse(Schema::hasColumn('bs_pyramid_players', 'email'), 'the fixture is the pre-email live shape');

        $ladderId = DB::table('bs_pyramid_ladders')->insertGetId([
            'title' => 'Demo Rangliste', 'season' => 2026, 'status' => 'running', 'created' => '2026-08-03 10:00:00',
        ]);
        $label = "Test \u{1F3BE}";
        DB::table('bs_pyramid_players')->insert([
            'ladder_id' => $ladderId, 'label' => $label, 'itn_rated' => '10.0', 'position' => 1,
            'status' => 'active', 'joined' => '2026-08-03 10:00:00',
        ]);

        $this->runMigrations();

        $this->assertSame(1, DB::table('bs_pyramid_ladders')->count());
        $this->assertSame(1, DB::table('bs_pyramid_players')->count());
        $this->assertSame($label, DB::table('bs_pyramid_players')->value('label'), '4-byte label intact');

        $columns = array_map(
            fn (object $row): string => $row->COLUMN_NAME,
            DB::select(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
                ['bs_pyramid_players'],
            ),
        );
        $this->assertSame('email', $columns[array_search('phone', $columns, true) + 1], 'email sits after phone');

        $this->assertContains('ladder_position', array_column(Schema::getIndexes('bs_pyramid_players'), 'name'));

        $this->assertSame(
            [20260803, 20260806],
            DB::table('bs_pyramid_schema_versions')->orderBy('version')->pluck('version')->map(fn ($v): int => (int) $v)->all(),
        );

        // Running them again changes nothing.
        $this->runMigrations();
        $this->assertSame(2, DB::table('bs_pyramid_schema_versions')->count());
    }

    #[Test]
    public function null_then_set_works_under_the_live_unique_index(): void
    {
        $this->loadLiveSchema();
        $this->runMigrations();

        $ladder = $this->makeLadder(10);
        $challenge = app(ChallengeService::class)->create($ladder, $this->playerAt($ladder, 8), $this->playerAt($ladder, 4), null);

        app(ResultService::class)->record($ladder, $challenge, ['s1a' => 6, 's1b' => 4, 's2a' => 6, 's2b' => 3], null);

        $positions = $this->positionsByLabel((int) $ladder->id);
        $this->assertSame(4, $positions['Spieler 8']);
        $this->assertSame(5, $positions['Spieler 4']);
        $this->assertSame(8, $positions['Spieler 7'], 'players between the two moved down by one');
        $this->assertRankingIntact((int) $ladder->id);
    }

    #[Test]
    public function integration_script_holds_on_mysql(): void
    {
        $this->loadLiveSchema();
        $this->runMigrations();

        $challenges = app(ChallengeService::class);
        $results = app(ResultService::class);
        $win = ['s1a' => 6, 's1b' => 4, 's2a' => 6, 's2b' => 3];

        // Admissibility
        $ladder = $this->makeLadder(10);
        $this->assertNull($challenges->check($ladder, $this->playerAt($ladder, 8), $this->playerAt($ladder, 5)));
        $this->assertNull($challenges->check($ladder, $this->playerAt($ladder, 8), $this->playerAt($ladder, 7)));
        $this->assertNotNull($challenges->check($ladder, $this->playerAt($ladder, 8), $this->playerAt($ladder, 2)));
        $this->assertNotNull($challenges->check($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 6)));

        // Rule 3.5 and the crossed challenge
        $first = $challenges->create($ladder, $this->playerAt($ladder, 8), $this->playerAt($ladder, 5), null);
        $this->assertNotNull($challenges->check($ladder, $this->playerAt($ladder, 8), $this->playerAt($ladder, 6)));
        $this->assertNotNull($challenges->check($ladder, $this->playerAt($ladder, 9), $this->playerAt($ladder, 5)));
        $this->assertNull($challenges->check($ladder, $this->playerAt($ladder, 9), $this->playerAt($ladder, 6)));
        $this->assertRuleRejected(fn () => $challenges->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 8), null));

        // Rule 4.4 protection and 4.3 cooldown, exclusive end
        DB::table('bs_pyramid_challenges')->where('id', $first->id)->update([
            'status' => 'played', 'winner_id' => $first->opponent_id, 'score' => '6:4 6:4',
            'resolved' => CarbonImmutable::now()->format('Y-m-d H:i:s'),
        ]);
        $winner = $this->playerAt($ladder, 5);
        $this->assertNotNull($challenges->check($ladder, $this->playerAt($ladder, 9), $winner));
        $this->assertNull($challenges->check($ladder, $this->playerAt($ladder, 9), $winner, CarbonImmutable::now()->addDays(4)));
        $this->assertNotNull($challenges->check($ladder, $this->playerAt($ladder, 8), $this->playerAt($ladder, 6)));
        $this->assertNull($challenges->check($ladder, $this->playerAt($ladder, 8), $this->playerAt($ladder, 6), CarbonImmutable::now()->addDays(8)));

        // Challenger win inserts (rule 4.2)
        $ladder = $this->makeLadder(6);
        $challenge = $challenges->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3), null);
        $results->record($ladder, $challenge, $win, null);
        $after = $this->positionsByLabel((int) $ladder->id);
        $this->assertSame([1, 2, 4, 5, 3, 6], array_values([$after['Spieler 1'], $after['Spieler 2'], $after['Spieler 3'], $after['Spieler 4'], $after['Spieler 5'], $after['Spieler 6']]));
        $this->assertRankingIntact((int) $ladder->id);
        $this->assertSame('6:4 6:3', PyramidChallenge::query()->findOrFail($challenge->id)->score);

        // A lost challenge changes nothing (rule 4.3)
        $ladder = $this->makeLadder(6);
        $before = $this->positionsByLabel((int) $ladder->id);
        $challenge = $challenges->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3), null);
        $results->record($ladder, $challenge, ['s1a' => 4, 's1b' => 6, 's2a' => 3, 's2b' => 6], null);
        $this->assertSame($before, $this->positionsByLabel((int) $ladder->id));

        // Entering player wins: everyone behind moves up one place; loses: ends last (rule 6.1)
        foreach ([[true, 3], [false, 6]] as [$entrantWins, $expected]) {
            $ladder = $this->makeLadder(5);
            $newcomer = PyramidPlayer::factory()->entering()->create(['ladder_id' => $ladder->id, 'label' => 'Neuling']);
            $challenge = $challenges->create($ladder, $newcomer, $this->playerAt($ladder, 3), null);
            $input = $entrantWins ? ['s1a' => 6, 's1b' => 2, 's2a' => 6, 's2b' => 2] : ['s1a' => 2, 's1b' => 6, 's2a' => 2, 's2b' => 6];
            $results->record($ladder, $challenge, $input, null);

            $this->assertSame($expected, $this->positionsByLabel((int) $ladder->id)['Neuling']);
            $this->assertRankingIntact((int) $ladder->id);
        }

        // An invalid score leaves everything as it was
        $ladder = $this->makeLadder(6);
        $challenge = $challenges->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3), null);
        $before = $this->positionsByLabel((int) $ladder->id);
        try {
            $results->record($ladder, $challenge, ['s1a' => 6, 's1b' => 4], null);
            $this->fail('an incomplete score must be rejected');
        } catch (\Throwable) {
            $this->assertSame($before, $this->positionsByLabel((int) $ladder->id));
            $this->assertSame('open', PyramidChallenge::query()->findOrFail($challenge->id)->status);
        }

        // Stale snapshot of an already booked challenge
        $results->record($ladder, $challenge, $win, null);
        $booked = $this->positionsByLabel((int) $ladder->id);
        $this->assertRuleRejected(fn () => $results->record($ladder, $challenge, $win, null));
        $this->assertSame($booked, $this->positionsByLabel((int) $ladder->id));

        // A second transition on a finished challenge
        $ladder = $this->makeLadder(6);
        $challenge = $challenges->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3), null);
        $challenges->transition((int) $challenge->id, ['status' => 'declined', 'decline_reason' => 'injury', 'resolved' => now()->format('Y-m-d H:i:s')], 'admin', ['message' => 'x'], null);
        $this->assertRuleRejected(fn () => $challenges->transition((int) $challenge->id, ['status' => 'withdrawn'], 'admin', ['message' => 'y'], null));
        $this->assertSame('declined', PyramidChallenge::query()->findOrFail($challenge->id)->status);

        // Forfeit: same shift as a played match, no invented score, winner set
        $ladder = $this->makeLadder(6);
        $challenge = $challenges->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3), null);
        $results->recordForfeit($ladder, $challenge, true, 'no show', null);
        $row = PyramidChallenge::query()->findOrFail($challenge->id);
        $this->assertSame('forfeited', $row->status);
        $this->assertNull($row->score);
        $this->assertNotNull($row->winner_id);
        $this->assertSame(3, $this->positionsByLabel((int) $ladder->id)['Spieler 5']);
        $this->assertRankingIntact((int) $ladder->id);
        $this->assertNotNull($challenges->check($ladder, $this->playerAt($ladder, 6), $this->playerAt($ladder, 3)), 'loser cooldown applies');

        // Paused opponent is not challengeable, his row keeps its position
        $ladder = $this->makeLadder(8);
        $paused = $this->playerAt($ladder, 5);
        DB::table('bs_pyramid_players')->where('id', $paused->id)->update(['status' => 'paused']);
        $this->assertNotNull($challenges->check($ladder, $this->playerAt($ladder, 8), $paused->fresh()));
        $this->assertNull($challenges->check($ladder, $this->playerAt($ladder, 8), $this->playerAt($ladder, 6)));
        $this->assertRankingIntact((int) $ladder->id);
    }

    private function assertRuleRejected(callable $action): void
    {
        try {
            $action();
            $this->fail('expected a PyramidRuleException');
        } catch (PyramidRuleException) {
            $this->addToAssertionCount(1);
        }
    }
}
