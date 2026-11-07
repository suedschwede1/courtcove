<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberHousehold;
use Illuminate\Support\Facades\DB;

/**
 * The single place for household writes. Rules:
 * - a member belongs to at most one household; departed or anonymised members cannot join;
 * - a dissolved household accepts no new members;
 * - the tariff is empty (just a grouping) or an active per-household fee category;
 * - the payer is a member of the household; cleared on leave, departure and anonymisation;
 * - a joiner is covered by the household due of the current fiscal year without surcharge when that
 *   due exists, the year is open, and the member has no own due and no coverage there (decision 6);
 * - coverage rows and household dues are never deleted here: coverage lasts until year end (decision 7).
 * Every change to a member's household is logged on that member (protocol).
 */
final class HouseholdService
{
    /**
     * @param  list<int>  $memberIds
     *
     * @throws HouseholdRuleException
     */
    public function create(array $memberIds, ?int $feeCategoryId, ?string $name): MemberHousehold
    {
        $this->assertTariff($feeCategoryId);

        return DB::transaction(function () use ($memberIds, $feeCategoryId, $name): MemberHousehold {
            $household = MemberHousehold::create([
                'name' => self::cleanName($name),
                'fee_category_id' => $feeCategoryId,
            ]);

            foreach ($memberIds as $memberId) {
                $member = Member::query()->find($memberId);
                if ($member === null) {
                    throw new HouseholdRuleException(__('members::admin.households.errors.member_not_found'));
                }
                $this->addMember($household, $member);
            }

            return $household;
        });
    }

    /**
     * Name and tariff of a household; the tariff follows the same rule as on create, except that a
     * now deactivated category may be kept.
     *
     * @throws HouseholdRuleException
     */
    public function updateDetails(MemberHousehold $household, ?string $name, ?int $feeCategoryId): void
    {
        $this->assertTariff($feeCategoryId, $household->fee_category_id);

        $household->update(['name' => self::cleanName($name), 'fee_category_id' => $feeCategoryId]);
    }

    /** @throws HouseholdRuleException */
    public function addMember(MemberHousehold $household, Member $member): void
    {
        DB::transaction(function () use ($household, $member): void {
            // Year row first (lock order of the dues code): a closed current year gets no new coverage.
            $year = $this->currentFiscalYear();
            $openYear = $year !== null && FiscalYear::lockForWrite($year->id) === null ? $year : null;
            $household = MemberHousehold::query()->lockForUpdate()->findOrFail($household->id);
            $locked = Member::query()->lockForUpdate()->findOrFail($member->id);

            if ($household->isDissolved()) {
                throw new HouseholdRuleException(__('members::admin.households.errors.dissolved'));
            }
            if ($locked->household_id === $household->id) {
                throw new HouseholdRuleException(__('members::admin.households.errors.already_member', ['name' => $locked->fullName()]));
            }
            if ($locked->household_id !== null) {
                throw new HouseholdRuleException(__('members::admin.households.errors.other_household', [
                    'name' => $locked->fullName(),
                    'household' => $locked->household?->label() ?? '',
                ]));
            }
            if (! in_array($locked->status, MemberHousehold::BILLABLE_STATUSES, true) || $locked->anonymized_at !== null) {
                throw new HouseholdRuleException(__('members::admin.households.errors.not_billable', ['name' => $locked->fullName()]));
            }
            if ($household->feeCategory !== null && ! $household->feeCategory->isPerHousehold()) {
                throw new HouseholdRuleException(__('members::admin.households.errors.category_not_per_household'));
            }

            $before = MemberChangeLogger::snapshot($locked);
            $locked->forceFill(['household_id' => $household->id])->save();
            MemberChangeLogger::log($locked, 'updated', $before);

            $this->coverJoiner($household, $locked, $openYear);
        });
    }

    /** Removes the member; the payer is cleared if it is this member. Coverage stays until year end. */
    public function removeMember(MemberHousehold $household, Member $member): void
    {
        DB::transaction(function () use ($household, $member): void {
            $household = MemberHousehold::query()->lockForUpdate()->findOrFail($household->id);
            $locked = Member::query()->lockForUpdate()->findOrFail($member->id);

            if ($locked->household_id !== $household->id) {
                throw new HouseholdRuleException(__('members::admin.households.errors.not_member', ['name' => $locked->fullName()]));
            }

            if ($household->payer_member_id === $locked->id) {
                $this->changePayer($household, null);
            }

            $before = MemberChangeLogger::snapshot($locked);
            $locked->forceFill(['household_id' => null])->save();
            MemberChangeLogger::log($locked, 'updated', $before);
        });
    }

    /**
     * Sets or clears the payer; the payer must be a member of this household.
     *
     * @throws HouseholdRuleException
     */
    public function setPayer(MemberHousehold $household, ?Member $payer): void
    {
        DB::transaction(function () use ($household, $payer): void {
            $household = MemberHousehold::query()->lockForUpdate()->findOrFail($household->id);

            if ($payer !== null) {
                $payer = Member::query()->findOrFail($payer->id);
                if ($payer->household_id !== $household->id) {
                    throw new HouseholdRuleException(__('members::admin.households.errors.payer_not_member', ['name' => $payer->fullName()]));
                }
            }

            $this->changePayer($household, $payer);
        });
    }

    /** All members leave, the payer is cleared, dissolved_at is set; household dues and coverage stay. */
    public function dissolve(MemberHousehold $household): void
    {
        DB::transaction(function () use ($household): void {
            $household = MemberHousehold::query()->lockForUpdate()->findOrFail($household->id);
            if ($household->isDissolved()) {
                return;
            }

            if ($household->payer_member_id !== null) {
                $this->changePayer($household, null);
            }

            foreach ($household->members()->lockForUpdate()->get() as $member) {
                $before = MemberChangeLogger::snapshot($member);
                $member->forceFill(['household_id' => null])->save();
                MemberChangeLogger::log($member, 'updated', $before);
            }

            $household->forceFill(['dissolved_at' => now()])->save();
        });
    }

    /** A departed member no longer pays for a household (the membership itself stays until removed). */
    public function memberDeparted(Member $member): void
    {
        DB::transaction(function () use ($member): void {
            $households = MemberHousehold::query()->where('payer_member_id', $member->id)->lockForUpdate()->get();
            foreach ($households as $household) {
                $this->changePayer($household, null);
            }
        });
    }

    /**
     * Called inside the anonymise transaction before the 'anonymized' snapshot is logged, so that
     * snapshot records the household change: household_id and payer are cleared; a household left
     * without members loses its name (no surname survives) and is dissolved.
     */
    public function detachForAnonymisation(Member $member): void
    {
        DB::transaction(function () use ($member): void {
            $householdId = $member->household_id;
            MemberHousehold::query()->where('payer_member_id', $member->id)->update(['payer_member_id' => null]);
            if ($householdId === null) {
                return;
            }

            $member->forceFill(['household_id' => null])->save();

            $household = MemberHousehold::query()->lockForUpdate()->find($householdId);
            if ($household !== null && ! $household->members()->exists()) {
                $household->forceFill(['name' => null, 'dissolved_at' => $household->dissolved_at ?? now()])->save();
            }
        });
    }

    /**
     * Ids of the years after the due's own year up to and including $target ("Bezahlt bis").
     *
     * @return list<int>
     */
    public static function yearIdsUpTo(HouseholdDue $due, FiscalYear $target): array
    {
        return FiscalYear::query()
            ->where('starts_on', '>', $due->fiscalYear->starts_on)
            ->where('starts_on', '<=', $target->starts_on)
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Paid in advance up to $target: the due and every household due of the years in between become
     * paid; a missing one is created (same tariff and amount) covering the then-billable members without
     * own due or coverage. A dissolved household gets no new dues. Call inside a transaction that holds
     * the locks of the due's year and of every year up to $target.
     */
    public function payThrough(HouseholdDue $due, FiscalYear $target, string $paidOn): void
    {
        $household = $due->household;
        $intervening = FiscalYear::query()
            ->where('starts_on', '>', $due->fiscalYear->starts_on)
            ->where('starts_on', '<=', $target->starts_on)
            ->orderBy('starts_on')->get();

        foreach ($intervening as $year) {
            $existing = HouseholdDue::query()->where('household_id', $household->id)->where('fiscal_year_id', $year->id)->first();
            if ($existing !== null) {
                $existing->update([
                    'status' => 'bezahlt',
                    'bezahlt_bis_fiscal_year_id' => $target->id,
                    'bezahlt_am' => $existing->bezahlt_am ?? $paidOn,
                ]);

                continue;
            }
            if ($household->isDissolved()) {
                continue;
            }

            $created = HouseholdDue::create([
                'household_id' => $household->id,
                'fiscal_year_id' => $year->id,
                'fee_category_id' => $due->fee_category_id,
                'amount' => $due->amount,
                'status' => 'bezahlt',
                'bezahlt_bis_fiscal_year_id' => $target->id,
                'faellig_am' => $year->faellig_am ?? $year->starts_on,
                'bezahlt_am' => $paidOn,
            ]);
            $this->coverBillable($created);
        }

        $due->update([
            'status' => 'bezahlt',
            'bezahlt_bis_fiscal_year_id' => $target->id,
            'bezahlt_am' => $due->bezahlt_am ?? $paidOn,
        ]);
    }

    /** The fiscal year containing today, else the last one (same rule as the overview). */
    public function currentFiscalYear(): ?FiscalYear
    {
        $years = FiscalYear::query()->orderBy('starts_on')->get();

        return $years->first(fn (FiscalYear $year) => $year->covers()) ?? $years->last();
    }

    /**
     * Covers a joiner by the current year's household due (decision 6). Never in a closed year, never
     * on top of an own due or an existing coverage in that year.
     */
    private function coverJoiner(MemberHousehold $household, Member $member, ?FiscalYear $year): void
    {
        if ($year === null) {
            return;
        }

        $due = $household->dues()->where('fiscal_year_id', $year->id)->first();
        if ($due === null) {
            return;
        }

        $this->coverIfFree($due, $member, $year);
    }

    /**
     * Covers every billable member of the due's household that has no own due and no coverage in the
     * due's year (call inside a transaction that holds the year-row lock). Returns how many were added.
     */
    public function coverBillable(HouseholdDue $due): int
    {
        $year = $due->fiscalYear;
        $added = 0;
        foreach ($due->household->billableMembers()->get() as $member) {
            $added += $this->coverIfFree($due, $member, $year) ? 1 : 0;
        }

        return $added;
    }

    /** Billable members of the household (aktiv/ruhend, not anonymised) that the due could still cover. */
    public static function coverableCount(MemberHousehold $household, FiscalYear $year): int
    {
        return $household->billableMembers()
            ->whereDoesntHave('dues', fn ($q) => $q->where('fiscal_year_id', $year->id))
            ->whereDoesntHave('coveringHouseholdDues', fn ($q) => $q->where('household_due_member.fiscal_year_id', $year->id))
            ->count();
    }

    private function coverIfFree(HouseholdDue $due, Member $member, FiscalYear $year): bool
    {
        $hasOwnDue = $member->dues()->where('fiscal_year_id', $year->id)->exists();
        $isCovered = $member->coveringHouseholdDues()->wherePivot('fiscal_year_id', $year->id)->exists();
        if ($hasOwnDue || $isCovered) {
            return false;
        }

        $due->coveredMembers()->attach($member->id, ['fiscal_year_id' => $year->id]);

        return true;
    }

    /** Writes the new payer and logs 'household_payer' on the old and on the new payer. */
    private function changePayer(MemberHousehold $household, ?Member $payer): void
    {
        $oldId = $household->payer_member_id;
        $newId = $payer?->id;
        if ($oldId === $newId) {
            return;
        }

        $household->forceFill(['payer_member_id' => $newId])->save();

        $old = $oldId !== null ? Member::query()->find($oldId) : null;
        if ($old !== null) {
            MemberChangeLogger::log($old, 'household_payer',
                [...MemberChangeLogger::snapshot($old), 'household_payer' => $household->id],
                ['household_payer' => null]);
        }
        if ($payer !== null) {
            MemberChangeLogger::log($payer, 'household_payer',
                [...MemberChangeLogger::snapshot($payer), 'household_payer' => null],
                ['household_payer' => $household->id]);
        }
    }

    /** @throws HouseholdRuleException */
    private function assertTariff(?int $feeCategoryId, ?int $keepId = null): void
    {
        if ($feeCategoryId === null) {
            return;
        }

        $category = FeeCategory::query()->find($feeCategoryId);
        if ($category === null || ! $category->isPerHousehold()) {
            throw new HouseholdRuleException(__('members::admin.households.errors.category_not_per_household'));
        }
        if ($category->isDeactivated() && $category->id !== $keepId) {
            throw new HouseholdRuleException(__('members::admin.fee_categories.not_assignable', ['name' => $category->name]));
        }
    }

    private static function cleanName(?string $name): ?string
    {
        $name = $name !== null ? trim($name) : null;

        return $name === '' ? null : $name;
    }
}
