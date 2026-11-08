<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Outbox row and delivery record of one notification. Holds IDs and keys only:
 * no message text, no name, no full phone number.
 *
 * @property int $id
 * @property string $type
 * @property string $channel
 * @property int|null $user_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $dedupe_key
 * @property string $status
 * @property string|null $skip_reason
 * @property int $priority
 * @property array<string, mixed>|null $subject_payload
 * @property int $attempts
 * @property Carbon $not_before
 * @property string|null $provider_sid
 * @property string|null $error_code
 * @property int|null $cost_estimate_micros
 * @property int|null $cost_reported_micros
 * @property string|null $phone_last2
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 */
class NotificationLogEntry extends Model
{
    public const PENDING = 'pending';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const READ = 'read';

    public const FAILED = 'failed';

    public const UNDELIVERED = 'undelivered';

    public const SKIPPED = 'skipped';

    public const SUBJECT_GONE = 'subject_gone';

    public const NO_CONSENT = 'no_consent';

    public const GATE = 'gate';

    public const PHONE_CHANGED = 'phone_changed';

    public const DAILY_LIMIT = 'daily_limit';

    public const BUDGET = 'budget';

    public const DISABLED = 'disabled';

    public const TEMPLATE_MISSING = 'template_missing';

    /** The notification type is switched off (option `whatsapp.type.<key>.enabled`). */
    public const TYPE_DISABLED = 'type_disabled';

    public const PAUSED = 'paused';

    protected $table = 'notification_log';

    protected $fillable = [
        'type', 'channel', 'user_id', 'subject_type', 'subject_id', 'dedupe_key', 'status', 'skip_reason',
        'priority', 'subject_payload', 'attempts', 'not_before', 'provider_sid', 'error_code',
        'cost_estimate_micros', 'cost_reported_micros', 'phone_last2', 'sent_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'subject_id' => 'integer',
            'priority' => 'integer',
            'attempts' => 'integer',
            'subject_payload' => 'array',
            'not_before' => 'datetime',
            'cost_estimate_micros' => 'integer',
            'cost_reported_micros' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
