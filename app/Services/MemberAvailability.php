<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\PlayerSignup;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Whether a member already plays at a time: the one check for both directions of the player
 * exchange. A join asks busyAt() (own booking on any court or a signup elsewhere); an own
 * booking asks joinedAt() (a signup elsewhere; two own bookings at once stay allowed as before).
 *
 * Overlap is the rule of the court check (BookingService::hasOverlappingBooking): same date,
 * start before the other end and end after the other start, so back-to-back slots are free.
 * Like that check, a reservation is assumed not to cross midnight.
 *
 * Callers that write afterwards take lockMember() first, inside their transaction and before any
 * plain read: the member row is the mutex that serialises a member's join and own booking.
 */
final class MemberAvailability
{
    /** Row lock on the member (bs_users FOR UPDATE). Lock order: booking or court row first, then this. */
    public function lockMember(User $member): void
    {
        User::query()->whereKey($member->uid)->lockForUpdate()->get();
    }

    /** An own active booking on any court, or a signup elsewhere, overlaps the time. */
    public function busyAt(User $user, CarbonInterface $start, CarbonInterface $end, ?int $exceptBid = null): bool
    {
        return $this->ownBookingAt($user, $start, $end, $exceptBid)
            || $this->joinedAt($user, $start, $end, $exceptBid);
    }

    /** An own active booking on any court overlaps the time. */
    public function ownBookingAt(User $user, CarbonInterface $start, CarbonInterface $end, ?int $exceptBid = null): bool
    {
        return Booking::query()
            ->join('bs_reservations', 'bs_reservations.bid', '=', 'bs_bookings.bid')
            ->where('bs_bookings.uid', $user->uid)
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->where(fn (Builder $q) => $this->overlaps($q, $start, $end))
            ->when($exceptBid !== null, fn (Builder $q) => $q->where('bs_bookings.bid', '!=', $exceptBid))
            ->exists();
    }

    /**
     * The member holds a place via the exchange at an overlapping time: a signup in a search that
     * still holds places (open, filled or ended) of an active booking. Left, lapsed and cancelled
     * searches and cancelled bookings do not count.
     */
    public function joinedAt(User $user, CarbonInterface $start, CarbonInterface $end, ?int $exceptBid = null): bool
    {
        return PlayerSignup::query()
            ->join('player_exchange_searches', 'player_exchange_searches.id', '=', 'player_exchange_signups.search_id')
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'player_exchange_searches.bid')
            ->join('bs_reservations', 'bs_reservations.bid', '=', 'bs_bookings.bid')
            ->where('player_exchange_signups.uid', $user->uid)
            ->whereIn('player_exchange_searches.status', PlayerExchangeService::LIVE_STATUSES)
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->where(fn (Builder $q) => $this->overlaps($q, $start, $end))
            ->when($exceptBid !== null, fn (Builder $q) => $q->where('bs_bookings.bid', '!=', $exceptBid))
            ->exists();
    }

    private function overlaps(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->where('bs_reservations.date', $start->format('Y-m-d'))
            ->where('bs_reservations.time_start', '<', $end->format('H:i:s'))
            ->where('bs_reservations.time_end', '>', $start->format('H:i:s'));
    }
}
