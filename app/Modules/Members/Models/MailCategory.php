<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 */
class MailCategory extends Model
{
    protected $fillable = ['name'];

    /** @return BelongsToMany<ClubRole, $this> */
    public function clubRoles(): BelongsToMany
    {
        return $this->belongsToMany(ClubRole::class, 'club_role_mail_category');
    }
}
