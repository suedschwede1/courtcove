<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use App\Modules\Members\Support\DuesSummary;
use App\Modules\Members\Support\MemberDepartureAccounts;
use Illuminate\Contracts\View\View;

class DashboardController
{
    public function index(): View
    {
        // Members linked to a placeholder account are left out of every figure (Member::scopeWithoutPlaceholderAccount).
        $activeCount = Member::query()->countsAsActiveMember()->count();
        $current = fn () => Member::query()->countsAsCurrentMember();

        // Same default as the dues list (year containing today, else latest with dues, else latest).
        $currentFiscalYear = FiscalYear::defaultFor(FiscalYear::query()->orderBy('starts_on')->get());

        // Member dues plus each household due once (never once per covered member).
        $dues = DuesSummary::forFiscalYear($currentFiscalYear);

        return view('members::dashboard', [
            'memberCount' => $current()->count(),
            'youthCount' => $current()->youth()->count(),
            'dormantCount' => Member::query()->where('status', 'ruhend')->withoutPlaceholderAccount()->count(),
            // Informational only: the link to a booking-system account is optional; a placeholder is no real account.
            'withoutAccountCount' => Member::query()->current()->withoutRealAccount()->count(),
            'departureOverview' => app(MemberDepartureAccounts::class)->overview(),
            'openDuesTop' => $dues->openRows(5),
            'recentChanges' => MemberChange::query()->with(['member', 'adminUser'])->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get(),
            'categoryNames' => FeeCategory::query()->pluck('name', 'id')->all(),
            'activeCount' => $activeCount,
            'openDuesCount' => $dues->openCount(),
            'openDuesSum' => $dues->openSum(),
            'openHouseholdDuesCount' => $dues->openHouseholdCount(),
            'paidDuesCount' => $dues->paidCount(),
            'paidDuesSum' => $dues->paidSum(),
            'currentFiscalYear' => $currentFiscalYear,
            'ageStructure' => $this->ageStructure(),
            'categoryBreakdown' => $this->categoryBreakdown(),
        ]);
    }

    /**
     * Count of active members per fee category, for the dashboard.
     *
     * @return array<string, int>
     */
    private function categoryBreakdown(): array
    {
        $breakdown = FeeCategory::query()->orderBy('name')->get()
            ->mapWithKeys(fn (FeeCategory $category) => [$category->name => 0])
            ->all();

        Member::query()->countsAsActiveMember()->with('feeCategory')->get()->each(function (Member $member) use (&$breakdown): void {
            $name = $member->feeCategory->name ?? __('members::admin.dashboard.no_category');
            $breakdown[$name] = ($breakdown[$name] ?? 0) + 1;
        });

        return $breakdown;
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

        Member::query()->countsAsActiveMember()->get(['birthdate'])->each(function (Member $member) use (&$buckets, &$unknown): void {
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
