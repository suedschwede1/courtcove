<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class StatisticsController extends Controller
{
    public function index(): View
    {
        $users = User::whereIn('status', ['enabled', 'assist', 'admin'])
            ->orderBy('alias')
            ->get(['uid', 'alias']);

        $bookings = Booking::whereIn('uid', $users->pluck('uid'))
            ->get();

        $stats = $users->map(function (User $user) use ($bookings): array {
            return $this->statsForUser($user, $bookings->where('uid', $user->uid));
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

        return view('admin.statistics.index', compact('stats', 'summary'));
    }

    /** @param Collection<int, Booking> $userBookings */
    private function statsForUser(User $user, Collection $userBookings): array
    {
        $active = $userBookings->where('status', '!=', 'cancelled');
        $cancelledCount = $userBookings->where('status', 'cancelled')->count();

        return [
            'uid' => $user->uid,
            'alias' => $user->alias,
            'total' => $active->count(),
            'single' => $active->where('quantity', 2)->count(),
            'double' => $active->where('quantity', 4)->count(),
            'lastMonth' => 0,
            'topCourt' => null,
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
