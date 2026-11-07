<?php

declare(strict_types=1);

namespace App\Console\Scheduling;

use App\Models\ScheduledTaskRun;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Throwable;

/**
 * "Jetzt ausführen": runs one task of the Schedule synchronously, exactly as the scheduler would (same Event
 * object, same withoutOverlapping mutex, same Starting/Finished/Failed events, so it lands in the run log as
 * triggered_by=manual with the user). A task that is running right now (mutex held) is not started again
 * and is logged as skipped. Only tasks from ScheduleDefinition can be run; there is no free command input.
 */
final class TaskRunner
{
    /** Seconds a manual run may take in the web request. */
    public const TIME_LIMIT = 120;

    public function __construct(private readonly RunLog $log) {}

    public function isRunning(Event $event): bool
    {
        return $event->withoutOverlapping && $event->mutex->exists($event);
    }

    public function run(Event $event, ?int $userId): ScheduledTaskRun
    {
        return $this->log->asManual($userId, function () use ($event): ScheduledTaskRun {
            if ($this->isRunning($event)) {
                return $this->log->skipped($event, __('booking.admin.scheduler.run.already_running'));
            }

            $restoreLimit = $this->raiseTimeLimit();

            event(new ScheduledTaskStarting($event));
            $start = microtime(true);

            try {
                $event->run(app());
                event(new ScheduledTaskFinished($event, microtime(true) - $start));
            } catch (Throwable $e) {
                event(new ScheduledTaskFailed($event, $e));
            } finally {
                if ($restoreLimit !== null) {
                    @set_time_limit($restoreLimit);
                }
            }

            return ScheduledTaskRun::query()
                ->where('name', (string) $event->description)
                ->where('triggered_by', 'manual')
                ->latest('id')
                ->firstOrFail();
        });
    }

    /**
     * Raises a shorter PHP time limit (web request, e.g. 30 s) to TIME_LIMIT for the run and returns the previous
     * limit to restore afterwards. An unlimited (0, CLI and test runs) or longer limit is left alone (null): a fixed
     * set_time_limit(120) used to kill a whole `php artisan test` run 120 seconds after the scheduler tests.
     */
    private function raiseTimeLimit(): ?int
    {
        $current = (int) ini_get('max_execution_time');
        if ($current === 0 || $current >= self::TIME_LIMIT || ! function_exists('set_time_limit')) {
            return null;
        }

        return @set_time_limit(self::TIME_LIMIT) ? $current : null;
    }
}
