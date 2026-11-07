<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\MemberHousehold;
use Illuminate\Support\Facades\DB;

/**
 * "Durch Haushaltsbeitrag ersetzen" (decision 1): preview, then on confirmation one transaction with the
 * year row locked first that re-checks every own due under its row lock (race-safe), creates the
 * household due if it is missing (tariff rate), deletes only replaceable own dues and covers the
 * household's billable members. Paid or partly paid own dues are never changed.
 */
final class HouseholdDueReplacer
{
    public function __construct(private readonly HouseholdService $households) {}

    public function preview(MemberHousehold $household, FiscalYear $year): ReplacementPreview
    {
        $household->loadMissing('feeCategory');
        $memberIds = $household->billableMembers()->pluck('id');
        $ownDues = MemberDue::query()->with('member')->withExists('payments')
            ->whereIn('member_id', $memberIds)->where('fiscal_year_id', $year->id)->orderBy('member_id')->get();
        [$replaceable, $untouched] = $ownDues->partition(fn (MemberDue $due) => $due->isReplaceable());

        $existing = HouseholdDue::query()->where('household_id', $household->id)->where('fiscal_year_id', $year->id)->first();
        $tariff = $household->feeCategory;
        $amount = match (true) {
            $existing !== null => $existing->amount,
            $tariff !== null => FeeCategoryRate::query()->where('fee_category_id', $tariff->id)->where('fiscal_year_id', $year->id)->value('amount'),
            default => null,
        };

        $block = match (true) {
            $year->isClosed() => $year->lockedMessage(),
            $household->isDissolved() => __('members::admin.households.errors.dissolved'),
            $tariff === null || ! $tariff->isPerHousehold() => __('members::admin.households.error_no_tariff'),
            $existing === null && $tariff->isDeactivated() => __('members::admin.fee_categories.not_assignable', ['name' => $tariff->name]),
            $memberIds->count() < MemberHousehold::MIN_BILLABLE_MEMBERS => __('members::admin.households.error_too_small'),
            $amount === null => __('members::admin.households.replace.no_rate', ['year' => $year->name]),
            $replaceable->isEmpty() => __('members::admin.households.replace.nothing'),
            default => null,
        };

        return new ReplacementPreview($replaceable->values(), $untouched->values(), $existing, $amount !== null ? (string) $amount : null, $block);
    }

    /**
     * @return int number of own dues replaced
     *
     * @throws HouseholdRuleException when the preview (re-built under the locks) says it is not possible
     */
    public function replace(MemberHousehold $household, FiscalYear $year): int
    {
        return DB::transaction(function () use ($household, $year): int {
            // Year row first (lock order of the dues code), then household, then the own due rows.
            $closed = FiscalYear::lockForWrite($year->id);
            if ($closed !== null) {
                throw new HouseholdRuleException($closed->lockedMessage());
            }
            $household = MemberHousehold::query()->lockForUpdate()->findOrFail($household->id);
            $year = FiscalYear::query()->findOrFail($year->id);

            $preview = $this->preview($household, $year);
            if (! $preview->possible()) {
                throw new HouseholdRuleException($preview->blockReason ?? __('members::admin.households.replace.nothing'));
            }

            $due = $preview->existingDue ?? HouseholdDue::create([
                'household_id' => $household->id,
                'fiscal_year_id' => $year->id,
                'fee_category_id' => $household->fee_category_id,
                'amount' => $preview->amount,
                'status' => 'ausstehend',
                'faellig_am' => $year->faellig_am ?? $year->starts_on,
            ]);

            $replaced = 0;
            foreach ($preview->replaceable as $candidate) {
                // Re-check under the row lock: a payment or partial amount entered meanwhile keeps the due.
                $own = MemberDue::query()->lockForUpdate()->find($candidate->id);
                if ($own === null || ! $own->isReplaceable()) {
                    continue;
                }
                $own->delete();
                $replaced++;
            }

            $this->households->coverBillable($due);

            return $replaced;
        });
    }
}
