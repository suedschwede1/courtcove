<?php

declare(strict_types=1);

namespace App\Console\Scheduling;

use App\Models\ScheduledTaskRun;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * The one place that defines the scheduled tasks (Laravel scheduler, run every minute by the server cron
 * `php artisan schedule:run`). Used by `schedule:run`/`schedule:list` (bootstrap/app.php withSchedule) and by
 * the admin page "Geplante Aufgaben", which builds its own Schedule from this definition.
 *
 * Adding a task is ONE line in define(), e.g.
 *   $this->command($schedule, 'notifications-dispatch', 'notifications:dispatch')->everyFiveMinutes();
 * Name and description come from the task name (lang booking.admin.scheduler.tasks.<name>.title/.description),
 * every task runs without overlapping and command output is kept for the run log.
 *
 * Only housekeeping, the player search expiry (player-exchange:expire, must run unattended) and the daily
 * block of the accounts of departed members (members:deactivate-left-accounts --since=14) are
 * scheduled here; business commands (other members:*, users:split-role,
 * booking:adopt-booking-rules, imports, phase2:*) stay manual on purpose.
 */
final class ScheduleDefinition
{
    public const HEARTBEAT = 'scheduler-heartbeat';

    public function define(Schedule $schedule): void
    {
        $this->call($schedule, self::HEARTBEAT, fn () => app(Heartbeat::class)->beat())->everyMinute();
        $this->call($schedule, 'scheduler-prune-runs', fn () => ScheduledTaskRun::prune())->dailyAt('03:10');
        $this->command($schedule, 'sanctum-prune-expired', 'sanctum:prune-expired --hours=24')->dailyAt('03:20');
        $this->command($schedule, 'auth-clear-resets', 'auth:clear-resets')->dailyAt('03:30');
        $this->command($schedule, 'player-exchange-expire', 'player-exchange:expire')->everyFiveMinutes();
        $this->command($schedule, 'notifications-dispatch', 'notifications:dispatch')->everyMinute();
        $this->command($schedule, 'notifications-prune', 'notifications:prune')->dailyAt('03:50');
        $this->command($schedule, 'members-deactivate-left', 'members:deactivate-left-accounts --since=14')->dailyAt('04:00');

        // A queue worker is only needed when jobs are queued (QUEUE_CONNECTION is sync today).
        if (self::usesQueue()) {
            $this->command($schedule, 'queue-work', 'queue:work --stop-when-empty --max-time=50')->everyMinute();
            $this->command($schedule, 'queue-prune-failed', 'queue:prune-failed --hours=168')->dailyAt('03:40');
        }
    }

    public static function usesQueue(): bool
    {
        return (string) config('queue.default', 'sync') !== 'sync';
    }

    /** All events of a fresh Schedule built from this definition (for the admin page). */
    public static function events(): array
    {
        $schedule = new Schedule((string) config('app.timezone'));
        (new self)->define($schedule);

        return $schedule->events();
    }

    public static function find(string $name): ?Event
    {
        foreach (self::events() as $event) {
            if ($event->description === $name) {
                return $event;
            }
        }

        return null;
    }

    private function command(Schedule $schedule, string $name, string $command): Event
    {
        return $schedule->command($command)
            ->name($name)
            ->withoutOverlapping(10)
            ->storeOutput();
    }

    private function call(Schedule $schedule, string $name, callable $callback): CallbackEvent
    {
        return $schedule->call($callback)->name($name)->withoutOverlapping(10);
    }
}
