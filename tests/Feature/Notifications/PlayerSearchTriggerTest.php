<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Events\PlayerSearchClosed;
use App\Events\PlayerSearchStarted;
use App\Mail\PlayerSearchOpened;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSubscription;
use App\Models\PlayerSearch;
use App\Models\User;
use App\Notifications\Types\NotificationTypes;
use App\Services\BookingService;
use App\Services\Notifications\ConsentService;
use App\Services\Notifications\RecipientResolver;
use App\Services\Notifications\Resolvers\PlayerSearchSubjectCheck;
use App\Services\PlayerExchangeService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Feature\Notifications\Concerns\BuildsPlayerSearchFixtures;
use Tests\TestCase;

/** A new player search enqueues WhatsApp rows next to the unchanged exchange mail; closing events. */
class PlayerSearchTriggerTest extends TestCase
{
    use BuildsPlayerSearchFixtures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->startClock();
    }

    /** Seam for the web branch of PlayerSearchEvents (the test runner itself always runs in the console). */
    private function setRunningInConsole(bool $value): void
    {
        (fn () => $this->isRunningInConsole = $value)->call($this->app);
    }

    private function configureMail(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com']);
    }

    #[Test]
    public function opening_a_search_enqueues_one_row_per_eligible_member_with_not_before_two_minutes(): void
    {
        $owner = $this->searcher();
        $anna = $this->optedInUser('+43 660 0000001');
        $bert = $this->optedInUser('+43 660 0000002');

        $booking = $this->openSearch($owner);
        $search = $this->searchOf($booking);

        $rows = $this->openedRows();
        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing([$anna->uid, $bert->uid], $rows->pluck('user_id')->all());

        $due = Carbon::now()->addMinutes(2);
        foreach ($rows as $index => $row) {
            $this->assertSame(NotificationLogEntry::PENDING, $row->status);
            $this->assertSame('player_search', $row->subject_type);
            $this->assertSame($search->id, $row->subject_id);
            $this->assertSame("player_search.opened:{$search->id}:{$row->user_id}:whatsapp", $row->dedupe_key);
            // Staggered by one second per recipient, starting at now + 2 minutes.
            $this->assertTrue($row->not_before->equalTo($due->copy()->addSeconds($index)), 'not_before of row '.$index);
            $this->assertSame(['search_id' => $search->id, 'bid' => $booking->bid], $row->subject_payload);
        }
    }

    #[Test]
    public function mail_job_is_still_dispatched_and_unchanged(): void
    {
        $this->configureMail();
        $owner = $this->searcher();
        $this->optedInUser('+43 660 0000001');
        $this->member('plain@example.test');

        $this->openSearch($owner);
        $this->app->terminate();

        // Mail goes to every exchange member (WhatsApp consent plays no role there), WhatsApp only to the opted-in one.
        Mail::assertQueued(PlayerSearchOpened::class, 2);
        Mail::assertQueued(PlayerSearchOpened::class, fn (PlayerSearchOpened $m) => $m->hasTo('plain@example.test'));
        $this->assertCount(1, $this->openedRows());
    }

    #[Test]
    public function in_a_web_request_the_rows_are_written_only_after_the_response(): void
    {
        $owner = $this->searcher();
        $this->optedInUser('+43 660 0000001');
        $this->optedInUser('+43 660 0000002');
        $this->setRunningInConsole(false);

        try {
            $search = $this->searchOf($this->openSearch($owner));
            $this->assertCount(0, $this->openedRows(), 'nothing during the request');

            $this->app->terminate();
        } finally {
            $this->setRunningInConsole(true);
        }

        $this->assertCount(2, $this->openedRows($search->id));
    }

    #[Test]
    public function whatsapp_is_enqueued_even_when_mail_is_not_configured(): void
    {
        $owner = $this->searcher();
        $this->optedInUser('+43 660 0000001');

        $this->openSearch($owner);
        $this->app->terminate();

        Mail::assertNothingQueued();
        $this->assertCount(1, $this->openedRows());
    }

    #[Test]
    public function whatsapp_failure_never_breaks_booking_or_mail(): void
    {
        Exceptions::fake();
        $this->configureMail();
        $owner = $this->searcher();
        $this->optedInUser('+43 660 0000001');
        // The resolver's query fails inside the listener (table gone): caught and reported there.
        Schema::drop('notification_subscriptions');

        $booking = $this->openSearch($owner);
        $this->app->terminate();

        $this->assertSame(PlayerSearch::OPEN, $this->searchOf($booking)->status);
        Mail::assertQueued(PlayerSearchOpened::class, 1);
        $this->assertCount(0, $this->openedRows());
        Exceptions::assertReported(QueryException::class);
    }

    #[Test]
    public function a_listener_that_cannot_even_be_built_is_reported_and_the_booking_stays(): void
    {
        Exceptions::fake();
        $this->configureMail();
        $owner = $this->searcher();
        $this->optedInUser('+43 660 0000001');
        $this->app->bind(RecipientResolver::class, fn () => throw new RuntimeException('resolver broken'));

        $booking = $this->openSearch($owner);
        $this->app->terminate();

        $this->assertSame(PlayerSearch::OPEN, $this->searchOf($booking)->status);
        Mail::assertQueued(PlayerSearchOpened::class, 1);
        $this->assertCount(0, $this->openedRows());
        Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'resolver broken');
    }

    #[Test]
    public function a_throwing_listener_of_the_event_is_reported_and_the_booking_stays(): void
    {
        Exceptions::fake();
        $this->configureMail();
        $owner = $this->searcher();
        Event::listen(PlayerSearchStarted::class, fn () => throw new RuntimeException('listener broken'));

        $booking = $this->openSearch($owner);
        $this->app->terminate();

        $this->assertSame(PlayerSearch::OPEN, $this->searchOf($booking)->status);
        Mail::assertNotQueued(PlayerSearchOpened::class);
        Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'listener broken');
    }

    #[Test]
    public function a_booking_without_search_fires_no_event(): void
    {
        Event::fake([PlayerSearchStarted::class]);
        $owner = $this->searcher();
        $start = Carbon::parse('2026-11-14 18:00:00');

        app(BookingService::class)->createSingle($owner, $this->court(), 2, $start, $start->copy()->addHour());

        Event::assertNotDispatched(PlayerSearchStarted::class);
    }

    #[Test]
    public function the_started_event_carries_the_search_id(): void
    {
        Event::fake([PlayerSearchStarted::class]);
        $booking = $this->openSearch($this->searcher());

        Event::assertDispatched(PlayerSearchStarted::class, fn (PlayerSearchStarted $e) => $e->searchId === $this->searchOf($booking)->id);
    }

    #[Test]
    public function dedupe_key_prevents_double_rows_when_event_fires_twice(): void
    {
        $owner = $this->searcher();
        $this->optedInUser('+43 660 0000001');
        $this->optedInUser('+43 660 0000002');
        $search = $this->searchOf($this->openSearch($owner));

        event(new PlayerSearchStarted($search->id));
        event(new PlayerSearchStarted($search->id));

        $this->assertCount(2, $this->openedRows());
    }

    #[Test]
    public function nothing_is_enqueued_while_the_main_switch_is_off(): void
    {
        $owner = $this->searcher();
        $this->optedInUser('+43 660 0000001');
        $this->setOption('whatsapp.enabled', '0');

        $this->openSearch($owner);

        $this->assertCount(0, $this->openedRows());
    }

    #[Test]
    public function variables_contain_full_name_but_no_phone_or_email(): void
    {
        $owner = $this->searcher();
        $anna = $this->optedInUser('+43 660 0000001');
        $booking = $this->openSearch($owner, needed: 2);
        $row = $this->openedRows()->sole();

        $content = app(PlayerSearchSubjectCheck::class)->build($row, $anna, 'de');

        $this->assertSame([
            '1' => 'Sa, 14.11.',
            '2' => '18:00',
            '3' => 'Platz Test',
            '4' => '2',
            '5' => 'Erika Musterfrau',
        ], $content->variables);
        $this->assertSame('calendar?date=2026-11-14&join='.$booking->bid, $content->buttonSuffix);
        $this->assertStringContainsString('Erika Musterfrau', $content->freeText);
        $this->assertStringContainsString('calendar?date=2026-11-14&join='.$booking->bid, $content->freeText);

        $everything = json_encode([$content->variables, $content->buttonSuffix, $content->freeText]).json_encode($row->getAttributes());
        foreach (['0000099', '0000001', '@example', '+43'] as $secret) {
            $this->assertStringNotContainsString($secret, $everything);
        }
        // The outbox row holds keys only, never the name.
        $this->assertStringNotContainsString('Musterfrau', json_encode($row->getAttributes()));
    }

    #[Test]
    public function the_english_text_uses_the_english_date(): void
    {
        $anna = $this->optedInUser('+43 660 0000001');
        $this->openSearch($this->searcher());

        $content = app(PlayerSearchSubjectCheck::class)->build($this->openedRows()->sole(), $anna, 'en');

        $this->assertSame('Sat, 14.11.', $content->variables['1']);
        $this->assertStringContainsString('Erika Musterfrau', $content->freeText);
    }

    #[Test]
    public function the_searcher_alias_is_used_when_first_and_last_name_are_missing(): void
    {
        $owner = $this->searcher();
        $owner->setMeta('firstname', null);
        $owner->setMeta('lastname', null);
        $owner->update(['alias' => 'Erika M.']);
        $anna = $this->optedInUser('+43 660 0000001');
        $this->openSearch($owner->fresh());

        $content = app(PlayerSearchSubjectCheck::class)->build($this->openedRows()->sole(), $anna, 'de');

        $this->assertSame('Erika M.', $content->variables['5']);
    }

    #[Test]
    public function closing_paths_fire_the_closed_event_with_their_reason(): void
    {
        Event::fake([PlayerSearchClosed::class]);
        $exchange = app(PlayerExchangeService::class);
        $owner = $this->searcher();

        $ended = $this->openSearch($owner);
        $exchange->endSearch($owner, $ended);

        $filled = $this->openSearch($owner, quantity: 2, needed: 1, start: Carbon::parse('2026-11-15 18:00:00'));
        $exchange->join($this->optedInUser('+43 660 0000001'), $filled);

        $cancelled = $this->openSearch($owner, start: Carbon::parse('2026-11-16 18:00:00'));
        app(BookingService::class)->cancelSingle($cancelled->fresh());

        $lapsed = $this->openSearch($owner, start: Carbon::parse('2026-11-12 12:00:00'));
        $this->travelTo(Carbon::parse('2026-11-12 11:01:00'));
        $exchange->lapseExpired();

        $reasons = [];
        Event::assertDispatched(PlayerSearchClosed::class, function (PlayerSearchClosed $e) use (&$reasons): bool {
            $reasons[$e->searchId] = $e->reason;

            return true;
        });
        $this->assertSame([
            $this->searchOf($ended)->id => 'ended',
            $this->searchOf($filled)->id => 'filled',
            $this->searchOf($cancelled)->id => 'cancelled',
            $this->searchOf($lapsed)->id => 'lapsed',
        ], $reasons);
    }

    #[Test]
    public function an_owner_edit_that_leaves_nothing_to_search_fires_the_closed_event(): void
    {
        Event::fake([PlayerSearchClosed::class]);
        $owner = $this->searcher();
        $booking = $this->openSearch($owner, quantity: 2, needed: 1);

        app(BookingService::class)->updateSinglePlayers($booking->fresh(), $owner, 2, [2 => 'Partner Test']);

        $search = $this->searchOf($booking);
        $this->assertSame(PlayerSearch::ENDED, $search->status);
        Event::assertDispatched(PlayerSearchClosed::class, fn (PlayerSearchClosed $e) => $e->searchId === $search->id && $e->reason === 'ended');
    }

    #[Test]
    public function a_rolled_back_close_fires_no_event(): void
    {
        Event::fake([PlayerSearchClosed::class]);
        $owner = $this->searcher();
        $booking = $this->openSearch($owner);

        try {
            DB::transaction(function () use ($owner, $booking): void {
                app(PlayerExchangeService::class)->endSearch($owner, $booking);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(PlayerSearch::OPEN, $this->searchOf($booking)->status);
        Event::assertNotDispatched(PlayerSearchClosed::class);
    }

    #[Test]
    public function revoking_the_consent_also_removes_a_closed_subscription(): void
    {
        $user = $this->optedInUser('+43 660 0000001');
        NotificationSubscription::create(['user_id' => $user->uid, 'type' => NotificationTypes::PLAYER_SEARCH_CLOSED, 'channel' => 'whatsapp']);

        app(ConsentService::class)->revoke($user, 'account', 'player_search');

        $this->assertSame(0, NotificationSubscription::query()->where('user_id', $user->uid)->count());
    }

    #[Test]
    public function opting_in_still_creates_only_the_opened_subscription(): void
    {
        $user = $this->optedInUser('+43 660 0000001');

        $this->assertSame(
            [NotificationTypes::PLAYER_SEARCH_OPENED],
            NotificationSubscription::query()->where('user_id', $user->uid)->pluck('type')->all(),
        );
    }

    private function member(string $email): User
    {
        $user = User::factory()->create(['status' => 'enabled', 'email' => $email]);
        $user->setMeta('player-exchange', '1');

        return $user;
    }
}
