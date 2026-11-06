<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\PlayerExchangeException;
use App\Models\Booking;
use App\Models\User;
use App\Services\PlayerExchangeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Player exchange endpoints: joining, leaving and ending a search (logged in), and the
 * one-click unsubscribe, where the signature check (middleware "signed") is the only authorisation.
 */
final class PlayerExchangeController extends Controller
{
    /** One-click unsubscribe from the link in player exchange e-mails. */
    public function unsubscribe(User $user, PlayerExchangeService $exchange): View
    {
        $exchange->optOut($user);

        return view('player-exchange.unsubscribed');
    }

    public function join(Request $request, Booking $booking, PlayerExchangeService $exchange): JsonResponse|RedirectResponse
    {
        return $this->respond($request, $booking, fn () => $exchange->join($this->member($request), $booking), 'joined');
    }

    public function leave(Request $request, Booking $booking, PlayerExchangeService $exchange): JsonResponse|RedirectResponse
    {
        return $this->respond($request, $booking, fn () => $exchange->leave($this->member($request), $booking), 'left');
    }

    public function end(Request $request, Booking $booking, PlayerExchangeService $exchange): JsonResponse|RedirectResponse
    {
        return $this->respond($request, $booking, fn () => $exchange->endSearch($this->member($request), $booking), 'ended');
    }

    private function member(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function respond(Request $request, Booking $booking, callable $action, string $successKey): JsonResponse|RedirectResponse
    {
        $json = $request->ajax() || $request->expectsJson();

        try {
            $action();
        } catch (PlayerExchangeException $e) {
            if ($json) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return $this->toCalendar($booking)->with('error', $e->getMessage());
        }

        if ($json) {
            return response()->json(['ok' => true]);
        }

        return $this->toCalendar($booking)->with('success', __('booking.messages.player_exchange.'.$successKey));
    }

    private function toCalendar(Booking $booking): RedirectResponse
    {
        $date = $booking->reservations()->orderBy('date')->orderBy('time_start')->value('date');

        return redirect()->route('calendar.index', $date !== null ? ['date' => Carbon::parse($date)->format('Y-m-d')] : []);
    }
}
