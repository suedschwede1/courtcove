<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
