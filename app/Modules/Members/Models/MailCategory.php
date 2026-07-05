<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailCategory extends Model
{
    protected $fillable = ['name'];

    public function clubRoles(): HasMany
    {
        return $this->hasMany(ClubRole::class);
    }
}
