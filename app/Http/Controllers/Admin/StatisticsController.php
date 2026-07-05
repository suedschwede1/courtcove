<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class StatisticsController extends Controller
{
    public function index(Request $request): View
    {
        $searched = $request->boolean('searched');
        $search = trim($request->string('search')->value());

        $users = User::whereIn('status', ['enabled', 'assist', 'admin'])
            ->when($search !== '', fn ($q) => $q->where('alias', 'like', "%{$search}%"))
            ->orderBy('alias')
            ->get(['uid', 'alias']);

        $bookings = Booking::with(['reservations', 'square'])
            ->whereIn('uid', $users->pluck('uid'))
            ->get();

        $lastMonthStart = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonthNoOverflow()->endOfMonth();
        $thisMonthStart = Carbon::now()->startOfMonth();
        $thisMonthEnd = Carbon::now()->endOfMonth();
        $thisYearStart = Carbon::now()->startOfYear();
        $thisYearEnd = Carbon::now()->endOfYear();

        $stats = $users->map(function (User $user) use ($bookings, $lastMonthStart, $lastMonthEnd, $thisYearStart, $thisYearEnd): array {
            return $this->statsForUser($user, $bookings->where('uid', $user->uid), $lastMonthStart, $lastMonthEnd, $thisYearStart, $thisYearEnd);
        });

        $summary = [
            'total' => $stats->sum('total'),
            'single' => $stats->sum('single'),
            'double' => $stats->sum('double'),
            'lastMonth' => $stats->sum('lastMonth'),
            'thisYear' => $stats->sum('thisYear'),
            'membersThisYear' => $stats->filter(fn (array $row) => $row['thisYear'] > 0)->count(),
            'membersThisMonth' => $stats->filter(fn (array $row) => $this->hasBookingBetween($bookings->where('uid', $row['uid']), $thisMonthStart, $thisMonthEnd))->count(),
            'cancellationRate' => $this->cancellationRate(
                $stats->sum('cancelled'),
                $stats->sum('cancelled') + $stats->sum('total'),
            ),
        ];

        return view('admin.statistics.index', [
            'searched' => $searched,
            'search' => $search,
            'stats' => $searched ? $stats : null,
            'summary' => $summary,
        ]);
    }

    /** @param Collection<int, Booking> $userBookings */
    private function statsForUser(User $user, Collection $userBookings, Carbon $lastMonthStart, Carbon $lastMonthEnd, Carbon $thisYearStart, Carbon $thisYearEnd): array
    {
        $active = $userBookings->whereIn('status', Booking::ACTIVE_STATUSES);
        $cancelledCount = $userBookings->where('status', 'cancelled')->count();

        $lastMonthCount = $active->filter(function (Booking $booking) use ($lastMonthStart, $lastMonthEnd): bool {
            return $booking->reservations->contains(
                fn (Reservation $reservation) => Carbon::parse($reservation->date)->between($lastMonthStart, $lastMonthEnd),
            );
        })->count();

        $thisYearCount = $active->filter(function (Booking $booking) use ($thisYearStart, $thisYearEnd): bool {
            return $booking->reservations->contains(
                fn (Reservation $reservation) => Carbon::parse($reservation->date)->between($thisYearStart, $thisYearEnd),
            );
        })->count();

        $topCourt = null;
        $topCourtCount = -1;
        foreach ($active->groupBy('sid') as $sid => $group) {
            $sid = (int) $sid;
            $count = $group->count();
            if ($count > $topCourtCount || ($count === $topCourtCount && $sid < $topCourt?->sid)) {
                $topCourtCount = $count;
                $topCourt = $group->first()->square;
            }
        }

        return [
            'uid' => $user->uid,
            'alias' => $user->alias,
            'total' => $active->count(),
            'single' => $active->where('quantity', 2)->count(),
            'double' => $active->where('quantity', 4)->count(),
            'lastMonth' => $lastMonthCount,
            'thisYear' => $thisYearCount,
            'topCourt' => $topCourt?->display_name,
            'cancelled' => $cancelledCount,
            'cancellationRate' => $this->cancellationRate($cancelledCount, $cancelledCount + $active->count()),
        ];
    }

    /** @param Collection<int, Booking> $userBookings */
    private function hasBookingBetween(Collection $userBookings, Carbon $start, Carbon $end): bool
    {
        return $userBookings->whereIn('status', Booking::ACTIVE_STATUSES)
            ->contains(fn (Booking $booking) => $booking->reservations->contains(
                fn (Reservation $reservation) => Carbon::parse($reservation->date)->between($start, $end),
            ));
    }

    private function cancellationRate(int $cancelled, int $totalIncludingCancelled): float
    {
        return $totalIncludingCancelled > 0
            ? round($cancelled / $totalIncludingCancelled * 100, 1)
            : 0.0;
    }
}
