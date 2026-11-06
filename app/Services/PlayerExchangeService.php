<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PlayerExchangeException;
use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
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
        return User::query()
            ->active()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('meta', fn ($q) => $q->where('key', self::META_KEY)->where('value', '1'))
            ->when($exclude, fn ($q) => $q->where('uid', '!=', $exclude->uid))
            ->orderBy('uid')
            ->get();
    }
}
