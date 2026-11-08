<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Events\PlayerSearchStarted;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSubscription;
use App\Models\PlayerSearch;
use App\Models\PlayerSignup;
use App\Models\User;
use App\Models\UserMeta;
use App\Modules\Members\Models\Member;
use App\Notifications\Types\NotificationType;
use App\Services\Notifications\RecipientResolver;
use App\Services\PlayerExchangeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsPlayerSearchFixtures;
use Tests\TestCase;

/** Who gets the WhatsApp message of a new search: the exchange recipients narrowed by consent, gate and subscription. */
class RecipientResolverTest extends TestCase
{
    use BuildsPlayerSearchFixtures;
    use LazilyRefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        // Only the resolver is under test here; the automatic enqueueing stays out.
        Event::fake([PlayerSearchStarted::class]);
        $this->startClock();
        $this->owner = $this->searcher();
    }

    private function resolver(): RecipientResolver
    {
        return app(RecipientResolver::class);
    }

    /** Saturday 14.11. 18:00, doubles (4 players). */
    private function search(int $quantity = 4, int $needed = 2): PlayerSearch
    {
        return $this->searchOf($this->openSearch($this->owner, $quantity, $needed));
    }

    /** @return list<int> */
    private function uids(PlayerSearch $search): array
    {
        return $this->resolver()->forSearch($search)->pluck('uid')->map(fn ($id): int => (int) $id)->all();
    }

    #[Test]
    public function recipients_equal_recipients_query_plus_consent_gate_subscription(): void
    {
        $anna = $this->optedInUser('+43 660 0000001');
        $bert = $this->optedInUser('+43 660 0000002');
        $this->eligibleUser('+43 660 0000003');            // exchange, no WhatsApp consent
        $noSubscription = $this->optedInUser('+43 660 0000004');
        NotificationSubscription::query()->where('user_id', $noSubscription->uid)->delete();
        $this->optedInUser('+43 660 0000005')->update(['email' => '']);  // not an exchange recipient (no e-mail)

        $search = $this->search();
        $exchange = app(PlayerExchangeService::class)->recipientsQuery($this->owner)->pluck('uid')->all();

        $this->assertEqualsCanonicalizing([$anna->uid, $bert->uid], $this->uids($search));
        $this->assertEmpty(array_diff($this->uids($search), $exchange), 'never anyone outside recipientsQuery');
    }

    #[Test]
    public function the_booker_is_excluded_even_with_consent(): void
    {
        // The booker has no member record yet; give him one so only the exclusion can keep him out.
        $this->enableWhatsApp();
        Member::factory()->create(['user_id' => $this->owner->uid, 'status' => 'aktiv', 'left_at' => null, 'anonymized_at' => null]);
        $this->consents()->optIn($this->owner->fresh(), 'player_search', 'de');
        $anna = $this->optedInUser('+43 660 0000001');

        $this->assertSame([$anna->uid], $this->uids($this->search()));
    }

    /** @return iterable<string, array{0: string}> */
    public static function excludedCases(): iterable
    {
        yield 'without consent' => ['no_consent'];
        yield 'revoked consent' => ['revoked'];
        yield 'exchange off' => ['exchange_off'];
        yield 'member ruhend' => ['ruhend'];
        yield 'account blocked' => ['blocked'];
        yield 'no mobile number any more' => ['no_phone'];
        yield 'subscription switched off' => ['no_subscription'];
        yield 'already joined' => ['joined'];
    }

    #[Test]
    #[DataProvider('excludedCases')]
    public function excluded_members(string $case): void
    {
        $kept = $this->optedInUser('+43 660 0000001');
        $user = $case === 'no_consent' ? $this->eligibleUser('+43 660 0000002') : $this->optedInUser('+43 660 0000002');
        $search = $this->search();

        match ($case) {
            'no_consent' => null,
            'revoked' => $this->consents()->revoke($user, 'account'),
            // Raw update: the exchange switch must keep the user out even where no hook revoked the consent.
            'exchange_off' => UserMeta::query()->where('uid', $user->uid)->where('key', 'player-exchange')->update(['value' => '0']),
            'ruhend' => Member::query()->where('user_id', $user->uid)->update(['status' => 'ruhend']),
            'blocked' => User::query()->whereKey($user->uid)->update(['status' => 'blocked']),
            'no_phone' => UserMeta::query()->where('uid', $user->uid)->where('key', 'phone')->update(['value' => '']),
            'no_subscription' => NotificationSubscription::query()->where('user_id', $user->uid)->delete(),
            'joined' => PlayerSignup::create(['search_id' => $search->id, 'uid' => $user->uid, 'slot' => 2]),
        };

        $this->assertSame([$kept->uid], $this->uids($search->fresh()));
    }

    /** @return iterable<string, array{0: array<string, mixed>, 1: bool}> */
    public static function filterCases(): iterable
    {
        // The search: Saturday (ISO 6), 18:00, quantity 4.
        yield 'no filters' => [[], true];
        yield 'weekday matches' => [['weekdays' => [6, 7]], true];
        yield 'weekday does not match' => [['weekdays' => [1, 2, 3]], false];
        yield 'time window matches' => [['time_from' => '17:00', 'time_to' => '20:00'], true];
        yield 'time window inclusive start' => [['time_from' => '18:00', 'time_to' => '20:00'], true];
        yield 'time window inclusive end' => [['time_from' => '16:00', 'time_to' => '18:00'], true];
        yield 'time window too early' => [['time_from' => '08:00', 'time_to' => '12:00'], false];
        yield 'time window too late' => [['time_from' => '18:30', 'time_to' => '21:00'], false];
        yield 'quantity matches' => [['quantities' => [4]], true];
        yield 'quantity does not match' => [['quantities' => [2]], false];
        yield 'all filters match' => [['weekdays' => [6], 'time_from' => '17:00', 'time_to' => '19:00', 'quantities' => [2, 4]], true];
        yield 'one of all filters fails' => [['weekdays' => [6], 'time_from' => '17:00', 'time_to' => '19:00', 'quantities' => [2]], false];
        yield 'court filter is not applied' => [['square_ids' => [999999]], true];
    }

    /** @param  array<string, mixed>  $filters */
    #[Test]
    #[DataProvider('filterCases')]
    public function subscription_filters(array $filters, bool $expected): void
    {
        $user = $this->optedInUser('+43 660 0000001');
        $this->setFilters($user, $filters === [] ? null : $filters);

        $this->assertSame($expected ? [$user->uid] : [], $this->uids($this->search()));
    }

    #[Test]
    public function ordered_by_fewest_messages_this_month_then_by_id(): void
    {
        $busy = $this->optedInUser('+43 660 0000001');
        $quiet = $this->optedInUser('+43 660 0000002');
        $middle = $this->optedInUser('+43 660 0000003');
        $fresh = $this->optedInUser('+43 660 0000004');

        $this->sentRows($busy, 3);
        $this->sentRows($middle, 1);
        $this->sentRows($quiet, 2, Carbon::parse('2026-10-30 12:00:00'));          // last month: does not count
        $this->sentRows($fresh, 2, null, NotificationLogEntry::SKIPPED);            // not sent: does not count
        $this->sentRows($fresh, 5, null, NotificationLogEntry::SENT, NotificationType::AdminTest); // admin test: does not count

        $this->assertSame([$quiet->uid, $fresh->uid, $middle->uid, $busy->uid], $this->uids($this->search()));
    }

    #[Test]
    public function recipients_are_read_in_chunks_without_losing_anyone(): void
    {
        $expected = [];
        for ($i = 0; $i < RecipientResolver::CHUNK_SIZE + 3; $i++) {
            $expected[] = $this->optedInUser('+43 660 1'.str_pad((string) $i, 6, '0', STR_PAD_LEFT))->uid;
        }

        $this->assertEqualsCanonicalizing($expected, $this->uids($this->search()));
    }

    private function sentRows(User $user, int $count, ?Carbon $at = null, string $status = NotificationLogEntry::SENT, NotificationType $type = NotificationType::PlayerSearchOpened): void
    {
        $at ??= Carbon::parse('2026-11-05 12:00:00');
        for ($i = 0; $i < $count; $i++) {
            NotificationLogEntry::create([
                'type' => $type->value,
                'channel' => 'whatsapp',
                'user_id' => $user->uid,
                'subject_type' => 'player_search',
                'subject_id' => 0,
                'dedupe_key' => 'test:'.Str::uuid()->toString(),
                'status' => $status,
                'priority' => $type->priority(),
                'attempts' => 0,
                'not_before' => $at,
                'sent_at' => $status === NotificationLogEntry::SENT ? $at : null,
            ]);
        }
    }
}
