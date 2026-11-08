<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per login attempt (success/failed/throttled) for a resolved user —
 * unmatched emails aren't recorded, there's no account/baseline to protect.
 * Feeds App\Services\LoginRiskEvaluator, and the admin-only, display-only risk
 * score (riskScore()). Retention: 30 days, pruned opportunistically on every
 * insert (no cron on shared hosting).
 *
 * @property int $id
 * @property int $user_id
 * @property string $ip
 * @property string|null $country_code
 * @property string $status success|failed|throttled
 * @property string|null $risk_flags comma-separated trigger names, only ever set on success rows
 */
class LoginHistory extends Model
{
    protected $table = 'login_history';

    public $timestamps = false;

    protected $fillable = ['user_id', 'ip', 'country_code', 'status', 'risk_flags', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uid');
    }

    /** @param array<int, string> $riskFlags */
    public static function record(?int $userId, string $ip, ?string $countryCode, string $status, array $riskFlags = []): void
    {
        if ($userId === null) {
            return;
        }

        self::create([
            'user_id' => $userId,
            'ip' => $ip,
            'country_code' => $countryCode,
            'status' => $status,
            'risk_flags' => $riskFlags === [] ? null : implode(',', $riskFlags),
            'created_at' => now(),
        ]);

        self::query()
            ->where('user_id', $userId)
            ->where('created_at', '<', now()->subDays(30))
            ->delete();
    }

    /** Risk signals of a row: 'new_device' is the device confirmation step, not a risk, and does not count. */
    private static function riskFlagCount(self $row): int
    {
        return count(array_diff(explode(',', (string) $row->risk_flags), ['new_device']));
    }

    /** Display-only: total number of individual risk flags fired in the last 30 days. */
    public static function riskScore(int $userId): int
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('risk_flags')
            ->get()
            ->sum(fn (self $row): int => self::riskFlagCount($row));
    }

    /**
     * Batch version of riskScore() for a list of users (e.g. an admin index page) —
     * one query instead of one per user.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int> user_id => score
     */
    public static function riskScores(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return self::query()
            ->whereIn('user_id', $userIds)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('risk_flags')
            ->get(['user_id', 'risk_flags'])
            ->groupBy('user_id')
            ->map(fn ($rows): int => $rows->sum(fn (self $row): int => self::riskFlagCount($row)))
            ->all();
    }
}
