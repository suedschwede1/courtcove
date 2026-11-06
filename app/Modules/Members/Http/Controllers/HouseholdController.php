<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Support\HouseholdRuleException;
use App\Modules\Members\Support\HouseholdService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Haushalte (family fee): list, create and edit households. Every write goes through HouseholdService;
 * the whole controller sits behind the admin.members gate of the module routes.
 */
class HouseholdController
{
    private const PER_PAGE = 25;

    private const SEARCH_MAX = 100;

    public function __construct(private readonly HouseholdService $households) {}

    public function index(Request $request): View
    {
        $search = mb_substr(trim(self::scalar($request, 'search')), 0, self::SEARCH_MAX);
        $status = self::scalar($request, 'status') === 'aufgeloest' ? 'aufgeloest' : 'aktiv';
        $fiscalYear = $this->households->currentFiscalYear();

        $query = MemberHousehold::query()
            ->with([
                'members' => fn ($members) => $members->orderBy('lastname')->orderBy('firstname'),
                'feeCategory',
                'dues' => fn ($dues) => $dues->where('fiscal_year_id', $fiscalYear->id ?? 0)->withExists('payments'),
            ]);
        $status === 'aufgeloest' ? $query->whereNotNull('dissolved_at') : $query->whereNull('dissolved_at');

        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhereHas('members', fn (Builder $m) => $m->where('firstname', 'like', $term)->orWhere('lastname', 'like', $term));
            });
        }

        // Households are few and their label is computed (name or last names), so sort in PHP.
        $all = $query->get()->sortBy(fn (MemberHousehold $household) => mb_strtolower($household->label()), SORT_NATURAL)->values();
        $page = max(1, (int) self::scalar($request, 'page'));
        $households = new LengthAwarePaginator(
            $all->forPage($page, self::PER_PAGE)->values(),
            $all->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => array_filter(['search' => $search, 'status' => $status === 'aktiv' ? null : $status])],
        );

        return view('members::households.index', [
            'households' => $households,
            'search' => $search,
            'status' => $status,
            'fiscalYear' => $fiscalYear,
            'creating' => $request->boolean('anlegen'),
            'tariffs' => FeeCategory::query()->perHousehold()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:128'],
            'fee_category_id' => ['bail', 'nullable', 'integer', self::tariffRule()],
        ]);

        try {
            $this->households->create([], isset($validated['fee_category_id']) ? (int) $validated['fee_category_id'] : null, $validated['name'] ?? null);
        } catch (HouseholdRuleException $e) {
            return back()->withInput()->withErrors(['fee_category_id' => $e->getMessage()]);
        }

        return redirect()->route('members.households.index')->with('status', __('members::admin.households.created'));
    }

    /**
     * The tariff of a household: an existing per-household category that is active; a deactivated one
     * only when it equals $keepId (the household keeps the tariff it already has).
     */
    public static function tariffRule(?int $keepId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($keepId): void {
            $category = FeeCategory::query()->find((int) $value);
            if ($category === null || ! $category->isPerHousehold()) {
                $fail(__('members::admin.households.errors.category_not_per_household'));

                return;
            }
            if ($category->isDeactivated() && $category->id !== $keepId) {
                $fail(__('members::admin.fee_categories.not_assignable', ['name' => $category->name]));
            }
        };
    }

    /** A query value as string; array-shaped input counts as empty. */
    private static function scalar(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }
}
