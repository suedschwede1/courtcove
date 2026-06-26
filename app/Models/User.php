<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A system user (club member). Maps to the real bs_users table:
 * display name is `alias`, password hash is `pw`.
 *
 * Authorization (ported from the ZF2 User entity) is status-driven — there is no
 * roles/permissions table:
 *   - status 'admin'  → can() everything
 *   - status 'assist' → per-permission flags in bs_users_meta (key 'allow.<perm>' = 'true')
 *   - status 'enabled' (and others) → no privileged permissions
 *
 * Profile fields (firstname, lastname, phone, …) live in bs_users_meta.
 *
 * @property int         $uid
 * @property string      $alias   Display name
 * @property string      $status  admin|assist|enabled|placeholder|disabled|blocked|deleted
 * @property string|null $email
 * @property string|null $pw      Bcrypt password hash
 */
class User extends Authenticatable
{
    use HasFactory;

    /** All assignable privileges (ported from ZF2 User::$privileges). */
    public const PRIVILEGES = [
        'admin.user', 'admin.booking', 'admin.event', 'admin.config', 'admin.see-menu',
        'calendar.see-past', 'calendar.see-data',
        'calendar.create-single-bookings', 'calendar.cancel-single-bookings', 'calendar.delete-single-bookings',
        'calendar.create-subscription-bookings', 'calendar.cancel-subscription-bookings', 'calendar.delete-subscription-bookings',
    ];

    protected $table      = 'bs_users';
    protected $primaryKey = 'uid';
    public $timestamps    = false;

    protected $fillable = [
        'alias', 'status', 'email', 'pw', 'login_attempts', 'login_detent', 'last_activity', 'last_ip', 'created',
    ];

    protected $hidden = ['pw'];

    /** Route-model binding for {user} resolves on the primary key `uid`. */
    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    /** Laravel auth reads the password from the `pw` column. */
    public function getAuthPassword(): string
    {
        return (string) $this->pw;
    }

    /** Tell Laravel the password column is named 'pw', not 'password'. */
    public function getAuthPasswordName(): string
    {
        return 'pw';
    }

    /** Expose `name` as an alias of the display name for view compatibility. */
    public function getNameAttribute(): string
    {
        return (string) $this->alias;
    }

    /** Active accounts have status 'enabled' (or are privileged: assist/admin). */
    public function isEnabled(): bool
    {
        return in_array($this->status, ['enabled', 'assist', 'admin'], true);
    }

    /**
     * Whether the user holds the given privilege(s).
     *
     * Admins hold everything. For assist users, privilege flags are stored in
     * bs_users_meta as key 'allow.<privilege>' = 'true'. The privilege string
     * supports OR (comma) and AND (plus): "a, b+c" means a OR (b AND c).
     */
    public function can($privileges, $arguments = []): bool
    {
        if ($this->status === 'admin') {
            return true;
        }

        if ($this->status !== 'assist' || !is_string($privileges)) {
            return false;
        }

        foreach (explode(',', $privileges) as $orPrivilege) {
            $andPrivileges = explode('+', $orPrivilege);
            $matched = 0;

            foreach ($andPrivileges as $andPrivilege) {
                if ($this->getMeta('allow.' . trim($andPrivilege)) === 'true') {
                    $matched++;
                }
            }

            if ($matched === count($andPrivileges)) {
                return true;
            }
        }

        return false;
    }

    /** Read a single meta value by key from bs_users_meta. */
    public function getMeta(string $key, ?string $default = null): ?string
    {
        $value = $this->meta()->where('key', $key)->value('value');

        return $value !== null ? (string) $value : $default;
    }

    /** Upsert a single meta value (bs_users_meta key/value); null deletes the row. */
    public function setMeta(string $key, ?string $value): void
    {
        if ($value === null) {
            $this->meta()->where('key', $key)->delete();
            return;
        }
        $row = $this->meta()->where('key', $key)->first();
        if ($row) {
            $row->update(['value' => $value]);
        } else {
            $this->meta()->create(['key' => $key, 'value' => $value]);
        }
    }

    /** Replace the set of granted privileges (assist allow.* flags). */
    public function syncPrivileges(array $privileges): void
    {
        foreach (self::PRIVILEGES as $priv) {
            $this->setMeta('allow.' . $priv, in_array($priv, $privileges, true) ? 'true' : null);
        }
    }

    /** Currently granted privilege slugs (from allow.* meta). */
    public function grantedPrivileges(): array
    {
        return $this->meta()
            ->where('key', 'like', 'allow.%')
            ->where('value', 'true')
            ->pluck('key')
            ->map(fn (string $k) => substr($k, strlen('allow.')))
            ->all();
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'uid', 'uid');
    }

    /** @return HasMany<UserMeta, $this> */
    public function meta(): HasMany
    {
        return $this->hasMany(UserMeta::class, 'uid', 'uid');
    }
}
