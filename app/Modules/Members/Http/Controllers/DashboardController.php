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

        $currentFiscalYear = FiscalYear::query()->orderByDesc('ends_on')->first();

        $openDuesQuery = MemberDue::query()->whereDoesntHave('payments');
        if ($currentFiscalYear) {
            $openDuesQuery->where('fiscal_year_id', $currentFiscalYear->id);
        }
        $openDues = $openDuesQuery->get();

        $categoryCounts = $currentFiscalYear
            ? MemberDue::query()
                ->where('fiscal_year_id', $currentFiscalYear->id)
                ->with('feeCategory')
                ->get()
                ->groupBy(fn (MemberDue $due) => $due->feeCategory->name)
                ->map->count()
            : collect();

        return view('members::dashboard', [
            'activeCount' => $activeCount,
            'openDuesCount' => $openDues->count(),
            'openDuesSum' => $openDues->sum('amount'),
            'categoryCounts' => $categoryCounts,
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
