<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Database\Factories\Modules\Members\Models\MemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    public const GENDERS = ['m', 'w', 'd'];

    protected $fillable = [
        'member_number', 'nuliga_id', 'firstname', 'lastname', 'birthdate', 'gender',
        'email', 'phone', 'street', 'zip', 'city', 'fee_category_id', 'joined_at', 'left_at',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'joined_at' => 'date',
        'left_at' => 'date',
    ];

    protected static function newFactory(): MemberFactory
    {
        return MemberFactory::new();
    }

    public function dues(): HasMany
    {
        return $this->hasMany(MemberDue::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }

    public function age(): ?int
    {
        return $this->birthdate?->age;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('left_at');
    }

    public function fullName(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }

    /** Next free member_number, for pre-filling the create form (max existing + 1, or 1 if none exist). */
    public static function nextNumber(): int
    {
        return (int) (static::query()->max('member_number') ?? 0) + 1;
    }

    public function paidUntil(): ?\Carbon\Carbon
    {
        return $this->dues()
            ->get()
            ->filter(fn (MemberDue $due) => $due->isPaid())
            ->map(fn (MemberDue $due) => $due->fiscalYear->ends_on)
            ->sort()
            ->last();
    }
}
