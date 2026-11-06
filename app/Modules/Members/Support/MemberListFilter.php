<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filters of the members list, read from the query string. The quick-filter chip (`filter`) is combined with the
 * search and the "more filters" (fee category, due status); every value is read scalar-safe, so array-shaped or
 * unknown input falls back to the default instead of failing ("alle" for the chip).
 *
 * Chips: alle = current members (active or dormant), aktiv, ruhend, jugend = current members aged
 * Member::YOUTH_MAX_AGE or younger, offen = current members with at least one open due; ausgetreten = departed
 * members (reached through "more filters", not a main chip).
 */
final readonly class MemberListFilter
{
    /** The chips shown above the list, in order. */
    public const CHIPS = ['alle', 'aktiv', 'ruhend', 'jugend', 'offen'];

    /** Every accepted chip value (the main chips plus departed members). */
    public const FILTERS = [...self::CHIPS, 'ausgetreten'];

    public const DEFAULT = 'alle';

    /** Due status filter values of "more filters". */
    public const DUE_STATUSES = ['bezahlt', 'ausstehend', 'ueberfaellig'];

    private const SEARCH_MAX = 100;

    /** Values of the former `status` parameter, so old links keep working. */
    private const LEGACY_STATUS = ['aktiv' => 'aktiv', 'ruhend' => 'ruhend', 'ausgetreten' => 'ausgetreten', 'all' => 'alle'];

    public function __construct(
        public string $chip = self::DEFAULT,
        public string $search = '',
        public ?int $feeCategory = null,
        public ?string $dueStatus = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $chip = self::scalar($request, 'filter');
        if ($chip === '') {
            $chip = self::LEGACY_STATUS[self::scalar($request, 'status')] ?? '';
        }

        $feeCategory = self::scalar($request, 'fee_category');
        $dueStatus = self::scalar($request, 'zahlung');

        return new self(
            chip: in_array($chip, self::FILTERS, true) ? $chip : self::DEFAULT,
            search: mb_substr(trim(self::scalar($request, 'search')), 0, self::SEARCH_MAX),
            feeCategory: ctype_digit($feeCategory) && (int) $feeCategory > 0 ? (int) $feeCategory : null,
            dueStatus: in_array($dueStatus, self::DUE_STATUSES, true) ? $dueStatus : null,
        );
    }

    /**
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function apply(Builder $query): Builder
    {
        match ($this->chip) {
            'aktiv' => $query->where('status', 'aktiv'),
            'ruhend' => $query->where('status', 'ruhend'),
            'ausgetreten' => $query->where('status', 'ausgetreten'),
            'jugend' => $query->current()->youth(),
            'offen' => $query->current()->withOpenDues(),
            default => $query->current(),
        };

        // Every word has to match the first name, last name or email (case-insensitive), or be the member number.
        foreach (preg_split('/\s+/u', $this->search, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
            $query->where(function (Builder $q) use ($term): void {
                $q->where('firstname', 'like', "%{$term}%")
                    ->orWhere('lastname', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
                if (ctype_digit($term) && strlen($term) <= 9) {
                    $q->orWhere('member_number', (int) $term);
                }
            });
        }

        if ($this->feeCategory !== null) {
            $query->where('fee_category_id', $this->feeCategory);
        }

        if ($this->dueStatus !== null) {
            $query->whereHas('dues', function (Builder $dues): void {
                match ($this->dueStatus) {
                    'bezahlt' => $dues->where('status', 'bezahlt'),
                    'ausstehend' => $dues->where('status', 'ausstehend')
                        ->where(fn (Builder $q) => $q->whereNull('faellig_am')->orWhere('faellig_am', '>=', now()->toDateString())),
                    default => $dues->scopes(['overdue']),
                };
            });
        }

        return $query;
    }

    /** True when one of the "more filters" (fee category, due status) is set. */
    public function hasMoreFilters(): bool
    {
        return $this->feeCategory !== null || $this->dueStatus !== null;
    }

    /**
     * Query parameters of this filter for links (chips, pager), without defaults; $overrides replace single values.
     *
     * @param  array<string, string|int|null>  $overrides
     * @return array<string, string|int>
     */
    public function query(array $overrides = []): array
    {
        $params = array_merge([
            'filter' => $this->chip === self::DEFAULT ? null : $this->chip,
            'search' => $this->search,
            'fee_category' => $this->feeCategory,
            'zahlung' => $this->dueStatus,
        ], $overrides);

        if (($params['filter'] ?? null) === self::DEFAULT) {
            $params['filter'] = null;
        }

        return array_filter($params, fn ($value) => $value !== null && $value !== '');
    }

    private static function scalar(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
