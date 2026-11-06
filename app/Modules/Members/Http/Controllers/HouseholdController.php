<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Support\HouseholdRuleException;
use App\Modules\Members\Support\HouseholdService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
            $household = $this->households->create([], isset($validated['fee_category_id']) ? (int) $validated['fee_category_id'] : null, $validated['name'] ?? null);
        } catch (HouseholdRuleException $e) {
            return back()->withInput()->withErrors(['fee_category_id' => $e->getMessage()]);
        }

        return redirect()->route('members.households.show', $household->id)->with('status', __('members::admin.households.created'));
    }

    public function show(int $household): View
    {
        $household = MemberHousehold::query()
            ->with([
                'members' => fn ($members) => $members->with('feeCategory')->orderBy('lastname')->orderBy('firstname'),
                'feeCategory',
                'dues' => fn ($dues) => $dues->with('fiscalYear')->withExists('payments'),
            ])
            ->findOrFail($household);
        $fiscalYear = $this->households->currentFiscalYear();
        $currentDue = $household->dues->firstWhere('fiscal_year_id', $fiscalYear->id ?? 0);
        $coveredIds = $currentDue?->coveredMembers()->pluck('members.id')->map(fn ($id) => (int) $id)->all() ?? [];

        // Picker: current (aktiv/ruhend, not anonymised) members outside this household, by name.
        $candidates = Member::query()
            ->with('household')
            ->whereIn('status', MemberHousehold::BILLABLE_STATUSES)
            ->whereNull('anonymized_at')
            ->where(fn ($q) => $q->whereNull('household_id')->orWhere('household_id', '!=', $household->id))
            ->orderBy('lastname')->orderBy('firstname')
            ->get();

        return view('members::households.show', [
            'household' => $household,
            'fiscalYear' => $fiscalYear,
            'currentDue' => $currentDue,
            'coveredIds' => $coveredIds,
            'candidates' => $candidates,
            'tariffs' => FeeCategory::query()->perHousehold()
                ->where(fn ($q) => $q->whereNull('deactivated_at')->orWhere('id', $household->fee_category_id))
                ->orderBy('name')->get(),
            'dues' => $household->dues->sortByDesc(fn (HouseholdDue $due) => $due->fiscalYear->starts_on->toDateString())->values(),
        ]);
    }

    public function update(Request $request, int $household): RedirectResponse
    {
        $household = MemberHousehold::query()->findOrFail($household);
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:128'],
            'fee_category_id' => ['bail', 'nullable', 'integer', self::tariffRule($household->fee_category_id)],
            'payer_member_id' => ['bail', 'nullable', 'integer'],
        ]);

        $payer = null;
        if (isset($validated['payer_member_id'])) {
            $payer = Member::query()->find((int) $validated['payer_member_id']);
            if ($payer === null || $payer->household_id !== $household->id) {
                return back()->withInput()->withErrors(['payer_member_id' => __('members::admin.households.errors.payer_not_member', ['name' => $payer?->fullName() ?? '–'])]);
            }
        }

        try {
            DB::transaction(function () use ($household, $validated, $payer): void {
                $this->households->updateDetails($household, $validated['name'] ?? null, isset($validated['fee_category_id']) ? (int) $validated['fee_category_id'] : null);
                $this->households->setPayer($household, $payer);
            });
        } catch (HouseholdRuleException $e) {
            return back()->withInput()->withErrors(['payer_member_id' => $e->getMessage()]);
        }

        return redirect()->route('members.households.show', $household->id)->with('status', __('members::admin.households.saved'));
    }

    public function addMember(Request $request, int $household): RedirectResponse
    {
        $household = MemberHousehold::query()->with('members')->findOrFail($household);
        $validated = $request->validate(['member_id' => ['required', 'integer']]);
        $member = Member::query()->find((int) $validated['member_id']);
        if ($member === null) {
            return back()->withErrors(['member_id' => __('members::admin.households.errors.member_not_found')]);
        }

        try {
            $this->households->addMember($household, $member);
        } catch (HouseholdRuleException $e) {
            return back()->withErrors(['member_id' => $e->getMessage()]);
        }

        $redirect = redirect()->route('members.households.show', $household->id)
            ->with('status', __('members::admin.households.member_added', ['name' => $member->fullName()]));

        // Decision 5: a different address never blocks, it only gets a neutral hint.
        return self::addressDiffers($member, $household->members)
            ? $redirect->with('household_hint', __('members::admin.households.address_differs_hint', ['name' => $member->fullName()]))
            : $redirect;
    }

    public function removeMember(int $household, int $member): RedirectResponse
    {
        $household = MemberHousehold::query()->findOrFail($household);
        $member = Member::query()->findOrFail($member);

        try {
            $this->households->removeMember($household, $member);
        } catch (HouseholdRuleException $e) {
            return back()->withErrors(['member_id' => $e->getMessage()]);
        }

        return redirect()->route('members.households.show', $household->id)
            ->with('status', __('members::admin.households.member_removed', ['name' => $member->fullName()]));
    }

    public function dissolve(int $household): RedirectResponse
    {
        $household = MemberHousehold::query()->findOrFail($household);
        $this->households->dissolve($household);

        return redirect()->route('members.households.index')->with('status', __('members::admin.households.dissolved'));
    }

    /**
     * True when the new member has an address (street + zip) and none of the other members lives there.
     *
     * @param  Collection<int, Member>  $others
     */
    private static function addressDiffers(Member $member, Collection $others): bool
    {
        $key = fn (Member $m) => mb_strtolower(trim((string) $m->street)).'|'.mb_strtolower(trim((string) $m->zip));
        $others = $others->reject(fn (Member $m) => $m->id === $member->id || trim((string) $m->street) === '');
        if (trim((string) $member->street) === '' || $others->isEmpty()) {
            return false;
        }

        return ! $others->contains(fn (Member $m) => $key($m) === $key($member));
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
