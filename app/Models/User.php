<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A system user (club member or admin).
 *
 * @property int        $uid
 * @property string     $name
 * @property string     $email
 * @property string     $phone
 * @property string     $roles        Space-separated role list
 * @property string     $permissions  Space-separated permission list
 * @property UserStatus $status
 * @property int        $created      Unix timestamp
 * @property int        $updated      Unix timestamp
 */
class User extends Authenticatable
{
    use HasFactory;

    protected $table      = 'bs_users';
    protected $primaryKey = 'uid';
    public $timestamps    = false;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'roles', 'permissions',
        'status', 'token', 'created', 'updated',
    ];

    protected $hidden = ['password', 'token'];

    protected $casts = [
        'status' => UserStatus::class,
    ];

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

    /** Whether user has the given role (space-separated list). */
    public function hasRole(string $role): bool
    {
        return in_array($role, explode(' ', (string) $this->getRawOriginal('roles')), strict: true);
    }

    /** Whether user has the given permission (space-separated list). */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, explode(' ', (string) $this->getRawOriginal('permissions')), strict: true);
    }
}
