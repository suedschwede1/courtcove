<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Events\PlayerSearchClosed;
use App\Models\NotificationLogEntry;
use App\Notifications\Types\NotificationType;
use App\Services\BookingService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\Resolvers\PlayerSearchClosedSubjectCheck;
use App\Services\Notifications\Resolvers\PlayerSearchSubjectCheck;
use App\Services\Notifications\SubjectDeadline;
use App\Services\Notifications\SubjectResolverRegistry;
use App\Services\Notifications\Transport\FakeTransport;
use App\Services\PlayerExchangeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsPlayerSearchFixtures;
use Tests\TestCase;

/** Send-time re-check of a search (2-minute debounce, 15-minute lead) and the optional closed follow-up. */
class PlayerSearchDebounceTest extends TestCase
{
    use BuildsPlayerSearchFixtures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->startClock();
        $this->readyToSend();
    }

    private function dispatch(): void
    {
        app(NotificationDispatcher::class)->run();
    }

    private function fake(): FakeTransport
    {
        return app(FakeTransport::class);
    }

    private function exchange(): PlayerExchangeService
    {
        return app(PlayerExchangeService::class);
    }

    #[Test]
    public function both_search_types_have_a_registered_resolver_with_a_deadline(): void
    {
        $registry = app(SubjectResolverRegistry::class);

        $this->assertInstanceOf(PlayerSearchSubjectCheck::class, $registry->for(NotificationType::PlayerSearchOpened));
        $this->assertInstanceOf(PlayerSearchClosedSubjectCheck::class, $registry->for(NotificationType::PlayerSearchClosed));
        $this->assertInstanceOf(SubjectDeadline::class, $registry->for(NotificationType::PlayerSearchOpened));
    }

    #[Test]
    public function an_open_search_is_sent_after_two_minutes_with_its_variables(): void
    {
        $this->optedInUser('+43 660 0000001');
        $booking = $this->openSearch($this->searcher());

        $this->dispatch();
        $this->assertSame([], $this->fake()->sent(), 'nothing is due before two minutes');

        $this->travel(2)->minutes();
        $this->dispatch();

        $sent = $this->fake()->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('+436600000001', $sent[0]['to']);
        $this->assertSame('HXtest-opened', $sent[0]['template']->contentId);
        $this->assertSame('Erika Musterfrau', $sent[0]['vars']['5']);
        $this->assertSame('calendar?date=2026-11-14&join='.$booking->bid, $sent[0]['buttonSuffix']);
        $this->assertSame(NotificationLogEntry::SENT, $this->openedRows()->sole()->status);
    }

    #[Test]
    public function a_search_ended_within_two_minutes_sends_nothing(): void
    {
        $this->optedInUser('+43 660 0000001');
        $owner = $this->searcher();
        $booking = $this->openSearch($owner);

        $this->travel(1)->minutes();
        $this->exchange()->endSearch($owner, $booking);
        $this->travel(2)->minutes();
        $this->dispatch();

        $this->assertSame([], $this->fake()->sent());
        $this->assertSkippedGone($this->openedRows()->sole());
    }

    #[Test]
    public function a_cancelled_booking_within_two_minutes_sends_nothing(): void
    {
        $this->optedInUser('+43 660 0000001');
        $booking = $this->openSearch($this->searcher());

        app(BookingService::class)->cancelSingle($booking->fresh());
        $this->travel(3)->minutes();
        $this->dispatch();

        $this->assertSame([], $this->fake()->sent());
        $this->assertSkippedGone($this->openedRows()->sole());
    }

    #[Test]
    public function a_deleted_booking_within_two_minutes_sends_nothing(): void
    {
        $this->optedInUser('+43 660 0000001');
        $booking = $this->openSearch($this->searcher());

        app(BookingService::class)->deleteSingle($booking->fresh());
        $this->travel(3)->minutes();
        $this->dispatch();

        $this->assertSame([], $this->fake()->sent());
        $this->assertSkippedGone($this->openedRows()->sole());
    }

    #[Test]
    public function a_search_filled_within_two_minutes_sends_nothing_to_the_others(): void
    {
        $this->optedInUser('+43 660 0000001');
        $joiner = $this->optedInUser('+43 660 0000002');
        $booking = $this->openSearch($this->searcher(), quantity: 2, needed: 1);

        $this->exchange()->join($joiner, $booking);
        $this->travel(3)->minutes();
        $this->dispatch();

        $this->assertSame([], $this->fake()->sent());
        $this->openedRows()->each(fn (NotificationLogEntry $row) => $this->assertSkippedGone($row));
    }

    #[Test]
    public function a_search_whose_deadline_is_only_fourteen_minutes_away_is_skipped(): void
    {
        $this->optedInUser('+43 660 0000001');
        // Start 10:00, deadline 09:00; at 08:46 it is 14 minutes away.
        $this->openSearch($this->searcher(), start: Carbon::parse('2026-11-12 10:00:00'));

        $this->travelTo(Carbon::parse('2026-11-12 08:46:00'));
        $this->dispatch();

        $this->assertSame([], $this->fake()->sent());
        $this->assertSkippedGone($this->openedRows()->sole());
    }

    #[Test]
    public function a_search_whose_deadline_is_sixteen_minutes_away_is_still_sent(): void
    {
        $this->optedInUser('+43 660 0000001');
        $this->openSearch($this->searcher(), start: Carbon::parse('2026-11-12 10:00:00'));

        $this->travelTo(Carbon::parse('2026-11-12 08:44:00'));
        $this->dispatch();

        $this->assertCount(1, $this->fake()->sent());
    }

    #[Test]
    public function the_deadline_of_an_opened_row_is_the_end_of_the_search(): void
    {
        $this->optedInUser('+43 660 0000001');
        $search = $this->searchOf($this->openSearch($this->searcher()));

        $deadline = app(PlayerSearchSubjectCheck::class)->deadline($this->openedRows()->sole());

        $this->assertNotNull($deadline);
        $this->assertTrue($deadline->equalTo($search->ends_at));
    }

    #[Test]
    public function the_closed_follow_up_is_off_by_default(): void
    {
        $this->optedInUser('+43 660 0000001');
        $owner = $this->searcher();
        $booking = $this->openSearch($owner);
        $this->travel(2)->minutes();
        $this->dispatch();

        $this->exchange()->endSearch($owner, $booking);
        $this->travel(10)->minutes();
        $this->dispatch();

        $this->assertCount(0, $this->closedRows());
        $this->assertCount(1, $this->fake()->sent());
    }

    #[Test]
    public function the_closed_follow_up_goes_only_to_members_whose_opened_message_was_sent(): void
    {
        $this->setOption('whatsapp.followup_closed', '1');
        $sentTo = $this->optedInUser('+43 660 0000001');
        $pendingFor = $this->optedInUser('+43 660 0000002');
        $owner = $this->searcher();
        $booking = $this->openSearch($owner);
        $search = $this->searchOf($booking);
        $this->travel(2)->minutes();
        app(NotificationDispatcher::class)->run(max: 1);   // the first row only
        $rows = $this->openedRows()->keyBy('user_id');
        $this->assertSame(NotificationLogEntry::SENT, $rows[$sentTo->uid]->status);
        $this->assertSame(NotificationLogEntry::PENDING, $rows[$pendingFor->uid]->status);

        $this->exchange()->endSearch($owner, $booking);

        // The pending opened row is skipped instead of getting a follow-up.
        $this->assertSkippedGone($this->openedRows()->firstWhere('user_id', $pendingFor->uid));
        $closed = $this->closedRows()->sole();
        $this->assertSame($sentTo->uid, $closed->user_id);
        $this->assertSame("player_search.closed:{$search->id}:{$sentTo->uid}:whatsapp", $closed->dedupe_key);
        $this->assertTrue($closed->not_before->equalTo(Carbon::now()->addMinutes(5)));

        // Fired again (another closing path): still one follow-up per member and search.
        event(new PlayerSearchClosed($search->id, 'ended'));
        $this->assertCount(1, $this->closedRows());

        $this->travel(5)->minutes();
        $this->dispatch();
        $sent = $this->fake()->sent();
        $this->assertCount(2, $sent);
        $this->assertSame('HXtest-closed', $sent[1]['template']->contentId);
        $this->assertSame(['1' => 'Sa, 14.11.', '2' => '18:00', '3' => 'Platz Test'], $sent[1]['vars']);
        $this->assertNull($sent[1]['buttonSuffix']);
        $this->assertSame(NotificationLogEntry::SENT, $this->closedRows()->sole()->status);
    }

    #[Test]
    public function a_search_open_again_within_five_minutes_gets_no_closed_follow_up(): void
    {
        $this->setOption('whatsapp.followup_closed', '1');
        $this->optedInUser('+43 660 0000001');
        $joiner = $this->optedInUser('+43 660 0000002');
        $booking = $this->openSearch($this->searcher(), quantity: 2, needed: 1);
        $this->travel(3)->minutes();
        $this->dispatch();
        $this->assertCount(2, $this->fake()->sent());

        $this->exchange()->join($joiner, $booking);   // filled: follow-ups enqueued
        $this->assertCount(2, $this->closedRows());
        $this->exchange()->leave($joiner, $booking);  // open again within the debounce
        $this->travel(5)->minutes();
        $this->dispatch();

        $this->assertCount(2, $this->fake()->sent());
        $this->closedRows()->each(fn (NotificationLogEntry $row) => $this->assertSkippedGone($row));
    }

    private function assertSkippedGone(?NotificationLogEntry $row): void
    {
        $this->assertNotNull($row);
        $row = $row->fresh();
        $this->assertSame(NotificationLogEntry::SKIPPED, $row->status);
        $this->assertSame(NotificationLogEntry::SUBJECT_GONE, $row->skip_reason);
    }
}
