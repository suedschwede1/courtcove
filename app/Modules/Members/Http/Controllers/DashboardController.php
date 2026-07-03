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
        ]);
    }
}
