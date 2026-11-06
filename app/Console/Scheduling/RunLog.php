<?php

declare(strict_types=1);

namespace App\Console\Scheduling;

use App\Models\ScheduledTaskRun;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Throwable;

/**
 * Writes the run log. Container singleton (per application instance, so nothing leaks between tests):
 * remembers start times between the Starting and Finished events and who triggered a manual run.
 *
 * The heartbeat is not logged when it succeeds (it runs every minute; its time is the Heartbeat option).
 */
final class RunLog
{
    /** @var array<int, CarbonImmutable> */
    private array $starts = [];

    private ?int $manualUserId = null;

    private bool $manual = false;

    /** Marks the runs logged inside $callback as manual runs of the given user ("Jetzt ausführen"). */
    public function asManual(?int $userId, callable $callback): mixed
    {
        $this->manual = true;
        $this->manualUserId = $userId;

        try {
            return $callback();
        } finally {
            $this->manual = false;
            $this->manualUserId = null;
        }
    }

    public function starting(Event $event): void
    {
        $this->starts[spl_object_id($event)] = CarbonImmutable::now();
    }

    public function finished(Event $event, float $runtimeSeconds): ?ScheduledTaskRun
    {
        $exitCode = $event->exitCode;
        $status = $exitCode === null || $exitCode === 0 ? 'ok' : 'failed';

        if ($status === 'ok' && $event->description === ScheduleDefinition::HEARTBEAT && ! $this->manual) {
            unset($this->starts[spl_object_id($event)]);

            return null;
        }

        return $this->write($event, $status, $exitCode, (int) round($runtimeSeconds * 1000), $this->readOutput($event));
    }

    public function failed(Event $event, Throwable $exception): ScheduledTaskRun
    {
        $output = trim(($this->readOutput($event) ?? '')."\n".$exception::class.': '.$exception->getMessage());

        return $this->write($event, 'failed', $event->exitCode, null, $output);
    }

    public function skipped(Event $event, ?string $reason = null): ScheduledTaskRun
    {
        return $this->write($event, 'skipped', null, 0, $reason);
    }

    private function write(Event $event, string $status, ?int $exitCode, ?int $durationMs, ?string $output): ScheduledTaskRun
    {
        $now = CarbonImmutable::now();
        $started = $this->starts[spl_object_id($event)] ?? $now;
        unset($this->starts[spl_object_id($event)]);

        return ScheduledTaskRun::query()->create([
            'name' => mb_substr((string) ($event->description ?? 'unnamed'), 0, 100),
            'command' => $event->command !== null ? mb_substr(self::displayCommand($event->command), 0, 255) : null,
            'started_at' => $started,
            'finished_at' => $now,
            'duration_ms' => $durationMs ?? (int) $started->diffInMilliseconds($now, true),
            'status' => $status,
            'exit_code' => $exitCode,
            'output_excerpt' => ScheduledTaskRun::excerpt($output),
            'triggered_by' => $this->manual ? 'manual' : 'scheduler',
            'user_id' => $this->manual ? $this->manualUserId : null,
        ]);
    }

    /** The artisan part of a built command line ("sanctum:prune-expired --hours=24"), without PHP binary and paths. */
    public static function displayCommand(string $command): string
    {
        if (preg_match("/artisan['\"]?\\s+(.+)$/", $command, $m) === 1) {
            return trim(str_replace(["'", '"'], '', $m[1]));
        }

        return $command;
    }

    private function readOutput(Event $event): ?string
    {
        $path = (string) $event->output;
        if ($path === '' || $path === '/dev/null' || $path === 'NUL' || ! is_file($path)) {
            return null;
        }

        $content = @file_get_contents($path);

        return $content === false ? null : $content;
    }
}
