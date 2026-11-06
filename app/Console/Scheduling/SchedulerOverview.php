<?php

declare(strict_types=1);

namespace App\Console\Scheduling;

use App\Models\ScheduledTaskRun;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

/** Data of the admin page "Geplante Aufgaben" (tasks, health, queue, last runs). */
final class SchedulerOverview
{
    public function __construct(
        private readonly Heartbeat $heartbeat,
        private readonly ScheduleDescriber $describer,
        private readonly TaskRunner $runner,
    ) {}

    /** @return list<array<string, mixed>> */
    public function tasks(): array
    {
        $since = CarbonImmutable::now()->subDays(7);
        $rows = [];

        foreach (ScheduleDefinition::events() as $event) {
            $name = (string) $event->description;
            $last = ScheduledTaskRun::query()->where('name', $name)->latest('id')->first();

            $rows[] = [
                'name' => $name,
                'title' => $this->text($name, 'title', $name),
                'description' => $this->text($name, 'description', ''),
                'command' => $event instanceof CallbackEvent ? null : RunLog::displayCommand((string) $event->command),
                'schedule' => $this->describer->describe($event->getExpression()),
                'expression' => $event->getExpression(),
                // Pass "now" explicitly: the cron library reads the real clock for the string 'now'.
                'next_run' => CarbonImmutable::instance($event->nextRunDate(CarbonImmutable::now()))->setTimezone((string) config('app.timezone')),
                'last_run' => $last,
                'heartbeat' => $name === ScheduleDefinition::HEARTBEAT ? $this->heartbeat->lastTick() : null,
                'failures_7d' => ScheduledTaskRun::query()->where('name', $name)->where('status', 'failed')->where('started_at', '>=', $since)->count(),
                'running' => $this->runner->isRunning($event),
                'runnable' => $this->isRunnable($event),
            ];
        }

        return $rows;
    }

    /** Named callbacks of our own definition and artisan commands can be run safely; anything else not. */
    public function isRunnable(Event $event): bool
    {
        return is_string($event->description) && $event->description !== ''
            && ($event instanceof CallbackEvent || $event->command !== null);
    }

    /** @return array{status: string, last: ?CarbonImmutable, cron: string, php: string} */
    public function health(): array
    {
        $php = (string) config('booking.scheduler.php_binary', 'php');

        return [
            'status' => $this->heartbeat->status(),
            'last' => $this->heartbeat->lastTick(),
            'cron' => '* * * * * cd '.base_path().' && '.$php.' artisan schedule:run >> /dev/null 2>&1',
            'php' => $php,
        ];
    }

    /** @return array{enabled: bool, connection: string, waiting: ?int, failed: int, failed_jobs: Collection<int, \stdClass>} */
    public function queue(): array
    {
        $connection = (string) config('queue.default', 'sync');
        if (! ScheduleDefinition::usesQueue()) {
            return ['enabled' => false, 'connection' => $connection, 'waiting' => null, 'failed' => 0, 'failed_jobs' => collect()];
        }

        $waiting = null;
        if (config("queue.connections.{$connection}.driver") === 'database') {
            $waiting = (int) DB::table((string) config("queue.connections.{$connection}.table", 'jobs'))->count();
        }

        $failedTable = (string) config('queue.failed.table', 'failed_jobs');
        $failed = DB::table($failedTable);

        return [
            'enabled' => true,
            'connection' => $connection,
            'waiting' => $waiting,
            'failed' => (int) (clone $failed)->count(),
            'failed_jobs' => (clone $failed)->orderByDesc('failed_at')->limit(10)->get(['uuid', 'queue', 'failed_at', 'exception']),
        ];
    }

    /**
     * @param  array{task?: string|null, status?: string|null}  $filters
     * @return Collection<int, ScheduledTaskRun>
     */
    public function recentRuns(array $filters): Collection
    {
        return ScheduledTaskRun::query()
            ->when(($filters['task'] ?? '') !== '', fn ($q) => $q->where('name', $filters['task']))
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', $filters['status']))
            ->latest('id')
            ->limit(50)
            ->get();
    }

    private function text(string $name, string $part, string $fallback): string
    {
        $key = 'booking.admin.scheduler.tasks.'.$name.'.'.$part;

        return Lang::has($key) ? (string) __($key) : $fallback;
    }
}
