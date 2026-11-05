<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Modules\Draw\Models\Draw;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;

/**
 * Runs the Draw adoption and preflight against a real MySQL server.
 *
 * No wrapping transaction (DDL commits implicitly in MySQL and these tests rewrite
 * the schema): the database is migrated fresh once per process and every test
 * starts by rebuilding the three Draw tables from their migrations.
 */
#[Group('mysql')]
class DrawMysqlAdoptionTest extends MysqlTestCase
{
    private static bool $migrated = false;

    private const MIGRATIONS = [
        '2026_11_05_090001_create_bs_draws_table.php',
        '2026_11_05_090002_create_bs_draw_tokens_table.php',
        '2026_11_05_090003_create_bs_draw_eligible_table.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        $this->dropDrawTables();
        $this->runDrawMigrations();
    }

    private function dropDrawTables(): void
    {
        foreach (['bs_draw_eligible', 'bs_draw_tokens', 'bs_draws'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function runDrawMigrations(): void
    {
        foreach (self::MIGRATIONS as $file) {
            $migration = require base_path('app/Modules/Draw/Database/Migrations/'.$file);
            $migration->up();
        }
    }

    private function loadLiveSchema(): void
    {
        $this->dropDrawTables();

        $sql = (string) file_get_contents(base_path('tests/Fixtures/draw/live-schema.sql'));

        foreach (explode(';', $sql) as $statement) {
            // A chunk consisting only of comments and whitespace is not a statement.
            $code = trim((string) preg_replace('/^\s*--.*$/m', '', $statement));

            if ($code !== '') {
                DB::unprepared($code);
            }
        }
    }

    /**
     * @return array{int, string}
     */
    private function preflight(): array
    {
        $code = Artisan::call('phase2:preflight', ['module' => 'draw']);

        return [$code, Artisan::output()];
    }

    #[Test]
    public function guarded_migrations_adopt_the_live_shape(): void
    {
        $this->loadLiveSchema();

        DB::table('bs_draws')->insert([
            'slug' => 'live-shape',
            'title' => 'Live shape',
            'status' => 'committed',
            'mode' => 'direct',
            'payload' => json_encode(['rule' => "Platz \u{1F3BE}"], JSON_UNESCAPED_UNICODE),
            'commitment' => str_repeat('a', 64),
            'beacon_source' => 'drand:round:1',
            'created_uid' => 1,
            'created' => '2026-01-01 10:00:00',
        ]);

        $this->runDrawMigrations();

        $this->assertSame(1, DB::table('bs_draws')->count());
        $this->assertStringContainsString("\u{1F3BE}", (string) DB::table('bs_draws')->value('payload'));

        $draws = array_column(Schema::getIndexes('bs_draws'), 'name');
        $tokens = array_column(Schema::getIndexes('bs_draw_tokens'), 'name');

        $this->assertContains('slug', $draws);
        $this->assertContains('did_token', $tokens);
    }

    #[Test]
    public function preflight_passes_on_the_live_shape(): void
    {
        $this->loadLiveSchema();
        Draw::factory()->create();

        [$code, $output] = $this->preflight();

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('[PASS] charset:bs_draws', $output);
    }

    #[Test]
    public function preflight_spec_matches_fresh_migrations(): void
    {
        [, $output] = $this->preflight();

        $this->assertStringNotContainsString('[FAIL]', $output);
        $this->assertStringNotContainsString('[WARN] columns', $output);
        $this->assertStringContainsString('[PASS] charset:bs_draw_tokens', $output);
    }

    #[Test]
    public function utf8mb3_table_fails_charset(): void
    {
        DB::statement('ALTER TABLE bs_draw_eligible CONVERT TO CHARACTER SET utf8mb3');

        [$code, $output] = $this->preflight();

        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('[FAIL] charset:bs_draw_eligible', $output);
        $this->assertStringContainsString('[PASS] charset:bs_draws', $output);
    }

    #[Test]
    public function enum_drift_fails(): void
    {
        DB::statement("ALTER TABLE bs_draws MODIFY status ENUM('committed','picking','executed') NOT NULL DEFAULT 'committed'");

        [$code, $output] = $this->preflight();

        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('[FAIL] columns:bs_draws', $output);
    }
}
