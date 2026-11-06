<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Console\Scheduling\Heartbeat;
use App\Console\Scheduling\ScheduleDefinition;
use App\Models\Option;
use App\Models\ScheduledTaskRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Admin page "Geplante Aufgaben" (admin/scheduler). */
class SchedulerAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function the_page_lists_all_tasks_with_plain_schedule_and_next_run(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-07 10:00:30', 'Europe/Vienna'));

        $response = $this->actingAs($this->admin())->get('/admin/scheduler')->assertOk();

        foreach (ScheduleDefinition::events() as $event) {
            $response->assertSee('data-task="'.$event->description.'"', false);
        }
        $response->assertSee(__('booking.admin.scheduler.tasks.scheduler-prune-runs.title'))
            ->assertSee('täglich 03:10')
            ->assertSee('jede Minute')
            ->assertSee('sanctum:prune-expired --hours=24')
            ->assertSee(__('booking.admin.scheduler.queue.sync'))
            ->assertSee('data-queue="sync"', false);
    }

    #[Test]
    public function the_sidebar_has_the_entry_for_config_admins_only(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('admin.scheduler.index').'"', $html);

        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.see-menu', 'admin.booking']);
        $html = $this->actingAs($assist)->get('/admin')->assertOk()->getContent();
        $this->assertStringNotContainsString('href="'.route('admin.scheduler.index').'"', $html);
        $this->actingAs($assist)->get('/admin/scheduler')->assertForbidden();
    }

    #[Test]
    public function without_admin_mode_the_page_shows_the_admin_mode_notice(): void
    {
        $this->actingAsWithoutAdminMode($this->admin())->get('/admin/scheduler')
            ->assertForbidden()->assertSee('data-admin-mode-required', false);
        $this->actingAsWithoutAdminMode($this->admin())->post('/admin/scheduler/scheduler-heartbeat/run')
            ->assertForbidden();
        $this->assertSame(0, ScheduledTaskRun::query()->count());
    }

    #[Test]
    public function the_health_card_shows_green_yellow_and_red_with_the_cron_line(): void
    {
        config(['booking.scheduler.php_binary' => '/usr/local/bin/php84']);
        $this->travelTo(CarbonImmutable::parse('2026-11-07 10:00:00', 'Europe/Vienna'));
        $admin = $this->admin();

        $red = $this->actingAs($admin)->get('/admin/scheduler')->assertOk();
        $red->assertSee('data-health="red"', false)->assertSee('data-cron-warning', false)
            ->assertSee('* * * * * cd '.base_path().' &amp;&amp; /usr/local/bin/php84 artisan schedule:run', false)
            ->assertSee(__('booking.admin.scheduler.health.never'));

        $this->travelTo(CarbonImmutable::parse('2026-11-07 09:58:00', 'Europe/Vienna'));
        app(Heartbeat::class)->beat();
        $this->travelTo(CarbonImmutable::parse('2026-11-07 10:00:00', 'Europe/Vienna'));
        $this->actingAs($admin)->get('/admin/scheduler')->assertSee('data-health="yellow"', false)->assertDontSee('data-cron-warning', false);

        app(Heartbeat::class)->beat();
        $this->actingAs($admin)->get('/admin/scheduler')->assertSee('data-health="green"', false)->assertSee(__('booking.admin.scheduler.health.green'));
    }

    // --- run now -----------------------------------------------------------------------------------------

    #[Test]
    public function run_now_runs_the_task_and_logs_it_as_manual(): void
    {
        $admin = $this->admin();
        ScheduledTaskRun::query()->create(['name' => 'old', 'started_at' => now()->subDays(30), 'status' => 'ok', 'triggered_by' => 'scheduler']);

        $this->actingAs($admin)->post('/admin/scheduler/scheduler-prune-runs/run')
            ->assertRedirect(route('admin.scheduler.index'))
            ->assertSessionHas('success');

        $run = ScheduledTaskRun::query()->latest('id')->firstOrFail();
        $this->assertSame(['scheduler-prune-runs', 'ok', 'manual', $admin->uid], [$run->name, $run->status, $run->triggered_by, $run->user_id]);
        $this->assertSame(0, ScheduledTaskRun::query()->where('name', 'old')->count(), 'the task really ran');
    }

    #[Test]
    public function run_now_of_the_heartbeat_is_logged_and_beats(): void
    {
        $this->actingAs($this->admin())->post('/admin/scheduler/scheduler-heartbeat/run')->assertSessionHas('success');

        $this->assertNotNull(app(Heartbeat::class)->lastTick());
        $this->assertSame('manual', ScheduledTaskRun::query()->where('name', 'scheduler-heartbeat')->value('triggered_by'));
    }

    #[Test]
    public function only_tasks_of_the_schedule_can_be_run(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/scheduler/users:split-role/run')->assertNotFound();
        $this->actingAs($admin)->post('/admin/scheduler/'.rawurlencode('inspire; rm -rf /').'/run')->assertNotFound();
        $this->actingAs($admin)->get('/admin/scheduler/scheduler-heartbeat/run')->assertStatus(405);
        $this->assertSame(0, ScheduledTaskRun::query()->count());
    }

    #[Test]
    public function members_cannot_run_tasks(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))->post('/admin/scheduler/scheduler-heartbeat/run')->assertForbidden();
        $this->assertSame(0, ScheduledTaskRun::query()->count());
    }

    #[Test]
    public function a_running_task_is_not_started_again(): void
    {
        $event = ScheduleDefinition::find('scheduler-prune-runs');
        $this->assertNotNull($event);
        $event->mutex->create($event);

        try {
            $this->actingAs($this->admin())->post('/admin/scheduler/scheduler-prune-runs/run')->assertSessionHas('error');
        } finally {
            $event->mutex->forget($event);
        }

        $run = ScheduledTaskRun::query()->latest('id')->firstOrFail();
        $this->assertSame(['skipped', 'manual'], [$run->status, $run->triggered_by]);
    }

    #[Test]
    public function run_now_is_rate_limited_to_five_per_minute(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($admin)->post('/admin/scheduler/scheduler-heartbeat/run')->assertRedirect();
        }

        $this->actingAs($admin)->post('/admin/scheduler/scheduler-heartbeat/run')->assertStatus(429);
    }

    #[Test]
    public function the_recent_runs_can_be_filtered_by_task_and_status(): void
    {
        foreach ([['scheduler-prune-runs', 'ok'], ['scheduler-prune-runs', 'failed'], ['auth-clear-resets', 'ok']] as [$name, $status]) {
            ScheduledTaskRun::query()->create(['name' => $name, 'started_at' => now(), 'status' => $status, 'triggered_by' => 'scheduler']);
        }
        $admin = $this->admin();

        $html = $this->actingAs($admin)->get('/admin/scheduler?task=scheduler-prune-runs&status=failed')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-run-status='));
        $this->assertStringContainsString('data-run-status="failed"', $html);

        $this->actingAs($admin)->get('/admin/scheduler?status=bogus&task[]=x')->assertOk();
    }

    // --- queue -------------------------------------------------------------------------------------------

    private function failedJob(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => json_encode(['displayName' => 'Demo', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => []]),
            'exception' => "RuntimeException: Demo failure\n#0 trace", 'failed_at' => now(),
        ]);
    }

    #[Test]
    public function the_queue_card_shows_waiting_and_failed_jobs_for_a_database_queue(): void
    {
        config(['queue.default' => 'database']);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
        $this->failedJob();

        $this->actingAs($this->admin())->get('/admin/scheduler')->assertOk()
            ->assertSee('data-queue="on"', false)
            ->assertSee('RuntimeException: Demo failure')
            ->assertSee(__('booking.admin.scheduler.queue.retry'))
            ->assertSee('data-task="queue-work"', false);
    }

    #[Test]
    public function failed_jobs_can_be_retried_and_flushed_only_with_a_queue(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/scheduler/queue/retry')->assertNotFound();

        config(['queue.default' => 'database']);
        $this->failedJob();
        $this->actingAs($admin)->post('/admin/scheduler/queue/retry')->assertSessionHas('success');
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, DB::table('jobs')->count(), 'back on the queue');

        $this->failedJob();
        $this->actingAs($admin)->post('/admin/scheduler/queue/flush')->assertSessionHas('success');
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    #[Test]
    public function the_heartbeat_option_is_one_row(): void
    {
        app(Heartbeat::class)->beat();
        app(Heartbeat::class)->beat();

        $this->assertSame(1, Option::query()->where('key', Heartbeat::OPTION)->count());
    }
}
