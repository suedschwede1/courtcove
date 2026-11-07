<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Scheduling\ScheduleDefinition;
use App\Console\Scheduling\TaskRunner;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Jetzt ausführen" may raise the PHP time limit for the web request, but must never shorten an unlimited one
 * (CLI, test runs) and must restore the previous limit: a fixed set_time_limit(120) killed a plain
 * `php artisan test` run 120 seconds after the scheduler tests.
 */
class TaskRunnerTimeLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $original;

    protected function setUp(): void
    {
        parent::setUp();
        $this->original = (string) ini_get('max_execution_time');
    }

    protected function tearDown(): void
    {
        set_time_limit((int) $this->original);
        parent::tearDown();
    }

    #[Test]
    public function an_unlimited_time_limit_stays_unlimited(): void
    {
        set_time_limit(0);

        app(TaskRunner::class)->run(ScheduleDefinition::find('scheduler-heartbeat'), null);

        $this->assertSame('0', ini_get('max_execution_time'));
    }

    #[Test]
    public function a_short_limit_is_raised_for_the_run_and_restored_afterwards(): void
    {
        set_time_limit(30);
        $during = null;
        $event = ScheduleDefinition::find('scheduler-heartbeat');
        $this->assertNotNull($event);
        $event->before(function () use (&$during): void {
            $during = ini_get('max_execution_time');
        });

        app(TaskRunner::class)->run($event, null);

        $this->assertSame((string) TaskRunner::TIME_LIMIT, $during);
        $this->assertSame('30', ini_get('max_execution_time'));
    }

    #[Test]
    public function a_longer_limit_is_left_alone(): void
    {
        set_time_limit(600);

        app(TaskRunner::class)->run(ScheduleDefinition::find('scheduler-heartbeat'), null);

        $this->assertSame('600', ini_get('max_execution_time'));
    }
}
