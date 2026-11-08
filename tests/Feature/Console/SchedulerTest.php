<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Scheduling\Heartbeat;
use App\Console\Scheduling\RunLog;
use App\Console\Scheduling\ScheduleDefinition;
use App\Console\Scheduling\ScheduleDescriber;
use App\Models\Option;
use App\Models\ScheduledTaskRun;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Scheduler base: definition, plain-text schedules, next run, run log, heartbeat, cleanup. */
class SchedulerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return list<string> */
    private function names(): array
    {
        return array_map(fn ($event) => (string) $event->description, ScheduleDefinition::events());
    }

    #[Test]
    public function the_schedule_has_the_housekeeping_tasks_with_names_and_no_overlapping(): void
    {
        $this->assertSame(['scheduler-heartbeat', 'scheduler-prune-runs', 'sanctum-prune-expired', 'auth-clear-resets', 'player-exchange-expire', 'notifications-dispatch', 'notifications-prune'], $this->names());

        foreach (ScheduleDefinition::events() as $event) {
            $this->assertTrue($event->withoutOverlapping, $event->description.' without overlapping');
        }

        $commands = implode(' ', array_map(fn ($event) => (string) $event->command, ScheduleDefinition::events()));
        foreach (['members:', 'users:split-role', 'booking:adopt-booking-rules', 'phase2:', 'import'] as $business) {
            $this->assertStringNotContainsString($business, $commands, 'business commands are never scheduled');
        }
    }

    #[Test]
    public function the_player_search_expiry_runs_every_five_minutes(): void
    {
        $expire = ScheduleDefinition::find('player-exchange-expire');

        $this->assertNotNull($expire);
        $this->assertStringContainsString('player-exchange:expire', (string) $expire->command);
        $this->assertSame('*/5 * * * *', $expire->getExpression());
    }

    #[Test]
    public function the_queue_worker_is_only_scheduled_when_a_queue_is_used(): void
    {
        $this->assertNotContains('queue-work', $this->names());

        config(['queue.default' => 'database']);
        $names = $this->names();
        $this->assertContains('queue-work', $names);
        $this->assertContains('queue-prune-failed', $names);
        $worker = ScheduleDefinition::find('queue-work');
        $this->assertStringContainsString('queue:work --stop-when-empty --max-time=50', (string) $worker?->command);
        $this->assertSame('* * * * *', $worker?->getExpression());
    }

    #[Test]
    public function schedule_run_uses_the_same_definition(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('scheduler-heartbeat')
            ->expectsOutputToContain('auth:clear-resets')
            ->assertSuccessful();
    }

    #[Test]
    public function every_task_has_a_title_and_description_in_both_languages(): void
    {
        config(['queue.default' => 'database']);
        foreach ($this->names() as $name) {
            foreach (['title', 'description'] as $part) {
                foreach (['de', 'en'] as $locale) {
                    $this->assertTrue(Lang::has('booking.admin.scheduler.tasks.'.$name.'.'.$part, $locale), $locale.' '.$name.' '.$part);
                }
            }
        }
    }

    /** @return array<string, array{string, string, string}> */
    public static function expressions(): array
    {
        return [
            'every minute' => ['* * * * *', 'jede Minute', 'every minute'],
            'every 5 minutes' => ['*/5 * * * *', 'alle 5 Minuten', 'every 5 minutes'],
            'hourly' => ['15 * * * *', 'stündlich zur Minute 15', 'hourly at minute 15'],
            'daily' => ['10 3 * * *', 'täglich 03:10', 'daily at 03:10'],
            'weekly' => ['0 6 * * 1', 'wöchentlich Montag 06:00', 'weekly on Monday at 06:00'],
            'monthly' => ['0 4 1 * *', 'monatlich am 1. um 04:00', 'monthly on day 1 at 04:00'],
            'other' => ['0 4 * 1 *', '0 4 * 1 *', '0 4 * 1 *'],
        ];
    }

    #[Test]
    #[DataProvider('expressions')]
    public function schedules_are_described_in_plain_words(string $expression, string $de, string $en): void
    {
        app()->setLocale('de');
        $this->assertSame($de, (new ScheduleDescriber)->describe($expression));
        app()->setLocale('en');
        $this->assertSame($en, (new ScheduleDescriber)->describe($expression));
    }

    #[Test]
    public function the_next_run_follows_the_cron_expression_in_the_app_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-07 10:00:30', 'Europe/Vienna'));

        $this->assertSame('2026-11-08 03:10', ScheduleDefinition::find('scheduler-prune-runs')?->nextRunDate(CarbonImmutable::now())->setTimezone('Europe/Vienna')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-07 10:01', ScheduleDefinition::find('scheduler-heartbeat')?->nextRunDate(CarbonImmutable::now())->setTimezone('Europe/Vienna')->format('Y-m-d H:i'));
    }

    // --- heartbeat --------------------------------------------------------------------------------------

    #[Test]
    public function schedule_run_records_the_heartbeat_but_does_not_log_it(): void
    {
        // Minute 1, not 0: other tasks (every five minutes) would fire at :00 and be logged.
        $this->travelTo(CarbonImmutable::parse('2026-11-07 10:01:05', 'Europe/Vienna'));

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame('2026-11-07 10:01:05', app(Heartbeat::class)->lastTick()?->format('Y-m-d H:i:s'));
        // The every-minute notifications dispatcher is logged like any command; only the heartbeat is not.
        $this->assertSame(0, ScheduledTaskRun::query()->where('name', ScheduleDefinition::HEARTBEAT)->count(), 'a successful heartbeat is not logged');
        $this->assertSame(1, Option::query()->where('key', Heartbeat::OPTION)->count());

        $this->travelTo(CarbonImmutable::parse('2026-11-07 10:02:05', 'Europe/Vienna'));
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame(1, Option::query()->where('key', Heartbeat::OPTION)->count(), 'one row, updated in place');
    }

    /** @return array<string, array{?int, string}> */
    public static function ages(): array
    {
        return ['never' => [null, 'red'], 'fresh' => [30, 'green'], 'edge green' => [90, 'green'], 'late' => [150, 'yellow'], 'edge yellow' => [180, 'yellow'], 'stuck' => [181, 'red']];
    }

    #[Test]
    #[DataProvider('ages')]
    public function the_traffic_light_follows_the_age_of_the_last_tick(?int $age, string $status): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-07 10:00:00', 'Europe/Vienna'));
        if ($age !== null) {
            $this->travelTo(CarbonImmutable::now()->subSeconds($age));
            app(Heartbeat::class)->beat();
            $this->travelTo(CarbonImmutable::parse('2026-11-07 10:00:00', 'Europe/Vienna'));
        }

        $this->assertSame($status, app(Heartbeat::class)->status());
    }

    // --- run log -----------------------------------------------------------------------------------------

    #[Test]
    public function the_listeners_log_finished_failed_and_skipped_runs(): void
    {
        $schedule = new Schedule('Europe/Vienna');
        $ok = $schedule->call(fn () => null)->name('demo-ok');
        $failing = $schedule->call(function (): void {
            throw new \RuntimeException('Boom with password=topsecret');
        })->name('demo-failing');

        event(new ScheduledTaskStarting($ok));
        $ok->run(app());
        event(new ScheduledTaskFinished($ok, 0.25));

        event(new ScheduledTaskStarting($failing));
        try {
            $failing->run(app());
        } catch (\RuntimeException $e) {
            event(new ScheduledTaskFailed($failing, $e));
        }

        event(new ScheduledTaskSkipped($ok));

        $runs = ScheduledTaskRun::query()->orderBy('id')->get();
        $this->assertSame(['ok', 'failed', 'skipped'], $runs->pluck('status')->all());
        $this->assertSame(['demo-ok', 'demo-failing', 'demo-ok'], $runs->pluck('name')->all());
        $this->assertSame(250, $runs[0]->duration_ms);
        $this->assertSame('scheduler', $runs[0]->triggered_by);
        $this->assertNull($runs[0]->user_id);
        $this->assertStringContainsString('Boom with password=[redacted]', (string) $runs[1]->output_excerpt);
        $this->assertStringNotContainsString('topsecret', (string) $runs[1]->output_excerpt);
    }

    #[Test]
    public function command_lines_are_logged_without_binary_and_path(): void
    {
        $this->assertSame('sanctum:prune-expired --hours=24', RunLog::displayCommand("'/usr/bin/php8.4' 'artisan' sanctum:prune-expired --hours=24"));
    }

    #[Test]
    public function the_output_excerpt_is_capped_at_2000_characters(): void
    {
        $excerpt = ScheduledTaskRun::excerpt(str_repeat('a', 5000).'END');

        $this->assertSame(2000, mb_strlen((string) $excerpt));
        $this->assertStringEndsWith('END', (string) $excerpt);
        $this->assertNull(ScheduledTaskRun::excerpt('   '));
    }

    #[Test]
    public function the_cleanup_deletes_runs_older_than_14_days(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:10:00', 'Europe/Vienna'));
        foreach (['2026-11-01 12:00:00' => 'old', '2026-11-06 03:09:00' => 'edge-old', '2026-11-06 03:11:00' => 'kept', '2026-11-19 12:00:00' => 'recent'] as $at => $name) {
            ScheduledTaskRun::query()->create(['name' => $name, 'started_at' => $at, 'status' => 'ok', 'triggered_by' => 'scheduler']);
        }

        $this->assertSame(2, ScheduledTaskRun::prune());
        $this->assertSame(['kept', 'recent'], ScheduledTaskRun::query()->orderBy('started_at')->pluck('name')->all());
    }
}
