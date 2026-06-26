<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Square;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CalendarController — displays the daily booking calendar for all courts.
 *
 * Routes: GET /calendar
 * Auth: auth required
 */
final class CalendarController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
    ) {}

    /**
     * Render the daily calendar view for all courts.
     *
     * @param Request $request Accepts optional ?date=YYYY-MM-DD query parameter; defaults to today
     * @return View           calendar.index with $date, $squares, and $reservationsBySquare
     */
    public function index(Request $request): View
    {
        $date    = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();
        $squares = Square::orderBy('priority')->orderBy('sid')->get();

        $reservationsBySquare = $squares->mapWithKeys(
            fn(Square $square) => [
                $square->sid => $this->reservations->getInRangeBySquare(
                    $square,
                    $date->copy()->startOfDay(),
                    $date->copy()->endOfDay(),
                )->load('booking.user', 'booking.meta'),
            ]
        );

        return view('calendar.index', compact('date', 'squares', 'reservationsBySquare'));
    }
}
