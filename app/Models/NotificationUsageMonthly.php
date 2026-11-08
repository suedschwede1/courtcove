<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Message counts and costs per month and channel (budget guard).
 *
 * @property int $id
 * @property string $month YYYY-MM
 * @property string $channel
 * @property int $messages_sent
 * @property int $messages_reserved
 * @property int $estimated_micros
 * @property int $reported_micros
 * @property Carbon|null $warned_80_at
 * @property Carbon|null $warned_100_at
 */
class NotificationUsageMonthly extends Model
{
    public $timestamps = false;

    protected $table = 'notification_usage_monthly';

    protected $fillable = [
        'month', 'channel', 'messages_sent', 'messages_reserved', 'estimated_micros', 'reported_micros',
        'warned_80_at', 'warned_100_at',
    ];

    protected function casts(): array
    {
        return [
            'messages_sent' => 'integer',
            'messages_reserved' => 'integer',
            'estimated_micros' => 'integer',
            'reported_micros' => 'integer',
            'warned_80_at' => 'datetime',
            'warned_100_at' => 'datetime',
        ];
    }
}
