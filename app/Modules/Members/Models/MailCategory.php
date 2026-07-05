<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MailCategory extends Model
{
    protected $fillable = ['name'];

    public function clubRoles(): BelongsToMany
    {
        return $this->belongsToMany(ClubRole::class, 'club_role_mail_category');
    }
}
