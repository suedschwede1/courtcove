<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CalendarController - displays the 3-day booking calendar (yesterday/today/tomorrow).
 *
 * Routes: GET /calendar
 * Auth: public view, auth required for booking actions only
 */
final class CalendarController extends Controller
{
    private const MAX_DAYS = 8;

    public function __construct(
        private readonly ReservationService $reservations,
    ) {}

    /**
     * Render the 3-day calendar view (yesterday, today, tomorrow) for all courts.
     *
     * @param  Request  $request  Accepts optional ?date=YYYY-MM-DD query parameter; defaults to today
     * @return View calendar.index with calendar reservation and event data
     */
    public function index(Request $request): View
    {
        Carbon::setLocale('de');

        $dateInput = (string) $request->input('date');
        $date = Carbon::today();
        foreach (['Y-m-d', 'd.m.Y'] as $format) {
            $parsed = rescue(fn () => Carbon::createFromFormat('!'.$format, $dateInput), null, false);
            if ($parsed instanceof Carbon) {
                $date = $parsed;
                break;
            }
        }

        // Render a generous window (yesterday, today, today+1 … today+6); the client
        // reveals as many day-columns as fit the viewport and hides the rest, so the
        // count adapts to screen width without a reload. Base 3 (indices 0–2) are
        // always shown; indices >= 3 are the optional extra days.
        $dates = [$date->copy()->subDay()];
        for ($offset = 0; $offset <= self::MAX_DAYS - 2; $offset++) {
            $dates[] = $date->copy()->addDays($offset);
        }

        $rangeStart = $dates[0]->copy()->startOfDay();
        $rangeEnd = end($dates)->copy()->endOfDay();

        $squares = Square::with(['meta' => fn ($query) => $query->where('key', 'alias')])
            ->orderBy('priority')
            ->orderBy('sid')
            ->get();
        $squareIds = $squares->pluck('sid')->values()->all();

        $calendarReservations = $this->reservations->getCalendarReservations($squareIds, $rangeStart, $rangeEnd);

        $reservationsByDate = [];
        $reservationsBySlot = [];
        foreach ($dates as $d) {
            $dateKey = $d->format('Y-m-d');
            $reservationsByDate[$dateKey] = $squares->mapWithKeys(
                fn (Square $square) => [$square->sid => collect()]
            )->all();
        }

        foreach ($calendarReservations as $reservation) {
            $dateKey = $reservation->date;
            $sid = $reservation->booking?->sid;
            if ($sid === null || ! isset($reservationsByDate[$dateKey][$sid])) {
                continue;
            }

            $reservationsByDate[$dateKey][$sid]->push($reservation);

            $startH = (int) substr((string) $reservation->time_start, 0, 2);
            $endH = (int) substr((string) $reservation->time_end, 0, 2);
            for ($slotH = $startH; $slotH < $endH; $slotH++) {
                $reservationsBySlot[$dateKey][$sid][$slotH] = $reservation;
            }
        }

        $events = Event::with(['meta' => fn ($query) => $query->where('key', 'name')])
            ->where('status', 'enabled')
            ->where('datetime_start', '<', $rangeEnd)
            ->where('datetime_end', '>', $rangeStart)
            ->get();

        $eventBlocks = [];
        $eventSkip = [];

        foreach ($events as $event) {
            if ($event->status !== 'enabled') {
                continue;
            }

            $eventName = $event->meta->first()?->value ?? 'Veranstaltung';
            $coveredSids = $event->sid === null ? $squareIds : [(int) $event->sid];
            $coveredIndices = array_values(array_filter(
                array_map(
                    static fn (int $sid): int|false => array_search($sid, $squareIds, true),
                    $coveredSids,
                ),
                static fn (int|false $index): bool => $index !== false,
            ));

            sort($coveredIndices);
            if ($coveredIndices === []) {
                continue;
            }

            $segments = [];
            $segmentStart = $coveredIndices[0];
            $segmentPrev = $coveredIndices[0];

            for ($i = 1, $count = count($coveredIndices); $i < $count; $i++) {
                $current = $coveredIndices[$i];
                if ($current === $segmentPrev + 1) {
                    $segmentPrev = $current;

                    continue;
                }

                $segments[] = [$segmentStart, $segmentPrev];
                $segmentStart = $current;
                $segmentPrev = $current;
            }
            $segments[] = [$segmentStart, $segmentPrev];

            foreach ($dates as $d) {
                $dateKey = $d->format('Y-m-d');
                $eventStart = $event->datetime_start;
                $eventEnd = $event->datetime_end;

                $firstHour = null;
                $rows = 0;
                for ($h = 8; $h <= 21; $h++) {
                    $slotStart = Carbon::parse($dateKey.' '.sprintf('%02d:00:00', $h));
                    $slotEnd = $slotStart->copy()->addHour();

                    if ($eventStart < $slotEnd && $eventEnd > $slotStart) {
                        $firstHour ??= $h;
                        $rows++;
                    }
                }

                if ($firstHour === null) {
                    continue;
                }

                foreach ($segments as [$startIndex, $endIndex]) {
                    $startSid = $squareIds[$startIndex];
                    $cols = $endIndex - $startIndex + 1;

                    $eventBlocks[$dateKey][$startSid][$firstHour] = [
                        'rows' => $rows,
                        'cols' => $cols,
                        'name' => $eventName,
                    ];

                    for ($h = $firstHour; $h < $firstHour + $rows; $h++) {
                        for ($index = $startIndex; $index <= $endIndex; $index++) {
                            $sid = $squareIds[$index];
                            if ($sid === $startSid && $h === $firstHour) {
                                continue;
                            }
                            $eventSkip[$dateKey][$sid][$h] = true;
                        }
                    }
                }
            }
        }

        $now         = Carbon::now();
        $dateLabels = [];
        foreach ($dates as $d) {
            $dateLabels[$d->format('Y-m-d')] = [
                'short' => $d->isoFormat('dddd'),
                'long'  => $d->isoFormat('D. MMMM YYYY'),
                'full'  => $d->isoFormat('dddd, D. MMMM YYYY'),
            ];
        }

        $authUser    = auth()->user();
        $authUserId  = $authUser?->uid;
        $isLoggedIn  = $authUser !== null;
        $isAdmin     = $isLoggedIn && $authUser->can('admin.booking');

        $adminUsers = $isAdmin
            ? User::whereNotIn('status', ['deleted', 'placeholder'])->orderBy('alias')->get(['uid', 'alias'])
            : collect();

        return view('calendar.index', [
            'date'               => $date,
            'dates'              => $dates,
            'squares'            => $squares,
            'reservationsByDate' => $reservationsByDate,
            'reservationsBySquare' => $reservationsByDate[$date->format('Y-m-d')] ?? [],
            'reservationsBySlot' => $reservationsBySlot,
            'events'             => $events,
            'eventBlocks'        => $eventBlocks,
            'eventSkip'          => $eventSkip,
            'adminUsers'         => $adminUsers,
            'authUser'           => $authUser,
            'authUserId'         => $authUserId,
            'isLoggedIn'         => $isLoggedIn,
            'isAdmin'            => $isAdmin,
            'now'                => $now,
            'today'              => $now->format('Y-m-d'),
            'dateLabels'         => $dateLabels,
        ]);
    }
}
