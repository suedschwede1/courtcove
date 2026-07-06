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
 * insert (no cron on hosting.example.test).
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

    /** Display-only: total number of individual risk flags fired in the last 30 days. */
    public static function riskScore(int $userId): int
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('risk_flags')
            ->get()
            ->sum(fn (self $row): int => count(explode(',', (string) $row->risk_flags)));
    }
}
