<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $member_id
 * @property string $amount
 * @property Carbon $paid_at
 * @property string|null $note
 */
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

    /** @return BelongsToMany<HouseholdDue, $this> */
    public function householdDues(): BelongsToMany
    {
        return $this->belongsToMany(HouseholdDue::class, 'household_due_payment', 'payment_id', 'household_due_id')->withTimestamps();
    }
}
