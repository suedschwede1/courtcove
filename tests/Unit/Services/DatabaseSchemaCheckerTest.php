<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DatabaseSchemaChecker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseSchemaCheckerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private DatabaseSchemaChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new DatabaseSchemaChecker;
    }

    #[Test]
    public function knows_table_only_accepts_manifest_tables(): void
    {
        $this->assertTrue($this->checker->knowsTable('bs_users'));
        $this->assertTrue($this->checker->knowsTable('members'));
        $this->assertFalse($this->checker->knowsTable('migrations'));
        $this->assertFalse($this->checker->knowsTable('bs_users; DROP TABLE x'));
    }

    #[Test]
    public function migration_status_lists_all_migration_files(): void
    {
        $status = $this->checker->migrationStatus();

        $names = array_column($status, 'name');
        $this->assertContains('2026_06_26_073602_create_bs_users_table', $names);
        $this->assertContains('2026_06_28_155630_create_jobs_table', $names);
    }

    #[Test]
    public function migration_status_marks_already_run_migrations_as_ran(): void
    {
        // LazilyRefreshDatabase has already migrated everything in the test database.
        $status = $this->checker->migrationStatus();

        $usersMigration = collect($status)->firstWhere('name', '2026_06_26_073602_create_bs_users_table');

        $this->assertNotNull($usersMigration);
        $this->assertTrue($usersMigration['ran']);
    }

    #[Test]
    public function migration_status_marks_a_removed_migration_record_as_pending(): void
    {
        DB::table('migrations')->where('migration', '2026_06_28_155630_create_jobs_table')->delete();

        $status = $this->checker->migrationStatus();
        $jobsMigration = collect($status)->firstWhere('name', '2026_06_28_155630_create_jobs_table');

        $this->assertNotNull($jobsMigration);
        $this->assertFalse($jobsMigration['ran']);
        $this->assertTrue($this->checker->hasPendingMigrations());
    }

    #[Test]
    public function has_pending_migrations_is_false_when_everything_ran(): void
    {
        $this->assertFalse($this->checker->hasPendingMigrations());
    }

    #[Test]
    public function check_tables_reports_no_missing_columns_after_a_normal_migration(): void
    {
        foreach ($this->checker->checkTables() as $table) {
            $this->assertTrue($table['exists'], "{$table['table']} should exist");
            $this->assertSame([], $table['missing_columns'], "{$table['table']} should have no missing columns");
        }
    }

    #[Test]
    public function check_tables_flags_a_column_that_was_dropped(): void
    {
        Schema::table('bs_users', function (Blueprint $table): void {
            $table->dropColumn('last_ip');
        });

        $result = collect($this->checker->checkTables())->firstWhere('table', 'bs_users');

        $this->assertTrue($result['exists']);
        $this->assertSame(['last_ip'], $result['missing_columns']);
    }

    #[Test]
    public function check_tables_flags_a_table_that_does_not_exist(): void
    {
        Schema::drop('bs_events_meta');

        $result = collect($this->checker->checkTables())->firstWhere('table', 'bs_events_meta');

        $this->assertFalse($result['exists']);
        $this->assertSame(['emid', 'eid', 'key', 'value', 'locale'], $result['missing_columns']);
    }

    #[Test]
    public function repair_table_adds_missing_columns(): void
    {
        Schema::table('bs_users', function (Blueprint $table): void {
            $table->dropColumn('last_ip');
        });

        $actions = $this->checker->repairTable('bs_users');

        $this->assertCount(1, $actions);
        $this->assertStringContainsString('last_ip', $actions[0]);
        $this->assertTrue(Schema::hasColumn('bs_users', 'last_ip'));

        $result = collect($this->checker->checkTables())->firstWhere('table', 'bs_users');
        $this->assertSame([], $result['missing_columns']);
    }

    #[Test]
    public function repair_table_recreates_a_missing_table_via_its_migration(): void
    {
        Schema::drop('bs_events_meta');

        $actions = $this->checker->repairTable('bs_events_meta');

        $this->assertCount(1, $actions);
        $this->assertStringContainsString('2026_06_26_073904_create_bs_events_meta_table', $actions[0]);
        $this->assertTrue(Schema::hasTable('bs_events_meta'));
        $this->assertDatabaseHas('migrations', ['migration' => '2026_06_26_073904_create_bs_events_meta_table']);
    }

    #[Test]
    public function repair_table_restores_a_lost_migration_record(): void
    {
        Schema::drop('bs_events_meta');
        DB::table('migrations')->where('migration', '2026_06_26_073904_create_bs_events_meta_table')->delete();

        $this->checker->repairTable('bs_events_meta');

        $this->assertTrue(Schema::hasTable('bs_events_meta'));
        $this->assertDatabaseHas('migrations', ['migration' => '2026_06_26_073904_create_bs_events_meta_table']);
    }

    #[Test]
    public function repair_table_is_a_noop_for_a_healthy_table(): void
    {
        $this->assertSame([], $this->checker->repairTable('bs_users'));
    }

    #[Test]
    public function repair_table_throws_for_an_unknown_table(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->checker->repairTable('migrations');
    }

    #[Test]
    public function drift_report_is_empty_for_a_healthy_schema(): void
    {
        $this->assertSame([], $this->checker->driftReport());
    }

    #[Test]
    public function drift_report_flags_an_unexpected_extra_column(): void
    {
        Schema::table('bs_users', function (Blueprint $table): void {
            $table->string('legacy_note', 32)->nullable();
        });

        $finding = collect($this->checker->driftReport())
            ->firstWhere('kind', 'extra_column');

        $this->assertNotNull($finding);
        $this->assertSame('bs_users', $finding['table']);
        $this->assertSame('legacy_note', $finding['column']);
    }

    #[Test]
    public function drift_report_does_not_leak_probe_tables(): void
    {
        $this->checker->driftReport();

        $this->assertFalse(Schema::hasTable('__drift_probe__bs_users'));
    }

    #[Test]
    public function drift_report_does_not_double_report_a_missing_table(): void
    {
        Schema::drop('bs_events_meta');

        $findings = collect($this->checker->driftReport())->where('table', 'bs_events_meta');

        $this->assertCount(0, $findings);
    }

    #[Test]
    public function drift_report_flags_a_column_with_the_wrong_type(): void
    {
        Schema::table('bs_users', function (Blueprint $table): void {
            $table->dropIndex(['alias']);
        });
        Schema::table('bs_users', function (Blueprint $table): void {
            $table->dropColumn('alias');
        });
        Schema::table('bs_users', function (Blueprint $table): void {
            $table->string('alias', 32)->nullable();
        });

        $finding = collect($this->checker->driftReport())
            ->firstWhere('kind', 'type_mismatch');

        $this->assertNotNull($finding);
        $this->assertSame('bs_users', $finding['table']);
        $this->assertSame('alias', $finding['column']);
        $this->assertArrayHasKey('expected', $finding);
        $this->assertArrayHasKey('actual', $finding);
    }

    #[Test]
    public function drift_report_flags_a_missing_single_column_index(): void
    {
        Schema::table('bs_users_meta', function (Blueprint $table): void {
            $table->dropIndex(['key']);
        });

        $finding = collect($this->checker->driftReport())
            ->firstWhere('kind', 'missing_index');

        $this->assertNotNull($finding);
        $this->assertSame('bs_users_meta', $finding['table']);
        $this->assertSame('key|index', $finding['signature']);
    }

    #[Test]
    public function drift_report_flags_an_unexpected_extra_index(): void
    {
        Schema::table('bs_users_meta', function (Blueprint $table): void {
            $table->index('value', 'bs_users_meta_value_test_index');
        });

        $finding = collect($this->checker->driftReport())
            ->firstWhere('kind', 'extra_index');

        $this->assertNotNull($finding);
        $this->assertSame('bs_users_meta', $finding['table']);
        $this->assertSame('value|index', $finding['signature']);
    }

    /**
     * The actual linter check: every real table in the database must be
     * known to the manifest. Fails the moment a new migration creates a
     * table (app or module) without a matching entry being added to
     * DatabaseSchemaChecker::columnDefinitions() — the exact drift that let
     * the admin/database page silently miss several Members-module tables.
     */
    #[Test]
    public function every_table_in_the_database_is_tracked_by_the_manifest(): void
    {
        $untracked = $this->checker->untrackedTables();

        $this->assertSame([], $untracked, 'Untracked tables: '.implode(', ', $untracked)
            .' — add them to DatabaseSchemaChecker::columnDefinitions() (or frameworkTables() if Laravel-owned).');
    }

    #[Test]
    public function untracked_tables_ignores_known_framework_tables(): void
    {
        $this->assertNotContains('migrations', $this->checker->untrackedTables());
        $this->assertNotContains('jobs', $this->checker->untrackedTables());
    }

    #[Test]
    public function untracked_tables_flags_a_table_created_outside_the_manifest(): void
    {
        Schema::create('some_new_module_table', function (Blueprint $table): void {
            $table->id();
        });

        $this->assertContains('some_new_module_table', $this->checker->untrackedTables());

        Schema::drop('some_new_module_table');
    }

    #[Test]
    public function drift_report_flags_a_missing_composite_unique_index(): void
    {
        Schema::table('member_dues', function (Blueprint $table): void {
            $table->dropUnique(['member_id', 'fiscal_year_id']);
        });

        $finding = collect($this->checker->driftReport())
            ->firstWhere('kind', 'missing_index');

        $this->assertNotNull($finding);
        $this->assertSame('member_dues', $finding['table']);
        $this->assertSame('fiscal_year_id,member_id|unique', $finding['signature']);
    }
}
