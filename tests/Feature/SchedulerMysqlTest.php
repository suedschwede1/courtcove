<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Scheduling\Heartbeat;
use App\Models\ScheduledTaskRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;

/** The run log and the heartbeat on real MySQL (utf8mb4 excerpt, strict mode, failed_jobs table). */
#[Group('mysql')]
class SchedulerMysqlTest extends MysqlTestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        DB::table('scheduled_task_runs')->delete();
    }

    #[Test]
    public function the_run_log_is_utf8mb4_and_takes_a_full_excerpt(): void
    {
        $collation = DB::selectOne("SELECT TABLE_COLLATION AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheduled_task_runs'");
        $this->assertStringStartsWith('utf8mb4', (string) $collation->c);

        $excerpt = ScheduledTaskRun::excerpt(str_repeat('Ä🎾', 1500));
        $run = ScheduledTaskRun::query()->create([
            'name' => str_repeat('n', 100), 'command' => str_repeat('c', 255), 'started_at' => now(), 'finished_at' => now(),
            'duration_ms' => 123, 'status' => 'failed', 'exit_code' => 1, 'output_excerpt' => $excerpt,
            'triggered_by' => 'manual', 'user_id' => 42,
        ]);

        $this->assertSame($excerpt, $run->fresh()->output_excerpt);
        $this->assertSame(2000, mb_strlen((string) $run->fresh()->output_excerpt));
    }

    #[Test]
    public function the_heartbeat_updates_one_row_even_when_the_value_is_unchanged(): void
    {
        // MySQL reports 0 affected rows for an identical UPDATE: the heartbeat must not insert a second row then.
        $this->travelTo('2026-11-07 10:00:00');
        app(Heartbeat::class)->beat();
        app(Heartbeat::class)->beat();

        $this->assertSame(1, DB::table('bs_options')->where('key', Heartbeat::OPTION)->count());
    }

    #[Test]
    public function the_failed_jobs_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('failed_jobs'));
        $this->assertTrue(Schema::hasTable('jobs'));
    }
}
