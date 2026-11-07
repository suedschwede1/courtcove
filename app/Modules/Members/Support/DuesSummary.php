<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Support\Collection;

/**
 * Dues figures of one fiscal year (or all years without one) with the "counted once" rule of the family
 * fee: member dues as before plus every household due once, never once per covered member.
 */
final class DuesSummary
{
    /**
     * @param  Collection<int, MemberDue>  $openMember
     * @param  Collection<int, HouseholdDue>  $openHousehold
     */
    private function __construct(
        private readonly Collection $openMember,
        private readonly Collection $openHousehold,
        private readonly int $paidCount,
        private readonly float $paidSum,
    ) {}

    public static function forFiscalYear(?FiscalYear $year): self
    {
        $scope = fn ($query) => $year !== null ? $query->where('fiscal_year_id', $year->id) : $query;

        $paidMember = $scope(MemberDue::query()->paid())->get(['amount']);
        $paidHousehold = $scope(HouseholdDue::query()->paid())->get(['amount']);

        return new self(
            $scope(MemberDue::query()->open())->with(['member', 'feeCategory'])->get(),
            $scope(HouseholdDue::query()->open())->with(['household.members', 'feeCategory'])->get(),
            $paidMember->count() + $paidHousehold->count(),
            (float) $paidMember->sum('amount') + (float) $paidHousehold->sum('amount'),
        );
    }

    public function openCount(): int
    {
        return $this->openMember->count() + $this->openHousehold->count();
    }

    public function openSum(): float
    {
        return (float) $this->openMember->sum('amount') + (float) $this->openHousehold->sum('amount');
    }

    public function openHouseholdCount(): int
    {
        return $this->openHousehold->count();
    }

    public function paidCount(): int
    {
        return $this->paidCount;
    }

    public function paidSum(): float
    {
        return $this->paidSum;
    }

    /**
     * The first $limit open dues by name: member rows link to the member, household rows to the household.
     *
     * @return Collection<int, array{label: string, url: string, amount: string, category: string, status: string, household: bool}>
     */
    public function openRows(int $limit): Collection
    {
        $members = $this->openMember->map(fn (MemberDue $due): array => [
            'label' => trim(($due->member->lastname ?? '').', '.($due->member->firstname ?? ''), ', '),
            'url' => route('members.edit', $due->member_id),
            'amount' => (string) $due->amount,
            'category' => $due->feeCategory->name ?? '—',
            'status' => $due->displayStatus(),
            'household' => false,
        ]);
        $households = $this->openHousehold->map(fn (HouseholdDue $due): array => [
            'label' => $due->household->label(),
            'url' => route('members.households.show', $due->household_id),
            'amount' => (string) $due->amount,
            'category' => $due->feeCategory->name ?? '—',
            'status' => $due->displayStatus(),
            'household' => true,
        ]);

        return $members->concat($households)
            ->sortBy(fn (array $row) => mb_strtolower($row['label']), SORT_NATURAL)
            ->take($limit)->values();
    }
}
