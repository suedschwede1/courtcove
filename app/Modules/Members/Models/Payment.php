<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Payment extends Model
{
    protected $fillable = ['member_id', 'amount', 'paid_at', 'note'];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsToMany<MemberDue, $this> */
    public function dues(): BelongsToMany
    {
        return $this->belongsToMany(MemberDue::class, 'payment_dues');
    }
}
