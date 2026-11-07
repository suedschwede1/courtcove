<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PlayerExchangeException;
use App\Mail\PlayerSearchBookingCancelled;
use App\Mail\PlayerSearchExpired;
use App\Mail\PlayerSearchJoined;
use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\PlayerSignup;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
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

    /**
     * Attempts of the lazy lapse in a web request (calendar load, before a join): a held booking
     * lock then costs one lock wait timeout, not three; the scheduler command keeps the retries.
     */
    public const LAZY_LAPSE_ATTEMPTS = 1;

    /**
     * Minutes before the first reservation start at which a search ends. The texts say "one
     * hour" (booking.messages.player_exchange.too_late, booking.modal.search_help, the expired
     * mail, the scheduler task description): change them together with this value.
     */
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
     * Open a search for the booking (call inside the booking transaction). Single bookings
     * only (spec): cancel and delete of one occurrence of a series rely on it.
     *
     * @throws PlayerExchangeException
     */
    public function openSearch(Booking $booking, int $needed): PlayerSearch
    {
        if ($booking->status !== 'single') {
            throw new \LogicException('A player search is only possible for a single booking.');
        }
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
     * A filled search opens again, an ended one stays ended. Only the slot of his own
     * signup is cleared (never matched by name), so typed partners and other joined
     * members with the same name stay, and the booker is not part of these slots.
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

    /** Statuses in which the members who joined still hold their place in the booking. */
    public const LIVE_STATUSES = [PlayerSearch::OPEN, PlayerSearch::FILLED, PlayerSearch::ENDED];

    /** The search of the booking whose joined members still hold places (open, filled or ended). */
    public function liveSearch(Booking $booking): ?PlayerSearch
    {
        return PlayerSearch::query()
            ->where('bid', $booking->bid)
            ->whereIn('status', self::LIVE_STATUSES)
            ->first();
    }

    /**
     * Whether the search stands in for the booker's partner: it is open (players are still
     * sought) or members are signed up (filled or ended). An ended search without signups does
     * not: the booking then needs a typed partner again like any other.
     */
    public function searchReplacesPartner(Booking $booking): bool
    {
        $search = $this->liveSearch($booking);

        return $search !== null && ($search->status === PlayerSearch::OPEN || $search->signups()->exists());
    }

    /**
     * The player names of the booking split for the booker's edit form: "own" are the partners
     * he typed himself (he may change them), "joined" the members of the live search (only they
     * can leave). Both slot => name.
     *
     * @return array{own: array<int, string>, joined: array<int, string>}
     */
    public function namesForOwnerEdit(Booking $booking): array
    {
        $booking->loadMissing('meta');
        $names = $this->playerNamesBySlot($booking);
        $search = $this->liveSearch($booking);

        $joined = [];
        foreach ($search?->signups()->with('user')->orderBy('slot')->get() ?? [] as $signup) {
            $joined[(int) $signup->slot] = $names[(int) $signup->slot] ?? (string) $signup->user?->name;
        }

        return ['own' => array_diff_key($names, $joined), 'joined' => $joined];
    }

    /**
     * The booker's edit of a booking with a live search (BookingService::updateSinglePlayers,
     * inside its transaction, after the booking row lock). Owner-editable are only the slots
     * 2..quantity that no joined member holds: a joined member keeps slot and name, and only
     * when Einzel leaves his slot outside the court does he move to the free slot (signup and
     * name together). The typed names fill the other free slots in order. Refused when typed
     * partners plus joined members need more places than the new quantity has.
     *
     * An edit never raises the searched number (spec: no change of the number after signups):
     * the places still searched are the old remaining ones, cut to the free slots that are
     * left. Nothing left to search: an open search becomes filled, or ended without signups
     * (the booker found his partners himself, so the booking must not lapse). Filled and ended
     * stay as they are.
     *
     * @param  list<string>  $ownNames  the partners the booker typed, in slot order
     *
     * @throws PlayerExchangeException
     */
    public function applyOwnerEdit(Booking $locked, PlayerSearch $search, int $quantity, array $ownNames): void
    {
        $signups = $search->signups()->with('user')->orderBy('slot')->get();
        $maxSlot = min(4, $quantity);
        $places = max(0, $maxSlot - 1);

        if (count($ownNames) + $signups->count() > $places) {
            throw $this->refused('edit_too_many');
        }

        $names = $this->playerNamesBySlot($locked);
        $taken = $signups->map(fn (PlayerSignup $s): int => (int) $s->slot)
            ->filter(fn (int $slot): bool => $slot <= $maxSlot)->values()->all();

        $merged = [];
        foreach ($signups as $signup) {
            $slot = (int) $signup->slot;
            $name = $names[$slot] ?? (string) $signup->user?->name;
            if ($slot > $maxSlot) {
                $slot = (int) $this->freeSlots($maxSlot, $taken)[0];
                $signup->update(['slot' => $slot]);
                $taken[] = $slot;
            }
            $merged[$slot] = $name;
        }

        $free = $this->freeSlots($maxSlot, $taken);
        foreach ($ownNames as $index => $name) {
            $merged[$free[$index]] = $name;
        }
        ksort($merged);

        $locked->update(['quantity' => $quantity]);
        app(BookingService::class)->syncPlayerMeta($locked, $merged);

        $remaining = min(max(0, $search->needed - $signups->count()), count($free) - count($ownNames));
        if ($remaining === 0 && $signups->isEmpty()) {
            if ($search->status === PlayerSearch::OPEN) {
                $search->update(['status' => PlayerSearch::ENDED]);
            }

            return;
        }

        $changes = ['needed' => $signups->count() + $remaining];
        if ($remaining === 0 && $search->status === PlayerSearch::OPEN) {
            $changes['status'] = PlayerSearch::FILLED;
        }
        $search->update($changes);
    }

    /**
     * The search that binds an admin edit of the booking: open or filled (the deadline and the
     * mails depend on the time), or ended with joined members (they agreed to that time and
     * hold places). An ended search without signups binds nothing. Plain read: the caller holds
     * the booking row lock.
     */
    public function searchBindingAdminEdit(Booking $locked): ?PlayerSearch
    {
        $search = $this->liveSearch($locked);
        if ($search === null) {
            return null;
        }

        return $search->status !== PlayerSearch::ENDED || $search->signups()->exists() ? $search : null;
    }

    /**
     * The admin edit (BookingService::updateSeries, inside its transaction, after the booking
     * row lock) of a booking with a binding search. Date, time, repeat and booker must stay as
     * they are, the court may change (done by the caller). The admin form lists the stored names
     * by position, so joined members are matched by name, not by field: each joined member's
     * stored name must still be among the submitted names (he can only leave himself; a joined
     * slot without a stored name is not shown and so is not checked), every other
     * name is the booker's own partner. Names, quantity and the search then follow the owner
     * edit rules (applyOwnerEdit): joined members keep their slot, capacity is checked.
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $slots  new date, start, end of each occurrence
     * @param  array<int, string|null>  $playerNames  slot => name as submitted
     *
     * @throws PlayerExchangeException
     */
    public function applyAdminEdit(Booking $locked, PlayerSearch $search, int $uid, array $slots, int $quantity, array $playerNames): void
    {
        $current = $locked->reservations
            ->map(fn (Reservation $r): array => [
                Carbon::parse($r->date)->format('Y-m-d'),
                Carbon::parse((string) $r->time_start)->format('H:i:s'),
                Carbon::parse((string) $r->time_end)->format('H:i:s'),
            ])
            ->sort()->values()->all();
        if ($uid !== (int) $locked->uid || $slots !== $current) {
            throw $this->refused('admin_edit_locked');
        }

        ksort($playerNames);
        $typed = array_values(array_filter(
            array_map(fn ($name): string => trim((string) $name), $playerNames),
            fn (string $name): bool => $name !== '',
        ));

        $names = $this->playerNamesBySlot($locked);
        $missing = [];
        foreach ($search->signups()->with('user')->orderBy('slot')->get() as $signup) {
            if (! isset($names[(int) $signup->slot])) {
                // No name stored for his slot (legacy booking): the form cannot show him, so he
                // counts as present and applyOwnerEdit writes his name back. A typed profile
                // name is taken as him, not as a second player.
                $index = array_search((string) $signup->user?->name, $typed, true);
                if ($index !== false) {
                    unset($typed[$index]);
                }

                continue;
            }

            $name = $names[(int) $signup->slot];
            $index = array_search($name, $typed, true);
            if ($index === false) {
                $missing[] = $name;

                continue;
            }
            unset($typed[$index]);
        }

        if ($missing !== []) {
            throw new PlayerExchangeException(__('booking.messages.player_exchange.admin_edit_joined', ['names' => implode(', ', $missing)]));
        }

        $this->applyOwnerEdit($locked, $search, $quantity, array_values($typed));
    }

    /**
     * @param  list<int>  $taken
     * @return list<int> slots 2..$maxSlot not in $taken, ascending
     */
    private function freeSlots(int $maxSlot, array $taken): array
    {
        return array_values(array_diff(range(2, max(2, $maxSlot)), $taken));
    }

    /**
     * Verfall: every open search whose deadline (one hour before the start) has passed.
     * Without signups the booking is cancelled on the normal cancel path (no cancel mail to
     * the booker, he gets the "search expired" mail instead) and the search is lapsed; with
     * signups only the search ends, the booking stays and nobody is mailed.
     *
     * Each booking is handled in its own transaction under its row lock, the same mutex as
     * join/leave/cancel, and the search status is checked again under that lock: running it
     * twice or in parallel with a join changes nothing more and never mails twice. A failure
     * on one booking is reported and does not stop the others.
     *
     * @param  int  $attempts  per booking; web requests pass LAZY_LAPSE_ATTEMPTS
     * @return int number of bookings that lapsed (were cancelled)
     */
    public function lapseExpired(?int $onlyBid = null, int $attempts = self::TRANSACTION_ATTEMPTS): int
    {
        $bids = PlayerSearch::query()
            ->where('status', PlayerSearch::OPEN)
            ->where('ends_at', '<=', Carbon::now())
            ->when($onlyBid !== null, fn ($q) => $q->where('bid', $onlyBid))
            ->orderBy('ends_at')
            ->pluck('bid');

        $lapsed = 0;
        foreach ($bids as $bid) {
            try {
                if ($this->lapseOne((int) $bid, $attempts)) {
                    $lapsed++;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $lapsed;
    }

    /**
     * The booking was cancelled (called by BookingService::cancelSingle and deleteSingle inside
     * their transaction, after the booking row is locked): a live search is cancelled and every
     * joined player is told, also one who has left the exchange since (the opt-out only stops
     * the advertising mails). Lapsed or already cancelled searches are left alone, so no mail
     * goes out twice, however often this runs.
     */
    public function onBookingCancelled(Booking $booking): void
    {
        $search = PlayerSearch::query()->where('bid', $booking->bid)->first();
        if ($search === null || ! in_array($search->status, self::LIVE_STATUSES, true)) {
            return;
        }

        $signups = $search->signups()->with('user')->get();
        $search->update(['status' => PlayerSearch::CANCELLED]);
        $search->setRelation('booking', $booking);

        foreach ($signups as $signup) {
            if ($signup->user !== null) {
                $this->mailAfterCommit((string) $signup->user->email, new PlayerSearchBookingCancelled($search, $signup->user));
            }
        }
    }

    /**
     * The booking is deleted for good (BookingService::deleteSingle, inside its transaction,
     * after the booking row lock): a live search is cancelled with the same mails as a cancel
     * (taken as plain values now, so they still render once the rows are gone), then the search
     * and its signups are removed in the same transaction. No foreign key does this: the legacy
     * bs_* tables carry none, and an explicit cleanup keeps the mails and the lock in one place.
     */
    public function onBookingDeleted(Booking $booking): void
    {
        $this->onBookingCancelled($booking);

        $searchIds = PlayerSearch::query()->where('bid', $booking->bid)->pluck('id');
        if ($searchIds->isEmpty()) {
            return;
        }

        PlayerSignup::query()->whereIn('search_id', $searchIds)->delete();
        PlayerSearch::query()->whereKey($searchIds)->delete();
    }

    /** One booking of lapseExpired; true when the booking was cancelled. */
    private function lapseOne(int $bid, int $attempts): bool
    {
        return DB::transaction(function () use ($bid): bool {
            // Locking read first: the plain reads below then see every committed join, leave or cancel.
            $locked = Booking::query()->whereKey($bid)->lockForUpdate()->first();
            $search = PlayerSearch::query()->where('bid', $bid)->first();

            if ($search === null || $search->status !== PlayerSearch::OPEN || Carbon::now()->lessThan($search->ends_at)) {
                return false;
            }
            if ($locked === null || ! in_array($locked->status, Booking::ACTIVE_STATUSES, true)) {
                // The booking is gone or was cancelled without the exchange noticing: close the search quietly.
                $search->update(['status' => PlayerSearch::CANCELLED]);

                return false;
            }
            if ($search->signups()->exists()) {
                $search->update(['status' => PlayerSearch::ENDED]);

                return false;
            }

            $search->update(['status' => PlayerSearch::LAPSED]);
            app(BookingService::class)->cancelSingle($locked, notifyOwner: false);

            $locked->loadMissing('user');
            $search->setRelation('booking', $locked);
            $this->mailAfterCommit((string) $locked->user?->email, new PlayerSearchExpired($search));

            return true;
        }, max(1, $attempts));
    }

    /**
     * Send the mail once the surrounding transaction has committed (nothing is sent for a
     * rolled back attempt). In a web request it goes out after the response; in the
     * console (scheduler) right away. The mail carries plain values only, so it renders
     * even if a worker picks it up after its rows are gone. Mail errors are reported and
     * never undo the change.
     */
    private function mailAfterCommit(string $email, Mailable $mail): void
    {
        if ($email === '' || ! app(BookingService::class)->isMailConfigured()) {
            return;
        }

        DB::afterCommit(function () use ($email, $mail): void {
            $send = static function () use ($email, $mail): void {
                try {
                    Mail::to($email)->send($mail);
                } catch (\Throwable $e) {
                    report($e);
                }
            };

            if (app()->runningInConsole()) {
                $send();
            } else {
                dispatch($send)->afterResponse();
            }
        });
    }

    /**
     * Whether the member is already playing at that time: an own active booking on any
     * court, or a signup in a search (open, filled or ended) of an active booking. The same
     * check as an own booking uses (MemberAvailability).
     */
    public function userBusy(User $user, CarbonInterface $start, CarbonInterface $end, ?int $exceptBid = null): bool
    {
        return app(MemberAvailability::class)->busyAt($user, $start, $end, $exceptBid);
    }

    /**
     * Mail the booker about the new signup. Called after the join transaction has
     * committed; the mail goes out after the response, so a slow or broken mail server
     * never holds a lock or fails the join. A blocked or deactivated booker gets no mail
     * (spec rule 1: exchange mails only go to active accounts).
     */
    private function notifyOwnerJoined(PlayerSearch $search, User $joiner): void
    {
        $owner = $search->booking()->with('user')->first()?->user;
        $email = $owner?->isEnabled() ? (string) $owner->email : '';
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
            app(MemberAvailability::class)->lockMember($member);
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
