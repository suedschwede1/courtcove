<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\PlayerExchangeException;
use App\Models\Booking;
use App\Models\User;
use App\Services\PlayerExchangeService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PDOException;

/**
 * Player exchange endpoints: joining, leaving and ending a search (logged in), and the
 * one-click unsubscribe, where the signature check (middleware "signed") is the only authorisation.
 */
final class PlayerExchangeController extends Controller
{
    use DetectsConcurrencyErrors;

    /** One-click unsubscribe from the link in player exchange e-mails. */
    public function unsubscribe(User $user, PlayerExchangeService $exchange): View
    {
        $exchange->optOut($user);

        return view('player-exchange.unsubscribed');
    }

    public function join(Request $request, Booking $booking, PlayerExchangeService $exchange): JsonResponse|RedirectResponse
    {
        // Lazy Verfall: a search past its deadline lapses first, the join then finds it closed.
        $exchange->lapseExpired((int) $booking->bid, PlayerExchangeService::LAZY_LAPSE_ATTEMPTS);

        // "Join the exchange and play" in the join dialog: the member agrees there, no detour via the account.
        $optIn = $request->boolean('opt_in');

        return $this->respond($request, $booking, function () use ($request, $booking, $exchange, $optIn): void {
            $member = $this->member($request);
            if ($optIn) {
                $exchange->optIn($member, 'join');
            }
            $exchange->join($member, $booking);
        }, 'joined');
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
        } catch (PlayerExchangeException|PDOException $e) {
            if ($e instanceof PDOException && ! $this->isLockConflict($e)) {
                throw $e;
            }
            // A deadlock or lock wait left over after the service's retries is a busy moment, not a 500.
            $message = $e instanceof PDOException ? __('booking.messages.player_exchange.retry') : $e->getMessage();

            if ($json) {
                return response()->json(['error' => $message], 422);
            }

            return $this->toCalendar($booking)->with('error', $message);
        }

        if ($json) {
            return response()->json(['ok' => true]);
        }

        return $this->toCalendar($booking)->with('success', __('booking.messages.player_exchange.'.$successKey));
    }

    /**
     * MySQL deadlock (1213) or lock wait timeout (1205), or the same detected by message
     * (also Laravel's DeadlockException, thrown instead of retrying inside an outer transaction).
     */
    private function isLockConflict(PDOException $e): bool
    {
        return in_array($e->errorInfo[1] ?? null, [1205, 1213], true) || $this->causedByConcurrencyError($e);
    }

    private function toCalendar(Booking $booking): RedirectResponse
    {
        $date = $booking->reservations()->orderBy('date')->orderBy('time_start')->value('date');

        return redirect()->route('calendar.index', $date !== null ? ['date' => Carbon::parse($date)->format('Y-m-d')] : []);
    }
}
