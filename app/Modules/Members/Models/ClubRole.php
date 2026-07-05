<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClubRole extends Model
{
    protected $fillable = ['name', 'is_board', 'mail_category_id', 'tasks'];

    protected $casts = [
        'is_board' => 'boolean',
    ];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_club_role');
    }

    public function mailCategory(): BelongsTo
    {
        return $this->belongsTo(MailCategory::class);
    }
}
