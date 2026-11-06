<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Console\Scheduling\RunLog;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;

/** Feeds the scheduled task run log (table scheduled_task_runs) from the scheduler events. */
final class LogScheduledTaskRun
{
    public function __construct(private readonly RunLog $log) {}

    public function handle(ScheduledTaskStarting|ScheduledTaskFinished|ScheduledTaskFailed|ScheduledTaskSkipped $event): void
    {
        // A broken log (e.g. database away) must never stop the scheduler from running the next tasks.
        try {
            match (true) {
                $event instanceof ScheduledTaskStarting => $this->log->starting($event->task),
                $event instanceof ScheduledTaskFinished => $this->log->finished($event->task, $event->runtime),
                $event instanceof ScheduledTaskFailed => $this->log->failed($event->task, $event->exception),
                $event instanceof ScheduledTaskSkipped => $this->log->skipped($event->task),
            };
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
