<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LoginHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = LoginHistory::query()->with('user')->latest('created_at');

        if ($request->filled('user')) {
            $term = '%'.$request->string('user')->trim()->value().'%';
            $userIds = User::query()
                ->where(fn ($sub) => $sub->where('alias', 'like', $term)->orWhere('email', 'like', $term))
                ->pluck('uid');
            $query->whereIn('user_id', $userIds);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('country')) {
            $query->where('country_code', strtoupper($request->string('country')->trim()->value()));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date('date_from')->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date('date_to')->endOfDay());
        }

        return view('admin.login-history.index', [
            'history' => $query->paginate(25)->withQueryString(),
            'filters' => $request->only('user', 'status', 'country', 'date_from', 'date_to'),
        ]);
    }
}
