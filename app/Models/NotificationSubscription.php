<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A member's subscription to one notification type on one channel, with optional filters.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $channel
 * @property array<string, mixed>|null $filters
 */
class NotificationSubscription extends Model
{
    protected $table = 'notification_subscriptions';

    protected $fillable = ['user_id', 'type', 'channel', 'filters'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'filters' => 'array',
        ];
    }
}
