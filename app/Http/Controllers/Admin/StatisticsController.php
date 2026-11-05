<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Square;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Booking statistics per active member and for the whole club.
 *
 * Everything is aggregated in SQL (grouped by uid, conditional sums, date
 * ranges compared on the Y-m-d reservation date), so the page costs a fixed
 * number of queries and never hydrates booking or reservation models. Without
 * a submitted search only the summary is computed (one query); the member
 * table adds the users, the grouped court counts and their squares.
 */
final class StatisticsController extends Controller
{
    public function index(Request $request): View
    {
        $searched = $request->boolean('searched');
        $search = trim($request->string('search')->value());
        $periods = $this->periods();

        if ($searched) {
            [$stats, $summary] = $this->statsWithSummary($search, $periods);
        } else {
            $stats = null;
            $summary = $this->summaryOnly($search, $periods);
        }

        return view('admin.statistics.index', [
            'searched' => $searched,
            'search' => $search,
            'stats' => $stats,
            'summary' => $summary,
        ]);
    }

    /**
     * Calendar ranges as inclusive Y-m-d bounds.
     *
     * @return array{lastMonth: array{string, string}, thisMonth: array{string, string}, thisYear: array{string, string}}
     */
    private function periods(): array
    {
        $lastMonth = Carbon::now()->subMonthNoOverflow();

        return [
            'lastMonth' => [$lastMonth->copy()->startOfMonth()->toDateString(), $lastMonth->copy()->endOfMonth()->toDateString()],
            'thisMonth' => [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()],
            'thisYear' => [Carbon::now()->startOfYear()->toDateString(), Carbon::now()->endOfYear()->toDateString()],
        ];
    }

    /** @return Builder<User> */
    private function members(string $search): Builder
    {
        return User::query()->active()
            ->when($search !== '', fn ($q) => $q->where('alias', 'like', "%{$search}%"));
    }

    /**
     * @param  array{lastMonth: array{string, string}, thisMonth: array{string, string}, thisYear: array{string, string}}  $periods
     * @return array{Collection, array<string, mixed>}
     */
    private function statsWithSummary(string $search, array $periods): array
    {
        $users = $this->members($search)->orderBy('alias')->get(['uid', 'alias']);
        $aggregates = $this->perUserAggregates($search, $periods)->get()->keyBy('uid');
        $topCourts = $this->topCourts($search);

        $stats = $users->map(function (User $user) use ($aggregates, $topCourts): array {
            $row = $aggregates->get($user->uid);
            $total = (int) ($row->total_count ?? 0);
            $cancelled = (int) ($row->cancelled_count ?? 0);

            return [
                'uid' => $user->uid,
                'alias' => $user->alias,
                'total' => $total,
                'single' => (int) ($row->single_count ?? 0),
                'double' => (int) ($row->double_count ?? 0),
                'lastMonth' => (int) ($row->last_month_count ?? 0),
                'thisYear' => (int) ($row->this_year_count ?? 0),
                'topCourt' => $topCourts[$user->uid] ?? null,
                'cancelled' => $cancelled,
                'cancellationRate' => $this->cancellationRate($cancelled, $cancelled + $total),
            ];
        });

        $cancelled = $stats->sum('cancelled');
        $summary = [
            'total' => $stats->sum('total'),
            'single' => $stats->sum('single'),
            'double' => $stats->sum('double'),
            'lastMonth' => $stats->sum('lastMonth'),
            'thisYear' => $stats->sum('thisYear'),
            'membersThisYear' => $stats->filter(fn (array $row) => $row['thisYear'] > 0)->count(),
            'membersThisMonth' => $aggregates->filter(fn (object $row) => (int) $row->booked_this_month > 0)->count(),
            'cancellationRate' => $this->cancellationRate($cancelled, $cancelled + $stats->sum('total')),
        ];

        return [$stats, $summary];
    }

    /**
     * The club summary in one query, for the page without the member table.
     *
     * @param  array{lastMonth: array{string, string}, thisMonth: array{string, string}, thisYear: array{string, string}}  $periods
     * @return array<string, int|float>
     */
    private function summaryOnly(string $search, array $periods): array
    {
        $totals = DB::query()
            ->fromSub($this->perUserAggregates($search, $periods), 'per_user')
            ->selectRaw('COALESCE(SUM(total_count), 0) AS total_count')
            ->selectRaw('COALESCE(SUM(single_count), 0) AS single_count')
            ->selectRaw('COALESCE(SUM(double_count), 0) AS double_count')
            ->selectRaw('COALESCE(SUM(last_month_count), 0) AS last_month_count')
            ->selectRaw('COALESCE(SUM(this_year_count), 0) AS this_year_count')
            ->selectRaw('COALESCE(SUM(cancelled_count), 0) AS cancelled_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN this_year_count > 0 THEN 1 ELSE 0 END), 0) AS members_this_year')
            ->selectRaw('COALESCE(SUM(booked_this_month), 0) AS members_this_month')
            ->first();

        $total = (int) $totals->total_count;
        $cancelled = (int) $totals->cancelled_count;

        return [
            'total' => $total,
            'single' => (int) $totals->single_count,
            'double' => (int) $totals->double_count,
            'lastMonth' => (int) $totals->last_month_count,
            'thisYear' => (int) $totals->this_year_count,
            'membersThisYear' => (int) $totals->members_this_year,
            'membersThisMonth' => (int) $totals->members_this_month,
            'cancellationRate' => $this->cancellationRate($cancelled, $cancelled + $total),
        ];
    }

    /**
     * One row per member with bookings: active bookings in total, by player
     * count and by period (a booking counts once even with several
     * reservations in the period), cancelled bookings and whether the member
     * has an active booking this month.
     *
     * @param  array{lastMonth: array{string, string}, thisMonth: array{string, string}, thisYear: array{string, string}}  $periods
     */
    private function perUserAggregates(string $search, array $periods): QueryBuilder
    {
        $date = DB::getQueryGrammar()->wrap('date');
        $inPeriod = "MAX(CASE WHEN {$date} BETWEEN ? AND ? THEN 1 ELSE 0 END)";

        // Period flags per booking, read only from reservations inside the widest period.
        $flags = DB::table('bs_reservations')
            ->select('bid')
            ->selectRaw("{$inPeriod} AS in_last_month", $periods['lastMonth'])
            ->selectRaw("{$inPeriod} AS in_this_month", $periods['thisMonth'])
            ->selectRaw("{$inPeriod} AS in_this_year", $periods['thisYear'])
            ->whereBetween('date', [min($periods['lastMonth'][0], $periods['thisYear'][0]), $periods['thisYear'][1]])
            ->groupBy('bid');

        $active = Booking::ACTIVE_STATUSES;
        $isActive = 'b.status IN ('.implode(', ', array_fill(0, count($active), '?')).')';

        return DB::table('bs_bookings as b')
            ->leftJoinSub($flags, 'r', 'r.bid', '=', 'b.bid')
            ->whereIn('b.uid', $this->members($search)->select('uid'))
            ->groupBy('b.uid')
            ->select('b.uid')
            ->selectRaw("SUM(CASE WHEN {$isActive} THEN 1 ELSE 0 END) AS total_count", $active)
            ->selectRaw("SUM(CASE WHEN {$isActive} AND b.quantity = 2 THEN 1 ELSE 0 END) AS single_count", $active)
            ->selectRaw("SUM(CASE WHEN {$isActive} AND b.quantity = 4 THEN 1 ELSE 0 END) AS double_count", $active)
            ->selectRaw("SUM(CASE WHEN {$isActive} AND r.in_last_month = 1 THEN 1 ELSE 0 END) AS last_month_count", $active)
            ->selectRaw("SUM(CASE WHEN {$isActive} AND r.in_this_year = 1 THEN 1 ELSE 0 END) AS this_year_count", $active)
            ->selectRaw("MAX(CASE WHEN {$isActive} AND r.in_this_month = 1 THEN 1 ELSE 0 END) AS booked_this_month", $active)
            ->selectRaw('SUM(CASE WHEN b.status = ? THEN 1 ELSE 0 END) AS cancelled_count', ['cancelled']);
    }

    /**
     * Display name of each member's most booked court (active bookings). A tie
     * goes to the smaller sid; courts are visited in the order of their first
     * booking, as the former per-model loop did.
     *
     * @return array<int, string|null>
     */
    private function topCourts(string $search): array
    {
        $counts = DB::table('bs_bookings')
            ->select('uid', 'sid')
            ->selectRaw('COUNT(*) AS bookings')
            ->selectRaw('MIN(bid) AS first_bid')
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->whereIn('uid', $this->members($search)->select('uid'))
            ->groupBy('uid', 'sid')
            ->orderBy('uid')
            ->orderBy('first_bid')
            ->get();

        $squares = Square::with('meta')
            ->whereIn('sid', $counts->pluck('sid')->unique()->values())
            ->get()
            ->keyBy('sid');

        /** @var array<int, array{count: int, square: Square|null}> $best */
        $best = [];
        foreach ($counts as $row) {
            $uid = (int) $row->uid;
            $sid = (int) $row->sid;
            $count = (int) $row->bookings;
            $current = $best[$uid] ?? null;

            if ($current === null
                || $count > $current['count']
                || ($count === $current['count'] && $current['square'] !== null && $sid < $current['square']->sid)) {
                $best[$uid] = ['count' => $count, 'square' => $squares->get($sid)];
            }
        }

        return array_map(fn (array $court): ?string => $court['square']?->display_name, $best);
    }

    private function cancellationRate(int $cancelled, int $totalIncludingCancelled): float
    {
        return $totalIncludingCancelled > 0
            ? round($cancelled / $totalIncludingCancelled * 100, 1)
            : 0.0;
    }
}
