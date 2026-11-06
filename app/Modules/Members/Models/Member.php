<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use App\Models\Option;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\Modules\Members\Models\MemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $member_number
 * @property int|null $nuliga_id
 * @property int|null $user_id
 * @property string $firstname
 * @property string $lastname
 * @property \Illuminate\Support\Carbon|null $birthdate
 * @property string|null $gender
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $street
 * @property string|null $zip
 * @property string|null $city
 * @property int|null $fee_category_id
 * @property \Illuminate\Support\Carbon|null $joined_at
 * @property \Illuminate\Support\Carbon|null $left_at
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $anonymized_at
 * @property string|null $notiz
 */
class Member extends Model
{
    use HasFactory;

    public const GENDERS = ['m', 'w', 'd'];

    public const STATUSES = ['aktiv', 'ruhend', 'ausgetreten'];

    /** Oldest age that still counts as youth (overview figure and the "Jugend" list filter). */
    public const YOUTH_MAX_AGE = 18;

    protected $fillable = [
        'member_number', 'nuliga_id', 'user_id', 'firstname', 'lastname', 'birthdate', 'gender',
        'email', 'phone', 'street', 'zip', 'city', 'fee_category_id', 'joined_at', 'left_at', 'status',
        'anonymized_at', 'notiz',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'joined_at' => 'date',
        'left_at' => 'date',
        'anonymized_at' => 'datetime',
    ];

    protected static function newFactory(): MemberFactory
    {
        return MemberFactory::new();
    }

    /** @return HasMany<MemberDue, $this> */
    public function dues(): HasMany
    {
        return $this->hasMany(MemberDue::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return BelongsTo<FeeCategory, $this> */
    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uid');
    }

    /** @return BelongsToMany<ClubRole, $this> */
    public function clubRoles(): BelongsToMany
    {
        return $this->belongsToMany(ClubRole::class, 'member_club_role');
    }

    public function age(): ?int
    {
        return $this->birthdate?->age;
    }

    /**
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'aktiv');
    }

    /**
     * Current members: active or dormant, not departed.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereIn('status', ['aktiv', 'ruhend']);
    }

    /**
     * Youth: YOUTH_MAX_AGE (18) or younger today; members without a birth date never count as youth.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeYouth(Builder $query): Builder
    {
        // whereDate: SQLite keeps the time part of a date cast ("2007-11-06 00:00:00"), so compare dates only.
        return $query->whereDate('birthdate', '>', now()->subYears(self::YOUTH_MAX_AGE + 1)->toDateString());
    }

    /**
     * Members with at least one open due (any fiscal year): not marked paid and without a payment (MemberDue::open).
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeWithOpenDues(Builder $query): Builder
    {
        return $query->whereHas('dues', fn (Builder $dues) => $dues->open());
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

    public function paidUntil(): ?Carbon
    {
        return $this->dues()
            ->get()
            ->filter(fn (MemberDue $due) => $due->isPaid())
            ->map(fn (MemberDue $due) => $due->fiscalYear->ends_on)
            ->sort()
            ->last();
    }

    /** Setzt das Austrittsdatum und leitet daraus automatisch den Status ab: gesetzt = ausgetreten, geleert = aktiv. */
    public function setLeftAt(?string $date): void
    {
        $this->update([
            'left_at' => $date,
            'status' => $date === null ? 'aktiv' : 'ausgetreten',
        ]);
    }

    /** Setzt den Status manuell (nur 'aktiv' oder 'ruhend' — 'ausgetreten' läuft ausschließlich über setLeftAt()). Zurück auf 'aktiv' löscht ein gesetztes Austrittsdatum. */
    public function setStatus(string $status): void
    {
        $this->update([
            'status' => $status,
            'left_at' => $status === 'aktiv' ? null : $this->left_at,
        ]);
    }

    /** DSGVO: ein ausgetretenes Mitglied darf erst nach Ablauf der Aufbewahrungsfrist anonymisiert werden. */
    public function isAnonymizable(): bool
    {
        $from = $this->anonymizableFrom();

        return $from !== null && $from->lt(now());
    }

    /** Day the retention period of a departed member ends (departure + retention years); null if anonymisation does not apply. */
    public function anonymizableFrom(): ?Carbon
    {
        if ($this->anonymized_at !== null || $this->status !== 'ausgetreten' || $this->left_at === null) {
            return null;
        }

        return $this->left_at->copy()->addYears(self::retentionYears());
    }

    /** Configured retention period in years (DSGVO settings); a missing or malformed value falls back to 3. */
    public static function retentionYears(): int
    {
        $rawRetention = Option::getValue('members.anonymization.retention_years', 3);

        return is_numeric($rawRetention) && (int) $rawRetention > 0 ? (int) $rawRetention : 3;
    }
}
