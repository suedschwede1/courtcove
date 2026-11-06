<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SecondConnection;

/**
 * Shared setup of the Tournament/Draw MySQL tests (Mixed pattern): no wrapping transaction, because the
 * second session must see committed rows and DDL commits implicitly. The database is migrated fresh once
 * per process; every test then drops the seven Tournament and Draw tables and rebuilds them from the
 * guarded migrations (fresh shape), so a test may change the schema (live fixture, narrowed column) and
 * the next one still starts clean. Only runs after MysqlTestCase::setUp() has verified the local
 * booking_test database.
 */
trait RebuildsTournamentMysqlTables
{
    private static bool $migratedOnce = false;

    /** True once setUp passed the MySQL guard (tearDown must never touch anything otherwise). */
    private bool $tablesReady = false;

    /** @var list<string> children before parents */
    private static array $moduleTables = [
        'bs_draw_eligible', 'bs_draw_tokens', 'bs_draws',
        'bs_tournament_lines', 'bs_tournament_matches', 'bs_tournament_players', 'bs_tournaments',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migratedOnce) {
            $this->artisan('migrate:fresh');
            self::$migratedOnce = true;
        }

        $this->rebuildModuleTables();
        $this->tablesReady = true;
    }

    protected function tearDown(): void
    {
        if ($this->tablesReady) {
            SecondConnection::release();
            $this->rebuildModuleTables();
        }

        parent::tearDown();
    }

    protected function dropModuleTables(): void
    {
        foreach (self::$moduleTables as $table) {
            Schema::dropIfExists($table);
        }
    }

    protected function rebuildModuleTables(): void
    {
        $this->dropModuleTables();
        $this->runModuleMigrations();
    }

    protected function runModuleMigrations(): void
    {
        foreach (['app/Modules/Draw/Database/Migrations', 'app/Modules/Tournament/Database/Migrations'] as $dir) {
            $files = glob(base_path($dir.'/*.php')) ?: [];
            sort($files);

            foreach ($files as $file) {
                $migration = require $file;
                $migration->up();
            }
        }
    }

    /** Runs a DDL fixture statement by statement (comment-only chunks are skipped). */
    protected function loadSqlFixture(string $path): void
    {
        $sql = (string) file_get_contents(base_path($path));

        foreach (explode(';', $sql) as $statement) {
            $code = trim((string) preg_replace('/^\s*--.*$/m', '', $statement));

            if ($code !== '') {
                DB::unprepared($code);
            }
        }
    }
}
