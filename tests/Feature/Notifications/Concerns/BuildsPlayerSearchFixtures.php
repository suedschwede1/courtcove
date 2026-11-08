<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications\Concerns;

use App\Models\Booking;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSubscription;
use App\Models\Option;
use App\Models\PlayerSearch;
use App\Models\Square;
use App\Models\User;
use App\Notifications\Types\NotificationType;
use App\Services\BookingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Fixtures for the WhatsApp player search tests. Clock: Thursday 2026-11-12 08:00 (Vienna); the default
 * search is for Saturday 2026-11-14 18:00, so its deadline is 17:00 that day. Names and numbers are fictitious.
 */
trait BuildsPlayerSearchFixtures
{
    use BuildsConsentFixtures;

    protected function startClock(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 08:00:00', 'Europe/Vienna'));
    }

    protected function setOption(string $key, string $value): void
    {
        Option::query()->where('key', $key)->delete();
        Option::create(['key' => $key, 'value' => $value, 'locale' => null]);
    }

    /** Main switch on, fake driver, templates and budget so the dispatcher can send. */
    protected function readyToSend(): void
    {
        $this->enableWhatsApp();
        foreach ([
            'whatsapp.driver' => 'fake',
            'whatsapp.budget_monthly' => '10.00',
            'whatsapp.price_estimate' => '0.05',
            'whatsapp.daily_limit_per_user' => '10',
            'whatsapp.template.player_search_opened.de' => 'HXtest-opened',
            'whatsapp.template.player_search_closed.de' => 'HXtest-closed',
        ] as $key => $value) {
            $this->setOption($key, $value);
        }
    }

    /** The booker: in the exchange, with first and last name. */
    protected function searcher(): User
    {
        $owner = User::factory()->create(['status' => 'enabled', 'email' => 'owner@example.test']);
        $owner->setMeta('firstname', 'Erika');
        $owner->setMeta('lastname', 'Musterfrau');
        $owner->setMeta('phone', '+43 660 0000099');
        $owner->setMeta('player-exchange', '1');

        return $owner->fresh();
    }

    protected function court(): Square
    {
        $square = Square::factory()->create(['capacity' => 4]);
        $square->setMeta('alias', 'Platz Test');

        return $square->fresh();
    }

    protected function openSearch(User $owner, int $quantity = 4, int $needed = 2, ?Carbon $start = null, ?Square $square = null): Booking
    {
        $start ??= Carbon::parse('2026-11-14 18:00:00', 'Europe/Vienna');

        return app(BookingService::class)->createSingle(
            $owner, $square ?? $this->court(), $quantity, $start, $start->copy()->addHour(), [], $needed,
        );
    }

    protected function searchOf(Booking $booking): PlayerSearch
    {
        return PlayerSearch::query()->where('bid', $booking->bid)->firstOrFail();
    }

    /** @param  array<string, mixed>|null  $filters */
    protected function setFilters(User $user, ?array $filters): void
    {
        NotificationSubscription::query()
            ->where('user_id', $user->uid)
            ->where('type', NotificationType::PlayerSearchOpened->value)
            ->update(['filters' => $filters === null ? null : json_encode($filters)]);
    }

    protected function openedRows(?int $searchId = null): Collection
    {
        return NotificationLogEntry::query()
            ->where('type', NotificationType::PlayerSearchOpened->value)
            ->when($searchId !== null, fn ($q) => $q->where('subject_id', $searchId))
            ->orderBy('not_before')
            ->orderBy('id')
            ->get();
    }

    protected function closedRows(): Collection
    {
        return NotificationLogEntry::query()->where('type', NotificationType::PlayerSearchClosed->value)->orderBy('id')->get();
    }
}
