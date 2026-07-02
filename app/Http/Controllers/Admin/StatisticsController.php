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

        $users = User::whereIn('status', ['enabled', 'assist', 'admin'])
            ->orderBy('alias')
            ->get(['uid', 'alias']);

        $bookings = Booking::with(['reservations', 'square'])
            ->whereIn('uid', $users->pluck('uid'))
            ->get();

        $lastMonthStart = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonthNoOverflow()->endOfMonth();

        $stats = $users->map(function (User $user) use ($bookings, $lastMonthStart, $lastMonthEnd): array {
            return $this->statsForUser($user, $bookings->where('uid', $user->uid), $lastMonthStart, $lastMonthEnd);
        });

        $summary = [
            'total' => $stats->sum('total'),
            'single' => $stats->sum('single'),
            'double' => $stats->sum('double'),
            'lastMonth' => $stats->sum('lastMonth'),
            'cancellationRate' => $this->cancellationRate(
                $stats->sum('cancelled'),
                $stats->sum('cancelled') + $stats->sum('total'),
            ),
        ];

        return view('admin.statistics.index', [
            'searched' => $searched,
            'stats' => $searched ? $stats : null,
            'summary' => $summary,
        ]);
    }

    /** @param Collection<int, Booking> $userBookings */
    private function statsForUser(User $user, Collection $userBookings, Carbon $lastMonthStart, Carbon $lastMonthEnd): array
    {
        $active = $userBookings->whereIn('status', Booking::ACTIVE_STATUSES);
        $cancelledCount = $userBookings->where('status', 'cancelled')->count();

        $lastMonthCount = $active->filter(function (Booking $booking) use ($lastMonthStart, $lastMonthEnd): bool {
            return $booking->reservations->contains(
                fn (Reservation $reservation) => Carbon::parse($reservation->date)->between($lastMonthStart, $lastMonthEnd),
            );
        })->count();

        $topCourt = null;
        $topCourtCount = -1;
        foreach ($active->groupBy('sid') as $sid => $group) {
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
            'topCourt' => $topCourt?->display_name,
            'cancelled' => $cancelledCount,
            'cancellationRate' => $this->cancellationRate($cancelledCount, $cancelledCount + $active->count()),
        ];
    }

    private function cancellationRate(int $cancelled, int $totalIncludingCancelled): float
    {
        return $totalIncludingCancelled > 0
            ? round($cancelled / $totalIncludingCancelled * 100, 1)
            : 0.0;
    }
}
