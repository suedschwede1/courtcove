<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Contracts\View\View;

class DashboardController
{
    public function index(): View
    {
        $activeCount = Member::query()->active()->count();

        $fiscalYears = FiscalYear::query()->orderBy('starts_on')->get();
        $currentFiscalYear = $fiscalYears->first(
            fn (FiscalYear $fiscalYear) => now()->between($fiscalYear->starts_on, $fiscalYear->ends_on),
        ) ?? $fiscalYears->last();

        $openDuesQuery = MemberDue::query()->whereDoesntHave('payments');
        $paidDuesQuery = MemberDue::query()->whereHas('payments');
        if ($currentFiscalYear) {
            $openDuesQuery->where('fiscal_year_id', $currentFiscalYear->id);
            $paidDuesQuery->where('fiscal_year_id', $currentFiscalYear->id);
        }
        $openDues = $openDuesQuery->get();
        $paidDues = $paidDuesQuery->get();

        return view('members::dashboard', [
            'activeCount' => $activeCount,
            'openDuesCount' => $openDues->count(),
            'openDuesSum' => $openDues->sum('amount'),
            'paidDuesCount' => $paidDues->count(),
            'paidDuesSum' => $paidDues->sum('amount'),
            'currentFiscalYear' => $currentFiscalYear,
            'ageStructure' => $this->ageStructure(),
        ]);
    }

    /**
     * Age distribution of active members, bucketed for the dashboard.
     *
     * @return array{buckets: array<string, int>, unknown: int}
     */
    private function ageStructure(): array
    {
        $buckets = [
            'up_to_18' => 0,
            '19_35' => 0,
            '36_50' => 0,
            '51_65' => 0,
            '66_plus' => 0,
        ];
        $unknown = 0;

        Member::query()->active()->get(['birthdate'])->each(function (Member $member) use (&$buckets, &$unknown): void {
            if (! $member->birthdate) {
                $unknown++;

                return;
            }

            $age = (int) $member->birthdate->age;
            match (true) {
                $age <= 18 => $buckets['up_to_18']++,
                $age <= 35 => $buckets['19_35']++,
                $age <= 50 => $buckets['36_50']++,
                $age <= 65 => $buckets['51_65']++,
                default => $buckets['66_plus']++,
            };
        });

        return ['buckets' => $buckets, 'unknown' => $unknown];
    }
}
