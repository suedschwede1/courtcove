<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LoginHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $history = $this->filteredQuery($request)
            ->with('user')
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.login-history.index', [
            'history' => $history,
            'filters' => $request->only('user', 'status', 'country', 'date_from', 'date_to'),
            'statusCounts' => $this->filteredQuery($request, except: ['status'])
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'countryCounts' => $this->filteredQuery($request, except: ['country'])
                ->whereNotNull('country_code')
                ->selectRaw('country_code, count(*) as total')
                ->groupBy('country_code')
                ->orderByDesc('total')
                ->limit(10)
                ->pluck('total', 'country_code'),
            'todayCount' => $this->filteredQuery($request, except: ['date_from', 'date_to'])
                ->where('created_at', '>=', now()->startOfDay())
                ->count(),
            'monthCount' => $this->filteredQuery($request, except: ['date_from', 'date_to'])
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ]);
    }

    /** @param  array<int, string>  $except  filter keys to skip, e.g. when computing that filter's own breakdown */
    private function filteredQuery(Request $request, array $except = []): Builder
    {
        $query = LoginHistory::query();

        if (! in_array('user', $except, true) && $request->filled('user')) {
            $term = '%'.$request->string('user')->trim()->value().'%';
            $userIds = User::query()
                ->where(fn ($sub) => $sub->where('alias', 'like', $term)->orWhere('email', 'like', $term))
                ->pluck('uid');
            $query->whereIn('user_id', $userIds);
        }

        if (! in_array('status', $except, true) && $request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if (! in_array('country', $except, true) && $request->filled('country')) {
            $query->where('country_code', strtoupper($request->string('country')->trim()->value()));
        }

        if (! in_array('date_from', $except, true) && $request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date('date_from')->startOfDay());
        }

        if (! in_array('date_to', $except, true) && $request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date('date_to')->endOfDay());
        }

        return $query;
    }
}
