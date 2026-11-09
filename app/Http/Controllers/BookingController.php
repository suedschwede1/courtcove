<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Models\Option;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PlayerExchangeService;
use App\Services\QuoteGroups;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
            'slot_start' => ['nullable', 'integer', 'min:0', 'max:86399'],
            'slot_end' => ['nullable', 'integer', 'min:1', 'max:86400'],
        ]);

        $square = Square::findOrFail((int) $data['sid']);
        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $timeStart = (int) $data['time_start'];
        $timeEnd = $timeStart + 1;

        return view('bookings.create', compact('square', 'date', 'timeStart', 'timeEnd') + ['slotStart' => $data['slot_start'] ?? null, 'slotEnd' => $data['slot_end'] ?? null]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'sid' => ['required', 'integer', 'exists:bs_squares,sid'],
            'date' => ['required', 'date'],
            'time_start' => ['required', 'integer', 'min:0', 'max:86399'],
            'time_end' => ['required', 'integer', 'min:1', 'max:86400'],
            'quantity' => ['required', 'integer', 'in:2,4'],
            'search_players' => ['nullable', 'integer', 'min:1'],
            'player_exchange_opt_in' => ['nullable', 'boolean'],
            'mitspieler' => ['required_without:search_players', 'nullable', 'string', 'max:255'],
        ]);
        $searchPlayers = (int) ($data['search_players'] ?? 0);

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
                searchPlayers: $searchPlayers,
            );
        } catch (BookingValidationException $e) {
            if ($request->ajax()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return back()->withErrors(['booking' => $e->getMessage()])->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $msg = __('booking.messages.booking_failed');
            $this->logFailure('booking.store', $e, [
                'sid' => $square->sid,
                'date' => $dateStart->toDateString(),
                'quantity' => (int) $data['quantity'],
                'search_players' => $searchPlayers,
            ]);
            if ($request->ajax()) {
                return response()->json(['error' => $msg], 422);
            }

            return back()->withErrors(['booking' => $msg])->with('error', $msg);
        }

        // The notice under "Seek players" says the member joins the exchange with it; only then the flag is sent.
        $optedIn = false;
        if ($searchPlayers > 0 && $request->boolean('player_exchange_opt_in')) {
            $user = auth()->user();
            $exchange = app(PlayerExchangeService::class);
            if ($user instanceof User && ! $exchange->isOptedIn($user)) {
                $exchange->optIn($user, 'booking');
                $optedIn = true;
            }
        }

        $quote = null;

        if (Option::getValue('service.quotes.enabled', '1') === '1') {
            $locale = app()->getLocale();
            $pool = QuoteGroups::baseQuotes($locale, __('booking.quotes'));

            // Every locale now ships its own quotes_named (see lang/{locale}/
            // booking/quotes.php), so this merge is locale-safe without a
            // guard: __() resolves it from the current locale's own file.
            $namedQuotes = __('booking.quotes_named');
            $namedQuotes = QuoteGroups::namedQuotes($locale, is_array($namedQuotes) ? $namedQuotes : []);
            if ($namedQuotes !== []) {
                $pool = array_merge($pool, $namedQuotes);
            }

            // Quote groups (private, not tracked in git) are only defined at
            // a hardcoded German path — app.fallback_locale is 'de', so an
            // unguarded lookup would leak German quotes into other locales.
            // A member's assigned group is weighted in twice so it dominates
            // the random pick while still leaving room for the generic pool.
            if ($locale === 'de') {
                $groupQuotes = QuoteGroups::quotesFor(auth()->user()?->getMeta('quote_group'));
                if ($groupQuotes !== []) {
                    $pool = array_merge($pool, $groupQuotes, $groupQuotes);
                }
            }

            $quote = str_replace(':name', (string) auth()->user()?->alias, $pool[array_rand($pool)]);
        }

        if ($request->ajax()) {
            // The sheet shows the quote itself, so nothing is flashed here.
            return response()->json([
                'redirect' => route('calendar.index', ['date' => Carbon::parse($data['date'])->format('Y-m-d')]),
                'quote' => $quote,
                'search' => $searchPlayers > 0,
                'opted_in' => $optedIn,
            ]);
        }

        if ($quote !== null) {
            session()->flash('booking_quote', $quote);
        }

        return redirect()->route('calendar.index', ['date' => Carbon::parse($data['date'])->format('Y-m-d')])
            ->with('success', $searchPlayers > 0
                ? __('booking.messages.booking_created_with_search')
                : __('booking.messages.booking_created_public'));
    }

    public function players(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $quantity = (string) $request->query('quantity', '2');

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
            ->when($quantity === '4', function ($query): void {
                // Doubles: only suggest placeholder names, never real user accounts.
                $query->where('bs_users.status', 'placeholder');
            }, function ($query): void {
                // Singles: look up all real users.
                $query->where('bs_users.status', '!=', 'deleted');
            })
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
        $joinedNames = [];

        // With a live player search the field holds only the booker's own typed partner;
        // the joined members are listed, not editable.
        $exchange = app(PlayerExchangeService::class);
        if ($exchange->liveSearch($booking) !== null) {
            $split = $exchange->namesForOwnerEdit($booking);
            $names = array_values($split['own']);
            $joinedNames = array_values($split['joined']);
        }

        $playerNames = [
            2 => $names[0] ?? '',
            3 => $names[1] ?? '',
            4 => $names[2] ?? '',
        ];

        return view('bookings.edit', compact('booking', 'reservation', 'playerNames', 'joinedNames'));
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

        // With an open search or joined members the booker may clear his own partner: the joined
        // (or still sought) members play. An ended search without signups does not count.
        $hasSearch = app(PlayerExchangeService::class)->searchReplacesPartner($booking);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'in:2,4'],
            'mitspieler' => [$hasSearch ? 'nullable' : 'required', 'string', 'max:255'],
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
        } catch (\Throwable $e) {
            $msg = __('booking.messages.booking_failed');
            $this->logFailure('booking.update', $e, ['bid' => $booking->bid, 'quantity' => (int) $data['quantity']]);
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
        /** @var User|null $user */
        $user = auth()->user();

        if ($booking->uid !== $user?->getAuthIdentifier() && ! $user?->can('admin.booking')) {
            abort(403);
        }

        if (! $this->bookingService->canUserCancelSingle($user, $booking)) {
            return back()->withErrors(['booking' => __('booking.messages.booking_not_cancellable')])->with('error', __('booking.messages.booking_not_cancellable'));
        }

        // The check above ran on the booking as loaded; the cancel decides on the locked row. A
        // booking the lapse of its player search lifted meanwhile is not cancelled again.
        if (! $this->bookingService->cancelSingle($booking)) {
            return back()->withErrors(['booking' => __('booking.messages.booking_already_cancelled')])->with('error', __('booking.messages.booking_already_cancelled'));
        }

        return redirect()->route('calendar.index')
            ->with('success', __('booking.messages.booking_cancelled'));
    }

    /**
     * A failure the user only sees as a generic message must still leave a trace. Only the
     * exception class, its code and location and ids that name no person are logged: the
     * message of a database error can carry the values of the statement.
     *
     * @param  array<string, int|string>  $context
     */
    private function logFailure(string $action, \Throwable $e, array $context): void
    {
        Log::warning('Booking request failed', $context + [
            'action' => $action,
            'exception' => $e::class,
            'code' => $e->getCode(),
            'at' => basename($e->getFile()).':'.$e->getLine(),
        ]);
    }
}
