<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Models\Square;
use App\Services\BookingService;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'sid'        => ['required', 'integer', 'exists:bs_squares,sid'],
            'date'       => ['required', 'date_format:Y-m-d'],
            'time_start' => ['required', 'integer', 'min:0', 'max:23'],
        ]);

        $square = Square::findOrFail((int) $data['sid']);
        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $timeStart = (int) $data['time_start'];
        $timeEnd = $timeStart + 1;

        return view('bookings.create', compact('square', 'date', 'timeStart', 'timeEnd'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sid'           => ['required', 'integer', 'exists:bs_squares,sid'],
            'date'          => ['required', 'date'],
            'time_start'    => ['required'],
            'time_end'      => ['required'],
            'quantity'      => ['required', 'integer', 'in:2,4'],
            'player_name_2' => ['required_if:quantity,2,4', 'nullable', 'string', 'max:120'],
            'player_name_3' => ['required_if:quantity,4', 'nullable', 'string', 'max:120'],
            'player_name_4' => ['required_if:quantity,4', 'nullable', 'string', 'max:120'],
        ]);

        $square = Square::findOrFail((int) $data['sid']);
        $dateStart = Carbon::parse($data['date'])->startOfDay()
            ->addSeconds($this->parseTimeToSeconds((string) $data['time_start']));
        $dateEnd = Carbon::parse($data['date'])->startOfDay()
            ->addSeconds($this->parseTimeToSeconds((string) $data['time_end']));

        if ((int) $data['quantity'] === 2) {
            $data['player_name_3'] = null;
            $data['player_name_4'] = null;
        }

        $meta = $this->bookingService->buildPlayerMeta([
            2 => $data['player_name_2'] ?? null,
            3 => $data['player_name_3'] ?? null,
            4 => $data['player_name_4'] ?? null,
        ]);

        try {
            $this->bookingService->createSingle(
                auth()->user(),
                $square,
                (int) $data['quantity'],
                $dateStart,
                $dateEnd,
                meta: $meta,
            );
        } catch (BookingValidationException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('calendar.index', ['date' => Carbon::parse($data['date'])->format('Y-m-d')])
            ->with('success', 'Ihre Buchung wurde erfolgreich abgeschlossen!');
    }

    /**
     * AJAX player-name autocomplete: returns up to 10 active member aliases
     * matching the query (min 2 chars). Replaces the old all-names datalist dump.
     */
    public function players(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $aliases = User::query()
            ->where('status', '!=', 'deleted')
            ->where('alias', 'like', '%' . $q . '%')
            ->orderBy('alias')
            ->limit(10)
            ->pluck('alias')
            ->values();

        return response()->json($aliases);
    }

    private function parseTimeToSeconds(string $time): int
    {
        if (str_contains($time, ':')) {
            [$hours, $minutes] = explode(':', $time, 2);

            return (int) $hours * 3600 + (int) $minutes * 60;
        }

        return (int) $time;
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        $user = auth()->user();

        if ($booking->uid !== $user?->getAuthIdentifier() && !$user?->can('admin.booking')) {
            abort(403);
        }

        $this->bookingService->cancelSingle($booking);

        return redirect()->route('calendar.index')
            ->with('success', 'Buchung storniert.');
    }
}

