<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationConsent;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSubscription;
use App\Models\PlayerSearch;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\Types\NotificationTypes;
use App\Services\PlayerExchangeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Recipients of the WhatsApp message of a new search. Starts from the exchange's own recipient query (enabled
 * accounts with e-mail and player-exchange=1, the booker excluded; no member record involved), so nobody outside
 * the exchange mail list is ever chosen, and narrows it to accounts with an active consent for `player_search`, a passing gate, a `player_search.opened`/whatsapp subscription (a
 * missing row means off) whose filters match, and no signup in this search yet.
 *
 * Filters are only applied when set: weekdays (ISO 1-7), start time window (H:i, both ends inclusive) and
 * player count (`quantities`). The court filter (`square_ids`) is not applied (open decision).
 */
final class RecipientResolver
{
    public const CHUNK_SIZE = 50;

    private const TIMEZONE = 'Europe/Vienna';

    public function __construct(
        private readonly PlayerExchangeService $exchange,
        private readonly ConsentService $consents,
    ) {}

    /**
     * Ordered by fewest messages this month (then by user id), so a tight budget is spread fairly.
     *
     * @return Collection<int, User>
     */
    public function forSearch(PlayerSearch $search): Collection
    {
        $search->loadMissing(['booking.reservations', 'booking.user', 'signups']);
        $booking = $search->booking;
        /** @var Reservation|null $first */
        $first = $booking?->reservations->sortBy(fn (Reservation $r): string => $r->date.' '.$r->time_start)->first();
        if ($booking === null || $first === null) {
            return collect();
        }

        $slot = [
            'weekday' => Carbon::parse($first->date)->dayOfWeekIso,
            'time' => substr((string) $first->time_start, 0, 5),
            'quantity' => (int) $booking->quantity,
        ];
        $joined = $search->signups->pluck('uid')->map(fn ($uid): int => (int) $uid)->all();

        $consented = NotificationConsent::query()
            ->select('user_id')
            ->where('channel', ConsentService::CHANNEL)
            ->where('purpose', NotificationConsent::PURPOSE_PLAYER_SEARCH)
            ->active();
        $subscribed = $this->subscriptions()->select('user_id');

        /** @var Collection<int, User> $found */
        $found = collect();
        $this->exchange->recipientsQuery(exclude: $booking->user)
            ->where('uid', '!=', (int) $booking->uid)
            ->whereNotIn('uid', $joined)
            ->whereIn('uid', $consented)
            ->whereIn('uid', $subscribed)
            ->chunkById(self::CHUNK_SIZE, function (EloquentCollection $users) use ($slot, $found): void {
                $filters = $this->subscriptions()
                    ->whereIn('user_id', $users->modelKeys())
                    ->get(['user_id', 'filters'])
                    ->mapWithKeys(fn (NotificationSubscription $s): array => [(int) $s->user_id => $s->filters]);

                foreach ($users as $user) {
                    /** @var User $user */
                    $userFilters = $filters->get((int) $user->uid);
                    if ($this->matches(is_array($userFilters) ? $userFilters : null, $slot) && $this->consents->gate($user)->passed) {
                        $found->push($user);
                    }
                }
            }, 'uid');

        $counts = $this->messagesThisMonth($found->map(fn (User $u): int => (int) $u->uid)->all());

        return $found
            ->sortBy([
                fn (User $a, User $b): int => ($counts[(int) $a->uid] ?? 0) <=> ($counts[(int) $b->uid] ?? 0),
                fn (User $a, User $b): int => (int) $a->uid <=> (int) $b->uid,
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @param  array{weekday: int, time: string, quantity: int}  $slot
     */
    private function matches(?array $filters, array $slot): bool
    {
        if ($filters === null || $filters === []) {
            return true;
        }

        $weekdays = array_map('intval', (array) ($filters['weekdays'] ?? []));
        if ($weekdays !== [] && ! in_array($slot['weekday'], $weekdays, true)) {
            return false;
        }

        $from = is_string($filters['time_from'] ?? null) ? $filters['time_from'] : '';
        $to = is_string($filters['time_to'] ?? null) ? $filters['time_to'] : '';
        if (($from !== '' && $slot['time'] < $from) || ($to !== '' && $slot['time'] > $to)) {
            return false;
        }

        $quantities = array_map('intval', (array) ($filters['quantities'] ?? []));

        return $quantities === [] || in_array($slot['quantity'], $quantities, true);
    }

    /**
     * Messages that went out to each user in the current Vienna month (admin tests not counted).
     *
     * @param  list<int>  $userIds
     * @return array<int, int>
     */
    private function messagesThisMonth(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $start = Carbon::now(self::TIMEZONE)->startOfMonth()->setTimezone((string) config('app.timezone'));
        $counts = [];
        foreach (array_chunk($userIds, 500) as $chunk) {
            $rows = NotificationLogEntry::query()
                ->selectRaw('user_id, count(*) as aggregate')
                ->whereIn('user_id', $chunk)
                ->whereIn('status', [NotificationLogEntry::SENT, NotificationLogEntry::DELIVERED, NotificationLogEntry::READ])
                ->where('type', '!=', NotificationTypes::ADMIN_TEST)
                ->where('sent_at', '>=', $start)
                ->groupBy('user_id')
                ->toBase()
                ->get();
            foreach ($rows as $row) {
                $counts[(int) $row->user_id] = (int) $row->aggregate;
            }
        }

        return $counts;
    }

    /** @return Builder<NotificationSubscription> */
    private function subscriptions(): Builder
    {
        return NotificationSubscription::query()
            ->where('type', NotificationTypes::PLAYER_SEARCH_OPENED)
            ->where('channel', ConsentService::CHANNEL);
    }
}
