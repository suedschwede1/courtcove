<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DatabaseSchemaChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseSchemaCheckerTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseSchemaChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new DatabaseSchemaChecker();
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
        // RefreshDatabase has already migrated everything in the test database.
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
}
