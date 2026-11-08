<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A browser that completed the e-mail code check for an account (see App\Services\TrustedDeviceService).
 * Only the SHA-256 of the cookie token is stored; the label is a display aid derived from the user agent and
 * never used for any decision. Validity is fixed from issue (expires_at), never extended.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property string $label
 * @property string|null $country_code
 * @property Carbon|null $created_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 */
class TrustedDevice extends Model
{
    public $timestamps = false;

    protected $table = 'trusted_devices';

    protected $fillable = ['user_id', 'token_hash', 'label', 'country_code', 'created_at', 'last_seen_at', 'expires_at', 'revoked_at'];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'created_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Devices that still count: not revoked and not expired.
     *
     * @param  Builder<TrustedDevice>  $query
     * @return Builder<TrustedDevice>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
