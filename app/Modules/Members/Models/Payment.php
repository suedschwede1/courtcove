<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use App\Models\User;
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
 * @property Carbon|null $cancelled_at Storno ("Zahlung stornieren"); a cancelled payment pays nothing
 * @property int|null $cancelled_by
 * @property string|null $cancel_reason
 */
class Payment extends Model
{
    protected $fillable = ['member_id', 'amount', 'paid_at', 'note'];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
        'cancelled_at' => 'datetime',
        'cancelled_by' => 'integer',
    ];

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by', 'uid');
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** All dues the payment covers or covered (the links stay after a storno, as history). @return BelongsToMany<MemberDue, $this> */
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
