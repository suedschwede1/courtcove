<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\MemberHousehold;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates the missing dues of one fiscal year, in one transaction with the year row locked first:
 *
 * 1. Households with an active per-household tariff and at least MIN_BILLABLE_MEMBERS billable members
 *    (aktiv + ruhend): one household due (amount = the tariff's rate for the year), covering every billable
 *    member without an own due and without coverage that year. Members with an own due stay as they are
 *    and are reported (unpaid: replaceable on the household page; paid or partly paid: check by hand).
 *    Such a household "owns" its billable members: they never get an individual due from this run, even
 *    when the household due could not be created (missing rate, deactivated tariff), so a family is never
 *    switched to individual billing behind the admin's back. A household with fewer billable members is
 *    reported and its members are billed individually (decision 2).
 * 2. Individuals: aktiv members without own due, without coverage and not owned by such a household.
 *    Category = the latest own due's category, else the member's category (new members); per-household
 *    and deactivated categories are never used; members without category or rate are reported.
 */
final class DuesGenerator
{
    /** @var array<string, list<array{label: string, url: string}>> */
    private array $skipped = [];

    /** @throws ValidationException when the year is closed (checked under its row lock) */
    public function generate(FiscalYear $year): DuesGenerationReport
    {
        $this->skipped = array_fill_keys(DuesGenerationReport::REASONS, []);

        return DB::transaction(function () use ($year): DuesGenerationReport {
            FiscalYear::lockOpenOrFail($year->id, 'fiscal_year_id');

            $dueDate = $year->faellig_am ?? $year->starts_on;
            $rates = FeeCategoryRate::query()->where('fiscal_year_id', $year->id)->pluck('amount', 'fee_category_id');
            $ownDueMemberIds = MemberDue::query()->where('fiscal_year_id', $year->id)->pluck('member_id')->map(fn ($id) => (int) $id)->flip();

            [$createdHouseholds, $ownedMemberIds] = $this->households($year, $rates->all(), $ownDueMemberIds->all(), $dueDate);
            [$createdMembers, $fromMemberCategory] = $this->individuals($year, $rates->all(), $ownDueMemberIds->all(), $ownedMemberIds, $dueDate);

            // Anschlussmitglied problems are only reported; the dues above came from each member's own category.
            foreach (MemberConnectionWarnings::all() as ['member' => $member, 'warnings' => $warnings]) {
                $this->skipped['connection_warning'][] = ['label' => $member->fullName().': '.implode(' ', $warnings), 'url' => route('members.edit', $member)];
            }

            return new DuesGenerationReport($year->name, $createdMembers, $createdHouseholds, $fromMemberCategory, $this->skipped);
        });
    }

    /**
     * @param  array<int|string, string>  $rates  fee category id => amount
     * @param  array<int, int>  $ownDueMemberIds  member id => index (lookup set)
     * @return array{int, array<int, true>} created household dues, ids of members owned by a billing household
     */
    private function households(FiscalYear $year, array $rates, array $ownDueMemberIds, mixed $dueDate): array
    {
        $created = 0;
        $owned = [];
        $coveredIds = $this->coveredMemberIds($year);

        $households = MemberHousehold::query()->current()
            ->whereHas('feeCategory', fn ($q) => $q->where('billing_unit', 'household'))
            ->with(['feeCategory', 'members' => fn ($q) => $q->orderBy('lastname')->orderBy('firstname')])
            ->orderBy('id')
            ->get();

        foreach ($households as $household) {
            $billable = $household->members->filter(fn (Member $m) => in_array($m->status, MemberHousehold::BILLABLE_STATUSES, true) && $m->anonymized_at === null);
            $link = ['label' => $household->label(), 'url' => route('members.households.show', $household->id)];

            if ($billable->count() < MemberHousehold::MIN_BILLABLE_MEMBERS) {
                $this->skipped['household_too_small'][] = $link;

                continue;
            }

            foreach ($billable as $member) {
                $owned[$member->id] = true;
            }

            /** @var FeeCategory $tariff */
            $tariff = $household->feeCategory;
            if ($tariff->isDeactivated()) {
                $this->skipped['household_deactivated'][] = $link;

                continue;
            }

            $due = HouseholdDue::query()->where('household_id', $household->id)->where('fiscal_year_id', $year->id)->first();
            $coverable = $billable->filter(fn (Member $m) => ! isset($ownDueMemberIds[$m->id]) && ! isset($coveredIds[$m->id]));

            foreach ($billable->filter(fn (Member $m) => isset($ownDueMemberIds[$m->id])) as $member) {
                $ownDue = MemberDue::query()->where('member_id', $member->id)->where('fiscal_year_id', $year->id)->first();
                $reason = $ownDue?->isReplaceable() ? 'own_due_replaceable' : 'own_due_paid';
                $this->skipped[$reason][] = ['label' => $member->fullName(), 'url' => route('members.households.show', $household->id)];
            }

            if ($due === null) {
                if (! isset($rates[$tariff->id])) {
                    $this->skipped['household_no_rate'][] = $link;

                    continue;
                }
                // Only a due that covers somebody; with every member billed individually it would bill twice.
                if ($coverable->isEmpty()) {
                    continue;
                }

                $due = HouseholdDue::create([
                    'household_id' => $household->id,
                    'fiscal_year_id' => $year->id,
                    'fee_category_id' => $tariff->id,
                    'amount' => $rates[$tariff->id],
                    'status' => 'ausstehend',
                    'faellig_am' => $dueDate,
                ]);
                $created++;
            }

            foreach ($coverable as $member) {
                $due->coveredMembers()->attach($member->id, ['fiscal_year_id' => $year->id]);
                $coveredIds[$member->id] = true;
            }
        }

        return [$created, $owned];
    }

    /**
     * @param  array<int|string, string>  $rates
     * @param  array<int, int>  $ownDueMemberIds
     * @param  array<int, true>  $ownedMemberIds
     * @return array{int, int} created member dues, of which from the member's own category
     */
    private function individuals(FiscalYear $year, array $rates, array $ownDueMemberIds, array $ownedMemberIds, mixed $dueDate): array
    {
        $created = 0;
        $fromMemberCategory = 0;
        $coveredIds = $this->coveredMemberIds($year);
        $categories = FeeCategory::query()->get()->keyBy('id');

        Member::query()->active()->orderBy('id')->each(function (Member $member) use ($year, $rates, $ownDueMemberIds, $ownedMemberIds, $coveredIds, $categories, $dueDate, &$created, &$fromMemberCategory): void {
            if (isset($ownDueMemberIds[$member->id]) || isset($coveredIds[$member->id]) || isset($ownedMemberIds[$member->id])) {
                return;
            }

            $link = ['label' => $member->fullName(), 'url' => route('members.edit', $member)];
            // "Last year's category" assumes fiscal years are created in chronological order.
            $lastDue = $member->dues()->latest('fiscal_year_id')->first();
            $categoryId = $lastDue !== null ? $lastDue->fee_category_id : $member->fee_category_id;
            $category = $categoryId !== null ? $categories->get($categoryId) : null;

            if ($category === null || $category->isPerHousehold()) {
                $this->skipped['no_category'][] = $link;

                return;
            }
            if ($category->isDeactivated()) {
                $this->skipped['deactivated_category'][] = $link;

                return;
            }
            if (! isset($rates[$category->id])) {
                $this->skipped['no_rate'][] = $link;

                return;
            }

            MemberDue::create([
                'member_id' => $member->id,
                'fiscal_year_id' => $year->id,
                'fee_category_id' => $category->id,
                'amount' => $rates[$category->id],
                'status' => 'ausstehend',
                'faellig_am' => $dueDate,
            ]);
            $created++;
            if ($lastDue === null) {
                $fromMemberCategory++;
            }
        });

        return [$created, $fromMemberCategory];
    }

    /** @return array<int, bool> ids of members covered by any household due in the year */
    private function coveredMemberIds(FiscalYear $year): array
    {
        return DB::table('household_due_member')->where('fiscal_year_id', $year->id)->pluck('member_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }
}
