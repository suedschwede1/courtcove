<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Services\Notifications\WhatsAppAdminStatus;
use App\Services\Notifications\WhatsAppSettings;
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

        // Bookings today – via reservations (date = today), non-cancelled
        $bookingsToday = Booking::whereIn('status', Booking::ACTIVE_STATUSES)
            ->whereHas('reservations', fn ($q) => $q->where('date', $today->toDateString()))
            ->with([
                'square',
                'user',
                'meta',
                'reservations' => fn ($q) => $q->where('date', $today->toDateString()),
            ])
            ->get();

        $bookingsTodayCount = $bookingsToday->count();

        // Bookings today grouped by court name
        $bookingsTodayBySquare = $bookingsToday
            ->groupBy(fn (Booking $b) => $b->square->display_name ?? $b->square->name ?? '—')
            ->map(fn (Collection $group) => $group->count())
            ->all();

        // Active (enabled) booking accounts, shown as "Aktive Benutzerkonten"
        $activeMembersCount = User::query()->active()->count();
        $adminCount = User::query()->withRole('admin')->count();

        // Bookings this week and last week (via reservations)
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

        // Events
        $now = Carbon::now();
        $upcomingEventsCount = Event::where('status', 'enabled')
            ->where('datetime_end', '>=', $now)
            ->count();

        $nextEvent = Event::where('status', 'enabled')
            ->where('datetime_end', '>=', $now)
            ->orderBy('datetime_start')
            ->first();

        // Budget and pause hint of the WhatsApp channel, only for admins who manage it and only while it is on.
        $whatsappNotices = null;
        if (auth()->user()?->can('admin.config') && app(WhatsAppSettings::class)->enabled()) {
            $whatsappNotices = app(WhatsAppAdminStatus::class)->notices();
            if ($whatsappNotices['state'] === null && ! $whatsappNotices['paused']) {
                $whatsappNotices = null;
            }
        }

        return view('admin.dashboard', compact(
            'whatsappNotices',
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
