<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use App\Modules\Members\Events\FiscalYearsLocking;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property Carbon|null $faellig_am
 * @property Carbon|null $abgeschlossen_am
 * @property int|null $abgeschlossen_von
 */
class FiscalYear extends Model
{
    // abgeschlossen_am/abgeschlossen_von are set only by close()/reopen(), never mass-assigned.
    protected $fillable = ['name', 'starts_on', 'ends_on', 'faellig_am'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'faellig_am' => 'date',
        'abgeschlossen_am' => 'datetime',
        'abgeschlossen_von' => 'integer',
    ];

    /** A closed year is read-only: no change to the year, its rates, dues or payments. */
    public function isClosed(): bool
    {
        return $this->abgeschlossen_am !== null;
    }

    public function close(int $userId): void
    {
        $this->forceFill(['abgeschlossen_am' => now(), 'abgeschlossen_von' => $userId])->save();
    }

    public function reopen(): void
    {
        $this->forceFill(['abgeschlossen_am' => null, 'abgeschlossen_von' => null])->save();
    }

    /** Name for selects: "2025 (abgeschlossen)" for a closed year. */
    public function labelWithState(): string
    {
        return $this->isClosed()
            ? __('members::admin.fiscal_years.closed_option', ['year' => $this->name])
            : $this->name;
    }

    /**
     * Write guard of the close feature. Locks the rows of the given years (SELECT ... FOR UPDATE in
     * ascending id order) and returns the first one that is closed, or null when all are open.
     *
     * Call it inside the write transaction, after validation and before any other row is locked or
     * written: year rows first is the module's lock order. A close running at the same time either
     * waits for this transaction to commit or has committed and is seen here, so a year closed
     * between validation and writing can no longer slip through.
     *
     * @param  iterable<int|string>|int  $ids
     */
    public static function lockForWrite(iterable|int $ids): ?self
    {
        $ids = collect(is_int($ids) ? [$ids] : $ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($ids === []) {
            return null;
        }

        event(new FiscalYearsLocking($ids));

        return self::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()
            ->first(fn (self $year): bool => $year->isClosed());
    }

    /**
     * lockForWrite() that refuses with the year's "locked" message as a validation error under
     * $errorKey (redirect back with the error, or 422 JSON for JSON requests).
     *
     * @param  iterable<int|string>|int  $ids
     */
    public static function lockOpenOrFail(iterable|int $ids, string $errorKey): void
    {
        $closed = self::lockForWrite($ids);
        if ($closed !== null) {
            throw ValidationException::withMessages([$errorKey => $closed->lockedMessage()]);
        }
    }

    /** The "locked" message for this year, shown wherever a change is refused. */
    public function lockedMessage(): string
    {
        return __('members::admin.fiscal_years.locked', ['year' => $this->name]);
    }

    /** @return HasMany<FeeCategoryRate, $this> */
    public function feeCategoryRates(): HasMany
    {
        return $this->hasMany(FeeCategoryRate::class);
    }

    /** @return HasMany<MemberDue, $this> */
    public function memberDues(): HasMany
    {
        return $this->hasMany(MemberDue::class);
    }

    /** Whether the given day (default today) lies within this year, first and last day included. */
    public function covers(?Carbon $day = null): bool
    {
        $date = ($day ?? now())->toDateString();

        return $this->starts_on->toDateString() <= $date && $date <= $this->ends_on->toDateString();
    }

    /**
     * The earliest other year whose range shares at least one day with starts..ends (both Y-m-d,
     * both days included). Adjacent years (end = the day before the next start) do not overlap.
     */
    public static function firstOverlapping(string $startsOn, string $endsOn, ?int $exceptId = null): ?self
    {
        return self::query()
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->orderBy('starts_on')
            ->first();
    }

    /**
     * The business year shown when none is chosen: the year containing today (the most recent
     * one if several overlap); otherwise the latest year that has dues; otherwise the latest year.
     *
     * @param  Collection<int, FiscalYear>  $fiscalYears
     */
    public static function defaultFor(Collection $fiscalYears): ?self
    {
        $newestFirst = $fiscalYears->sortByDesc(fn (self $year) => $year->starts_on->toDateString());

        $current = $newestFirst->first(fn (self $year) => $year->covers());
        if ($current !== null) {
            return $current;
        }

        $withDues = MemberDue::query()->distinct()->pluck('fiscal_year_id')->map(fn ($id) => (int) $id)->all();

        return $newestFirst->first(fn (self $year) => in_array($year->id, $withDues, true))
            ?? $newestFirst->first();
    }
}
