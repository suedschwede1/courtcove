<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A system user (club member). Maps to the real bs_users table:
 * display name is `alias`, password hash is `pw`.
 *
 * Account status and permission (role) are two separate things:
 *   - status: placeholder | enabled | disabled (waiting for activation) | blocked | deleted
 *   - role:   member | assist (per-permission flags in bs_users_meta, key 'allow.<perm>' = 'true') | admin
 * A role only counts on an enabled account.
 *
 * Storage depends on config('users.split_role'):
 *   - off (default, what the old system reads): the role is merged into `status`
 *     ('admin' / 'assist' = enabled account with that role), the `role` column is unused.
 *   - on: `role` holds the role, `status` only the account state. Rows not yet split
 *     (status still 'admin' / 'assist') keep counting as enabled with that role.
 *
 * Profile fields (firstname, lastname, phone, …) live in bs_users_meta.
 *
 * @property int $uid
 * @property string $alias Display name
 * @property string $status placeholder|enabled|disabled|blocked|deleted, merged storage also admin|assist
 * @property string $role member|assist|admin (only read with users.split_role on)
 * @property-read string|null $last_booking_date Only set by the admin user list query
 * @property string|null $email
 * @property string|null $pw Bcrypt password hash
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;

    /** All assignable privileges (ported from ZF2 User::$privileges). */
    public const PRIVILEGES = [
        'admin.user', 'admin.booking', 'admin.event', 'admin.config', 'admin.see-menu', 'admin.members', 'admin.api-tokens',
        'calendar.see-past', 'calendar.see-data',
        'calendar.create-single-bookings', 'calendar.cancel-single-bookings', 'calendar.delete-single-bookings',
        'calendar.create-subscription-bookings', 'calendar.cancel-subscription-bookings', 'calendar.delete-subscription-bookings',
        'admin.tournament', 'admin.mixed', 'admin.pyramid', 'admin.draw',
    ];

    /** Selectable account states, in form order. */
    public const STATUSES = ['placeholder', 'enabled', 'disabled', 'blocked', 'deleted'];

    /** Selectable permissions, in form order. */
    public const ROLES = ['member', 'assist', 'admin'];

    /** Status values of the merged storage that stand for an enabled account with a role. */
    private const LEGACY_ROLE_STATUSES = ['assist', 'admin'];

    protected $table = 'bs_users';

    protected $primaryKey = 'uid';

    public $timestamps = false;

    protected $fillable = [
        'alias', 'status', 'role', 'email', 'pw', 'login_attempts', 'login_detent', 'last_activity', 'last_ip', 'created',
    ];

    protected $hidden = ['pw'];

    /**
     * bs_users (created by the legacy ZF2 system) has no remember_token column.
     * An empty name makes Laravel skip reading/writing the token, so "remember me"
     * logins do not 500 on an UPDATE of a missing column.
     */
    protected $rememberTokenName = '';

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

    /** Whether the role is stored in its own column (config users.split_role). */
    public static function splitsRole(): bool
    {
        return (bool) config('users.split_role', false);
    }

    /**
     * The value for bs_users.status that stores this status/role pair. Merged storage
     * folds a role of an enabled account into the status; split storage keeps the status.
     */
    public static function storedStatus(string $status, string $role): string
    {
        if (! self::splitsRole() && $status === 'enabled' && in_array($role, self::LEGACY_ROLE_STATUSES, true)) {
            return $role;
        }

        return $status;
    }

    /** Account state without the role: one of STATUSES (or an unknown raw value). */
    public function accountStatus(): string
    {
        $status = (string) $this->status;

        return in_array($status, self::LEGACY_ROLE_STATUSES, true) ? 'enabled' : $status;
    }

    /** The permission that is in effect: one of ROLES; 'member' unless the account is enabled. */
    public function effectiveRole(): string
    {
        $status = (string) $this->status;

        if (in_array($status, self::LEGACY_ROLE_STATUSES, true)) {
            return $status;
        }

        if ($status !== 'enabled' || ! self::splitsRole()) {
            return 'member';
        }

        $role = (string) ($this->attributes['role'] ?? 'member');

        return in_array($role, self::ROLES, true) ? $role : 'member';
    }

    /** Only enabled accounts may sign in (waiting, blocked, deleted and placeholder ones may not). */
    public function isEnabled(): bool
    {
        return $this->accountStatus() === 'enabled';
    }

    /** Privileged users should use shorter-lived sessions and no persistent login cookie. */
    public function isPrivileged(): bool
    {
        return $this->effectiveRole() !== 'member';
    }

    /**
     * Enabled accounts of every role. Identical in both storages: split rows keep
     * 'enabled', merged (or not yet split) rows use 'admin' / 'assist'.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), ['enabled', ...self::LEGACY_ROLE_STATUSES]);
    }

    /**
     * Accounts in the given account state; 'enabled' includes every role.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $status === 'enabled'
            ? $this->scopeActive($query)
            : $query->where($query->qualifyColumn('status'), $status);
    }

    /**
     * Accounts whose effective role (see effectiveRole()) is the given one.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWithRole(Builder $query, string $role): Builder
    {
        $status = $query->qualifyColumn('status');
        $roleColumn = $query->qualifyColumn('role');
        $split = self::splitsRole();

        $hasRole = function (Builder $q, string $wanted) use ($status, $roleColumn, $split): void {
            $q->where($status, $wanted);
            if ($split) {
                $q->orWhere(fn (Builder $sub) => $sub->where($status, 'enabled')->where($roleColumn, $wanted));
            }
        };

        if ($role !== 'member') {
            return $query->where(fn (Builder $q) => $hasRole($q, $role));
        }

        foreach (self::LEGACY_ROLE_STATUSES as $privileged) {
            $query->whereNot(fn (Builder $q) => $hasRole($q, $privileged));
        }

        return $query;
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
        $role = $this->effectiveRole();

        if ($role === 'admin') {
            return true;
        }

        if ($role !== 'assist' || ! is_string($privileges)) {
            return false;
        }

        if (! $this->relationLoaded('meta')) {
            $this->load('meta');
        }

        foreach (explode(',', $privileges) as $orPrivilege) {
            $andPrivileges = explode('+', $orPrivilege);
            $matched = 0;

            foreach ($andPrivileges as $andPrivilege) {
                if ($this->getMeta('allow.'.trim($andPrivilege)) === 'true') {
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
        if ($this->relationLoaded('meta')) {
            $value = $this->meta->firstWhere('key', $key)?->value;

            return $value !== null ? (string) $value : $default;
        }

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
            $this->setMeta('allow.'.$priv, in_array($priv, $privileges, true) ? 'true' : null);
        }
    }

    /** Currently granted privilege slugs (from allow.* meta). */
    public function grantedPrivileges(): array
    {
        $source = $this->relationLoaded('meta')
            ? $this->meta->filter(fn ($m) => str_starts_with($m->key, 'allow.') && $m->value === 'true')
            : $this->meta()->where('key', 'like', 'allow.%')->where('value', 'true')->get();

        return $source
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
