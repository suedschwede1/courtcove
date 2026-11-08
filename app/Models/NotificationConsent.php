<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A member's consent to receive notifications on a channel for one purpose.
 * Holds a hash and the last two digits of the number, never the number itself.
 *
 * @property int $id
 * @property int $user_id
 * @property string $channel
 * @property string $purpose
 * @property string $phone_hash
 * @property string $phone_last2
 * @property string $locale
 * @property string $consent_text_version
 * @property Carbon $consented_at
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_via account|stop|admin|member_exit|anonymized|user_status|phone_changed
 * @property int|null $active_marker 1 while active, null once revoked (backs the unique key)
 */
class NotificationConsent extends Model
{
    public const PURPOSE_PLAYER_SEARCH = 'player_search';

    public const ACTIVE_MARKER = 1;

    protected $table = 'notification_consents';

    protected $fillable = [
        'user_id', 'channel', 'purpose', 'phone_hash', 'phone_last2', 'locale',
        'consent_text_version', 'consented_at', 'revoked_at', 'revoked_via', 'active_marker',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'consented_at' => 'datetime',
            'revoked_at' => 'datetime',
            'active_marker' => 'integer',
        ];
    }

    /**
     * @param  Builder<NotificationConsent>  $query
     * @return Builder<NotificationConsent>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function revoke(string $via): void
    {
        $this->forceFill([
            'revoked_at' => Carbon::now(),
            'revoked_via' => $via,
            'active_marker' => null,
        ])->save();
    }
}
