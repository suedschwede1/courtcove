<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @property string $name */
class ClubRole extends Model
{
    protected $fillable = ['name', 'is_board', 'tasks'];

    protected $casts = [
        'is_board' => 'boolean',
    ];

    /** @return BelongsToMany<Member, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_club_role');
    }

    /** @return BelongsToMany<MailCategory, $this> */
    public function mailCategories(): BelongsToMany
    {
        return $this->belongsToMany(MailCategory::class, 'club_role_mail_category');
    }
}
