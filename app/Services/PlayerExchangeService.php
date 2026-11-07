<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PlayerExchangeException;
use App\Mail\PlayerSearchJoined;
use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\PlayerSignup;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

final class PlayerExchangeService
{
    public const META_KEY = 'player-exchange';

    public function optIn(User $user): void
    {
        $user->setMeta(self::META_KEY, '1');
    }

    public function optOut(User $user): void
    {
        $user->setMeta(self::META_KEY, null);
    }

    public function isOptedIn(User $user): bool
    {
        return $user->meta()->where('key', self::META_KEY)->value('value') === '1';
    }

    /** Deadlocks and lock wait timeouts are retried this often before the error reaches the caller. */
    private const TRANSACTION_ATTEMPTS = 3;

    /** Minutes before the first reservation start at which a search ends. */
    public const DEADLINE_MINUTES = 60;

    /** A search is only offered when the start is more than the deadline lead away. */
    public function canOffer(Carbon $start): bool
    {
        return $start->greaterThan(Carbon::now()->addMinutes(self::DEADLINE_MINUTES));
    }

    /**
     * Places that may be searched: quantity minus the booker minus named players.
     *
     * @param  array<int, array<string, mixed>>  $meta  meta entries as passed to createSingle
     */
    public function maxSearchable(int $quantity, array $meta): int
    {
        $named = 0;
        foreach ($meta as $entry) {
            if (($entry['key'] ?? null) !== 'player-names') {
                continue;
            }
            $players = @unserialize((string) ($entry['value'] ?? ''), ['allowed_classes' => false]);
            foreach (is_array($players) ? $players : [] as $player) {
                if (trim((string) ($player['value'] ?? '')) !== '') {
                    $named++;
                }
            }
        }

        return max(0, $quantity - 1 - $named);
    }

    /**
     * Open a search for the booking (call inside the booking transaction).
     *
     * @throws PlayerExchangeException
     */
    public function openSearch(Booking $booking, int $needed): PlayerSearch
    {
        $booking->loadMissing('reservations', 'meta');
        $first = $booking->reservations->sortBy(fn ($r) => $r->date.' '.$r->time_start)->first();
        $start = $first ? Carbon::parse($first->date.' '.$first->time_start) : null;

        if ($needed < 1) {
            throw new PlayerExchangeException(__('booking.messages.player_exchange.needed_invalid'));
        }
        $meta = $booking->meta->map(fn ($m) => ['key' => $m->key, 'value' => $m->value])->all();
        if ($needed > $this->maxSearchable((int) $booking->quantity, $meta)) {
            throw new PlayerExchangeException(__('booking.messages.player_exchange.too_many'));
        }
        if ($start === null || ! $this->canOffer($start)) {
            throw new PlayerExchangeException(__('booking.messages.player_exchange.too_late'));
        }

        return PlayerSearch::create([
            'bid' => $booking->bid,
            'needed' => $needed,
            'ends_at' => $start->copy()->subMinutes(self::DEADLINE_MINUTES),
            'status' => PlayerSearch::OPEN,
        ]);
    }

    /**
     * Sign the member up for the first free place of the booking's open search.
     *
     * Runs under the booking row lock and the member's user row lock, both taken before
     * any plain read: under REPEATABLE READ the first plain read fixes the snapshot, so
     * every check then sees what a parallel join or leave on this booking, or a parallel
     * join of the same member elsewhere, has committed. The last place is never given
     * twice and a member is never signed up twice at the same time.
     *
     * @throws PlayerExchangeException
     */
    public function join(User $user, Booking $booking): PlayerSignup
    {
        [$signup, $search] = DB::transaction(function () use ($user, $booking): array {
            $locked = $this->lockBooking($booking, $user);
            $search = $this->currentSearch($locked);

            if ($search === null || ! in_array($search->status, [PlayerSearch::OPEN, PlayerSearch::FILLED], true)) {
                throw $this->refused('not_open');
            }
            if (! Carbon::now()->lessThan($search->ends_at)) {
                throw $this->refused('deadline_passed');
            }
            if ((int) $locked->uid === (int) $user->uid) {
                throw $this->refused('own_booking');
            }
            if (! $this->isOptedIn($user)) {
                throw $this->refused('not_opted_in');
            }
            if (! User::query()->active()->whereKey($user->uid)->exists()) {
                throw $this->refused('inactive');
            }

            // Plain read: the booking lock already serialises everything on this search, and a
            // FOR UPDATE here would take gap locks that block joins on neighbouring searches.
            $signups = $search->signups()->get();
            if ($signups->contains(fn (PlayerSignup $s): bool => (int) $s->uid === (int) $user->uid)) {
                throw $this->refused('already_joined');
            }

            $names = $this->playerNamesBySlot($locked);
            $slot = $this->firstFreeSlot($locked, $names, $signups->pluck('slot')->map(fn ($s): int => (int) $s)->all());
            if ($search->status !== PlayerSearch::OPEN || $search->needed - $signups->count() <= 0 || $slot === null) {
                throw $this->refused('full');
            }

            foreach ($locked->reservations as $reservation) {
                [$start, $end] = $this->reservationWindow($reservation);
                if ($this->userBusy($user, $start, $end, (int) $locked->bid)) {
                    throw $this->refused('busy');
                }
            }

            $signup = PlayerSignup::create(['search_id' => $search->id, 'uid' => $user->uid, 'slot' => $slot]);

            $names[$slot] = (string) $user->name;
            app(BookingService::class)->syncPlayerMeta($locked, $names);

            if ($search->remaining() === 0) {
                $search->update(['status' => PlayerSearch::FILLED]);
            }

            return [$signup, $search];
        }, self::TRANSACTION_ATTEMPTS);

        $this->notifyOwnerJoined($search, $user);

        return $signup;
    }

    /**
     * Remove the member's signup and name; possible until the search deadline.
     * A filled search opens again, an ended one stays ended.
     *
     * @throws PlayerExchangeException
     */
    public function leave(User $user, Booking $booking): void
    {
        DB::transaction(function () use ($user, $booking): void {
            $locked = $this->lockBooking($booking);
            $search = $this->currentSearch($locked);

            $signup = $search?->signups()->where('uid', $user->uid)->first();
            if ($search === null || $signup === null
                || ! in_array($search->status, [PlayerSearch::OPEN, PlayerSearch::FILLED, PlayerSearch::ENDED], true)) {
                throw $this->refused('not_joined');
            }
            if (! Carbon::now()->lessThan($search->ends_at)) {
                throw $this->refused('leave_too_late');
            }

            $names = $this->playerNamesBySlot($locked);
            unset($names[(int) $signup->slot]);
            $signup->delete();
            app(BookingService::class)->syncPlayerMeta($locked, $names);

            if ($search->status === PlayerSearch::FILLED) {
                $search->update(['status' => PlayerSearch::OPEN]);
            }
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * The booker ends an open search; the booking and the members already signed up stay.
     *
     * @throws PlayerExchangeException
     */
    public function endSearch(User $owner, Booking $booking): void
    {
        DB::transaction(function () use ($owner, $booking): void {
            $locked = $this->lockBooking($booking);
            if ((int) $locked->uid !== (int) $owner->uid) {
                throw $this->refused('not_owner');
            }

            $search = $this->currentSearch($locked);
            if ($search === null || $search->status !== PlayerSearch::OPEN) {
                throw $this->refused('not_open');
            }

            $search->update(['status' => PlayerSearch::ENDED]);
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Whether the member is already playing at that time: an own active booking on any
     * court, or a signup in a search (open, filled or ended) of an active booking.
     */
    public function userBusy(User $user, CarbonInterface $start, CarbonInterface $end, ?int $exceptBid = null): bool
    {
        $overlaps = static function ($query) use ($start, $end): void {
            $query->where('bs_reservations.date', $start->format('Y-m-d'))
                ->where('bs_reservations.time_start', '<', $end->format('H:i:s'))
                ->where('bs_reservations.time_end', '>', $start->format('H:i:s'));
        };

        $ownBooking = Booking::query()
            ->join('bs_reservations', 'bs_reservations.bid', '=', 'bs_bookings.bid')
            ->where('bs_bookings.uid', $user->uid)
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->where($overlaps)
            ->when($exceptBid !== null, fn ($q) => $q->where('bs_bookings.bid', '!=', $exceptBid))
            ->exists();

        if ($ownBooking) {
            return true;
        }

        return PlayerSignup::query()
            ->join('player_exchange_searches', 'player_exchange_searches.id', '=', 'player_exchange_signups.search_id')
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'player_exchange_searches.bid')
            ->join('bs_reservations', 'bs_reservations.bid', '=', 'bs_bookings.bid')
            ->where('player_exchange_signups.uid', $user->uid)
            ->whereIn('player_exchange_searches.status', [PlayerSearch::OPEN, PlayerSearch::FILLED, PlayerSearch::ENDED])
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->where($overlaps)
            ->when($exceptBid !== null, fn ($q) => $q->where('bs_bookings.bid', '!=', $exceptBid))
            ->exists();
    }

    /**
     * Mail the booker about the new signup. Called after the join transaction has
     * committed; the mail goes out after the response, so a slow or broken mail server
     * never holds a lock or fails the join.
     */
    private function notifyOwnerJoined(PlayerSearch $search, User $joiner): void
    {
        $email = (string) $search->booking()->with('user')->first()?->user?->email;
        if ($email === '' || ! app(BookingService::class)->isMailConfigured()) {
            return;
        }

        $remaining = $search->remaining();
        dispatch(function () use ($email, $search, $joiner, $remaining): void {
            try {
                Mail::to($email)->send(new PlayerSearchJoined($search, $joiner, $remaining));
            } catch (\Throwable $e) {
                // Mail errors must not affect the join already done
                report($e);
            }
        })->afterResponse();
    }

    /**
     * Lock the booking row (the mutex for its search) and, for a join, the member's user
     * row (the mutex for that member's joins); only then load the booking. These must be
     * the first statements of the transaction: locking reads see the latest commit, and
     * the snapshot for the plain reads after them is taken only once both locks are held.
     *
     * @throws PlayerExchangeException
     */
    private function lockBooking(Booking $booking, ?User $member = null): Booking
    {
        $locked = Booking::query()->whereKey($booking->bid)->lockForUpdate()->first();
        if ($member !== null) {
            User::query()->whereKey($member->uid)->lockForUpdate()->get();
        }
        if ($locked === null || ! in_array($locked->status, Booking::ACTIVE_STATUSES, true)) {
            throw $this->refused('not_open');
        }

        return $locked->load('reservations', 'meta');
    }

    /** Plain read: callers hold the booking row lock, which serialises every change to its search. */
    private function currentSearch(Booking $locked): ?PlayerSearch
    {
        return PlayerSearch::query()->where('bid', $locked->bid)->first();
    }

    /** @return array<int, string> slot (2-4) => name */
    private function playerNamesBySlot(Booking $booking): array
    {
        $value = $booking->meta->firstWhere('key', 'player-names')?->value;
        $players = is_string($value) ? @unserialize($value, ['allowed_classes' => false]) : [];

        $names = [];
        foreach (is_array($players) ? $players : [] as $player) {
            if (! is_array($player) || preg_match('/^sb-player-name-([2-4])$/', (string) ($player['name'] ?? ''), $m) !== 1) {
                continue;
            }
            $name = trim((string) ($player['value'] ?? ''));
            if ($name !== '') {
                $names[(int) $m[1]] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  array<int, string>  $names
     * @param  list<int>  $takenSlots
     */
    private function firstFreeSlot(Booking $booking, array $names, array $takenSlots): ?int
    {
        for ($slot = 2; $slot <= min(4, (int) $booking->quantity); $slot++) {
            if (! isset($names[$slot]) && ! in_array($slot, $takenSlots, true)) {
                return $slot;
            }
        }

        return null;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function reservationWindow(Reservation $reservation): array
    {
        $date = Carbon::parse($reservation->date)->format('Y-m-d');

        return [Carbon::parse($date.' '.$reservation->time_start), Carbon::parse($date.' '.$reservation->time_end)];
    }

    private function refused(string $key): PlayerExchangeException
    {
        return new PlayerExchangeException(__('booking.messages.player_exchange.'.$key));
    }

    /** Signed one-click unsubscribe link (no login, no expiry) for the e-mails. */
    public function unsubscribeUrl(User $user): string
    {
        return URL::signedRoute('player-exchange.unsubscribe', ['user' => $user->uid]);
    }

    /**
     * Active (not blocked) opted-in users with an e-mail address.
     *
     * @return Collection<int, User>
     */
    public function recipients(?User $exclude = null): Collection
    {
        return $this->recipientsQuery($exclude)->orderBy('uid')->get();
    }

    /**
     * Query of the recipients, for sending in chunks (chunkById) without loading every member.
     *
     * @return Builder<User>
     */
    public function recipientsQuery(?User $exclude = null): Builder
    {
        return User::query()
            ->active()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('meta', fn ($q) => $q->where('key', self::META_KEY)->where('value', '1'))
            ->when($exclude, fn ($q) => $q->where('uid', '!=', $exclude->uid));
    }
}
