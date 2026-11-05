<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of a member bulk-mail send.
 */
class MailSend extends Model
{
    protected $fillable = [
        'sent_by_user_id',
        'audience_key',
        'audience_label',
        'recipient_count',
        'from_name',
        'from_address',
        'subject',
        'body',
    ];

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id', 'uid');
    }
}
