<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Console\Command;

/** Sends the due WhatsApp outbox rows, run by the scheduler every minute. Output: counters only. */
class DispatchNotificationsCommand extends Command
{
    protected $signature = 'notifications:dispatch {--max= : Maximum number of rows for this run}';

    protected $description = 'Send due WhatsApp notifications from the outbox';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $max = $this->option('max');
        $report = $dispatcher->run($max === null || $max === '' ? null : max(0, (int) $max));

        $skipped = array_sum($report->skipped);
        $line = sprintf('sent=%d failed=%d retried=%d stale=%d skipped=%d', $report->sent, $report->failed, $report->retried, $report->stale, $skipped);
        if ($report->stoppedReason !== null) {
            $line .= ' stopped='.$report->stoppedReason;
        }
        $this->info($line);

        return self::SUCCESS;
    }
}
