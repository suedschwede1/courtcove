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
            'time_start'    => ['required', 'integer', 'min:0', 'max:86399'],
            'time_end'      => ['required', 'integer', 'min:1', 'max:86400'],
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

    public function players(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $aliases = User::query()
            ->select('bs_users.alias')
            ->leftJoin('bs_users_meta as firstnames', function ($join): void {
                $join->on('firstnames.uid', '=', 'bs_users.uid')
                    ->where('firstnames.key', '=', 'firstname');
            })
            ->leftJoin('bs_users_meta as lastnames', function ($join): void {
                $join->on('lastnames.uid', '=', 'bs_users.uid')
                    ->where('lastnames.key', '=', 'lastname');
            })
            ->where('bs_users.status', '!=', 'deleted')
            ->where(function ($query) use ($q): void {
                $query->where('bs_users.alias', 'like', '%' . $q . '%')
                    ->orWhere('firstnames.value', 'like', '%' . $q . '%')
                    ->orWhere('lastnames.value', 'like', '%' . $q . '%');

                // Full-name match ("Vorname Nachname") — DB-agnostic (no CONCAT/|| which differ across MySQL/SQLite).
                if (str_contains($q, ' ')) {
                    [$first, $last] = explode(' ', $q, 2);
                    $query->orWhere(function ($sub) use ($first, $last): void {
                        $sub->where('firstnames.value', 'like', '%' . trim($first) . '%')
                            ->where('lastnames.value', 'like', '%' . trim($last) . '%');
                    });
                }
            })
            ->orderBy('bs_users.alias')
            ->limit(10)
            ->pluck('bs_users.alias')
            ->unique()
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

        if (!$user || !$this->bookingService->canUserCancelSingle($user, $booking)) {
            return back()->withErrors(['booking' => 'Diese Buchung kann online nicht mehr storniert werden.']);
        }

        $this->bookingService->cancelSingle($booking);

        return redirect()->route('calendar.index')
            ->with('success', 'Buchung storniert.');
    }
}
