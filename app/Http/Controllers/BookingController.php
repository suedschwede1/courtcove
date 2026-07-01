<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Models\Option;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
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
            'sid' => ['required', 'integer', 'exists:bs_squares,sid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time_start' => ['required', 'integer', 'min:0', 'max:23'],
        ]);

        $square = Square::findOrFail((int) $data['sid']);
        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $timeStart = (int) $data['time_start'];
        $timeEnd = $timeStart + 1;

        return view('bookings.create', compact('square', 'date', 'timeStart', 'timeEnd'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'sid' => ['required', 'integer', 'exists:bs_squares,sid'],
            'date' => ['required', 'date'],
            'time_start' => ['required', 'integer', 'min:0', 'max:86399'],
            'time_end' => ['required', 'integer', 'min:1', 'max:86400'],
            'quantity' => ['required', 'integer', 'in:2,4'],
            'mitspieler' => ['required', 'string', 'max:255'],
        ]);

        $square = Square::findOrFail((int) $data['sid']);
        $dateStart = Carbon::parse($data['date'])->startOfDay()
            ->addSeconds($this->parseTimeToSeconds((string) $data['time_start']));
        $dateEnd = Carbon::parse($data['date'])->startOfDay()
            ->addSeconds($this->parseTimeToSeconds((string) $data['time_end']));

        $meta = $this->bookingService->buildPlayerMeta([
            2 => $data['mitspieler'] ?? null,
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
            if ($request->ajax()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->withErrors(['booking' => $e->getMessage()])->with('error', $e->getMessage());
        } catch (\Throwable) {
            $msg = __('booking.messages.booking_failed');
            if ($request->ajax()) {
                return response()->json(['error' => $msg], 422);
            }
            return back()->withErrors(['booking' => $msg])->with('error', $msg);
        }

        if (Option::getValue('service.quotes.enabled', '1') === '1') {
            $pool = __('booking.quotes');

            // quotes_named only exists for German; app.fallback_locale is 'de',
            // so a plain __() lookup would silently leak German quotes into
            // other locales — guard explicitly instead.
            if (app()->getLocale() === 'de') {
                $namedQuotes = __('booking.quotes_named');
                if (is_array($namedQuotes)) {
                    $pool = array_merge($pool, $namedQuotes);
                }
            }

            $quote = str_replace(':name', (string) auth()->user()?->alias, $pool[array_rand($pool)]);
            session()->flash('booking_quote', $quote);
        }

        if ($request->ajax()) {
            session()->flash('success', __('booking.messages.booking_created_public'));
            return response()->json(['redirect' => route('calendar.index', ['date' => Carbon::parse($data['date'])->format('Y-m-d')])]);
        }

        return redirect()->route('calendar.index', ['date' => Carbon::parse($data['date'])->format('Y-m-d')])
            ->with('success', __('booking.messages.booking_created_public'));
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
                $query->where('bs_users.alias', 'like', '%'.$q.'%')
                    ->orWhere('firstnames.value', 'like', '%'.$q.'%')
                    ->orWhere('lastnames.value', 'like', '%'.$q.'%');

                // Full-name match ("Vorname Nachname") — DB-agnostic (no CONCAT/|| which differ across MySQL/SQLite).
                if (str_contains($q, ' ')) {
                    [$first, $last] = explode(' ', $q, 2);
                    $query->orWhere(function ($sub) use ($first, $last): void {
                        $sub->where('firstnames.value', 'like', '%'.trim($first).'%')
                            ->where('lastnames.value', 'like', '%'.trim($last).'%');
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

    public function edit(Booking $booking): View
    {
        $user = auth()->user();

        if ($booking->uid !== $user?->getAuthIdentifier()) {
            abort(403);
        }

        $booking->load(['square', 'reservations', 'meta']);
        $reservation = $booking->reservations
            ->sortBy(fn ($reservation): string => (string) $reservation->date.' '.(string) $reservation->time_start)
            ->first();

        if (! $reservation) {
            abort(404);
        }

        $names = $booking->player_names;
        $playerNames = [
            2 => $names[0] ?? '',
            3 => $names[1] ?? '',
            4 => $names[2] ?? '',
        ];

        return view('bookings.edit', compact('booking', 'reservation', 'playerNames'));
    }

    public function update(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        $user = auth()->user();

        if ($booking->uid !== $user?->getAuthIdentifier()) {
            abort(403);
        }

        if (! $user || $booking->status !== 'single') {
            $msg = __('booking.messages.booking_not_cancellable');
            if ($request->ajax()) {
                return response()->json(['error' => $msg], 422);
            }
            return back()->withErrors(['booking' => $msg])->with('error', $msg);
        }

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'in:2,4'],
            'mitspieler' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->bookingService->updateSinglePlayers($booking, $user, (int) $data['quantity'], [
                2 => $data['mitspieler'] ?? null,
            ]);
        } catch (BookingValidationException $e) {
            if ($request->ajax()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->withErrors(['booking' => $e->getMessage()])->with('error', $e->getMessage());
        } catch (\Throwable) {
            $msg = __('booking.messages.booking_failed');
            if ($request->ajax()) {
                return response()->json(['error' => $msg], 422);
            }
            return back()->withErrors(['booking' => $msg])->with('error', $msg);
        }

        $reservation = $booking->reservations()
            ->orderBy('date')
            ->orderBy('time_start')
            ->first();

        if ($request->ajax()) {
            return response()->json(['redirect' => route('calendar.index', ['date' => $reservation?->date])]);
        }

        return redirect()->route('calendar.index', ['date' => $reservation?->date])
            ->with('success', __('booking.messages.booking_updated'));
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

        if ($booking->uid !== $user?->getAuthIdentifier() && ! $user?->can('admin.booking')) {
            abort(403);
        }

        if (! $user || ! $this->bookingService->canUserCancelSingle($user, $booking)) {
            return back()->withErrors(['booking' => __('booking.messages.booking_not_cancellable')])->with('error', __('booking.messages.booking_not_cancellable'));
        }

        $this->bookingService->cancelSingle($booking);

        return redirect()->route('calendar.index')
            ->with('success', __('booking.messages.booking_cancelled'));
    }
}
