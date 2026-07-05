<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClubRole extends Model
{
    protected $fillable = ['name', 'is_board', 'tasks'];

    protected $casts = [
        'is_board' => 'boolean',
    ];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_club_role');
    }
}
