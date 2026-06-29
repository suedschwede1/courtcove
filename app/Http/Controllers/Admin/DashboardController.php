<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function index(): View
    {
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $startOfLastWeek = Carbon::now()->subWeek()->startOfWeek();
        $endOfLastWeek = Carbon::now()->subWeek()->endOfWeek();

        // Buchungen heute – via Reservations (date = heute), nicht-storniert
        $bookingsToday = Booking::whereIn('status', Booking::ACTIVE_STATUSES)
            ->whereHas('reservations', fn ($q) => $q->where('date', $today->toDateString()))
            ->with([
                'square',
                'user',
                'reservations' => fn ($q) => $q->where('date', $today->toDateString()),
            ])
            ->get();

        $bookingsTodayCount = $bookingsToday->count();

        // Buchungen heute gruppiert nach Platz-Name
        $bookingsTodayBySquare = $bookingsToday
            ->groupBy(fn (Booking $b) => $b->square?->display_name ?? $b->square?->name ?? '—')
            ->map(fn (Collection $group) => $group->count())
            ->all();

        // Aktive Mitglieder
        $activeMembersCount = User::whereIn('status', ['enabled', 'assist', 'admin'])->count();
        $adminCount = User::where('status', 'admin')->count();

        // Buchungen diese Woche und letzte Woche (via Reservations)
        $bookingsThisWeek = Booking::whereIn('status', Booking::ACTIVE_STATUSES)
            ->whereHas('reservations', fn ($q) => $q->whereBetween('date', [
                $startOfWeek->toDateString(),
                $endOfWeek->toDateString(),
            ]))
            ->count();

        $bookingsLastWeek = Booking::whereIn('status', Booking::ACTIVE_STATUSES)
            ->whereHas('reservations', fn ($q) => $q->whereBetween('date', [
                $startOfLastWeek->toDateString(),
                $endOfLastWeek->toDateString(),
            ]))
            ->count();

        // Veranstaltungen
        $now = Carbon::now();
        $upcomingEventsCount = Event::where('status', 'enabled')
            ->where('datetime_end', '>=', $now)
            ->count();

        $nextEvent = Event::where('status', 'enabled')
            ->where('datetime_end', '>=', $now)
            ->orderBy('datetime_start')
            ->first();

        return view('admin.dashboard', compact(
            'bookingsToday',
            'bookingsTodayCount',
            'bookingsTodayBySquare',
            'activeMembersCount',
            'adminCount',
            'bookingsThisWeek',
            'bookingsLastWeek',
            'upcomingEventsCount',
            'nextEvent',
        ));
    }
}
