<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\MemberChange;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Tab "Protokoll": all changes to members, newest first, filterable by member name and period.
 */
class ChangeLogController
{
    public function index(Request $request): View
    {
        $filters = array_filter($request->validate([
            'search' => ['nullable', 'string', 'max:128'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]), fn ($value) => $value !== null && $value !== '');

        $query = MemberChange::query()->with(['member', 'adminUser'])->orderByDesc('created_at')->orderByDesc('id');

        if (isset($filters['search'])) {
            // Current names only: an anonymised member cannot be found by its old name.
            $search = '%'.$filters['search'].'%';
            $query->whereHas('member', fn (Builder $member) => $member
                ->where('firstname', 'like', $search)
                ->orWhere('lastname', 'like', $search));
        }
        if (isset($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return view('members::changes.index', [
            'changes' => $query->paginate(30)->withQueryString(),
            'filters' => $filters,
            'categoryNames' => FeeCategory::query()->pluck('name', 'id')->all(),
        ]);
    }
}
