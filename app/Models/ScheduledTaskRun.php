<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One run of a scheduled task (written by App\Listeners\LogScheduledTaskRun).
 *
 * @property int $id
 * @property string $name
 * @property string|null $command
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property int|null $duration_ms
 * @property string $status ok|failed|skipped
 * @property int|null $exit_code
 * @property string|null $output_excerpt
 * @property string $triggered_by scheduler|manual
 * @property int|null $user_id
 */
class ScheduledTaskRun extends Model
{
    public const STATUSES = ['ok', 'failed', 'skipped'];

    public const KEEP_DAYS = 14;

    public const EXCERPT_LENGTH = 2000;

    public $timestamps = false;

    protected $fillable = [
        'name', 'command', 'started_at', 'finished_at', 'duration_ms', 'status', 'exit_code', 'output_excerpt', 'triggered_by', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'exit_code' => 'integer',
            'user_id' => 'integer',
        ];
    }

    /** Deletes runs older than KEEP_DAYS; returns the number of deleted rows (daily task "scheduler-prune-runs"). */
    public static function prune(): int
    {
        return static::query()->where('started_at', '<', CarbonImmutable::now()->subDays(self::KEEP_DAYS))->delete();
    }

    /** Last EXCERPT_LENGTH characters of the output with obvious secrets masked. */
    public static function excerpt(?string $output): ?string
    {
        if ($output === null || trim($output) === '') {
            return null;
        }

        $masked = (string) preg_replace(
            '/((?:password|passwd|secret|token|api[_-]?key|key)\s*[=:]\s*)\S+/i',
            '$1[redacted]',
            $output,
        );
        $masked = (string) preg_replace('/\b(?:mysql|pgsql|redis|https?):\/\/[^\s@]+@/i', '[redacted]@', $masked);

        return mb_strlen($masked) > self::EXCERPT_LENGTH ? mb_substr($masked, -self::EXCERPT_LENGTH) : rtrim($masked);
    }
}
