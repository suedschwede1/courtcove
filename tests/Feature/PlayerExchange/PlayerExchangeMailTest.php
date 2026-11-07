<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Jobs\SendPlayerSearchMails;
use App\Mail\PlayerSearchBookingCancelled;
use App\Mail\PlayerSearchExpired;
use App\Mail\PlayerSearchJoined;
use App\Mail\PlayerSearchOpened;
use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PlayerExchangeService;
use App\Support\Mail\SystemMailSender;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerExchangeMailTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Carbon $start;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-06 08:00:00'));
        $this->start = Carbon::parse('2026-11-08 10:00:00');
    }

    /** Mails are only dispatched when a real SMTP mailer is configured. */
    private function configureMail(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.com',
        ]);
    }

    /** Mail dispatch is deferred via afterResponse(); run the terminating callbacks. */
    private function runDeferredJobs(): void
    {
        $this->app->terminate();
    }

    private function exchange(): PlayerExchangeService
    {
        return app(PlayerExchangeService::class);
    }

    private function runJob(int $searchId): void
    {
        (new SendPlayerSearchMails($searchId))->handle($this->exchange(), app(BookingService::class));
    }

    private function member(string $email, string $alias = 'Max Muster', string $status = 'enabled', bool $optIn = true): User
    {
        $user = User::factory()->create(['email' => $email, 'alias' => $alias, 'status' => $status]);
        if ($optIn) {
            $this->exchange()->optIn($user);
        }

        return $user;
    }

    private function searchingBooking(User $owner, int $quantity = 4, int $needed = 2, ?Square $square = null): Booking
    {
        return app(BookingService::class)->createSingle(
            $owner, $square ?? Square::factory()->create(), $quantity, $this->start, $this->start->copy()->addHour(), [], $needed,
        );
    }

    #[Test]
    public function opening_a_search_mails_opted_in_active_members_but_not_the_owner(): void
    {
        $this->configureMail();
        $owner = $this->member('owner@example.com', 'Olga Owner');
        $this->member('anna@example.com', 'Anna');
        $this->member('bert@example.com', 'Bert');
        $this->member('carl@example.com', 'Carl', optIn: false);

        $this->searchingBooking($owner);
        $this->runDeferredJobs();

        Mail::assertQueued(PlayerSearchOpened::class, 2);
        Mail::assertQueued(PlayerSearchOpened::class, fn (PlayerSearchOpened $m) => $m->hasTo('anna@example.com'));
        Mail::assertQueued(PlayerSearchOpened::class, fn (PlayerSearchOpened $m) => $m->hasTo('bert@example.com'));
        Mail::assertNotQueued(PlayerSearchOpened::class, fn (PlayerSearchOpened $m) => $m->hasTo('owner@example.com'));
        Mail::assertNotQueued(PlayerSearchOpened::class, fn (PlayerSearchOpened $m) => $m->hasTo('carl@example.com'));
    }

    #[Test]
    public function a_booking_without_search_sends_no_exchange_mail(): void
    {
        $this->configureMail();
        $owner = $this->member('owner@example.com');
        $this->member('anna@example.com', 'Anna');

        app(BookingService::class)->createSingle(
            $owner, Square::factory()->create(), 2, $this->start, $this->start->copy()->addHour(),
        );
        $this->runDeferredJobs();

        Mail::assertNotQueued(PlayerSearchOpened::class);
    }

    #[Test]
    public function blocked_members_get_no_mail(): void
    {
        $this->configureMail();
        $owner = $this->member('owner@example.com', 'Olga Owner');
        $this->member('anna@example.com', 'Anna');
        $this->member('blocked@example.com', 'Blocked', status: 'blocked');
        $this->member('waiting@example.com', 'Waiting', status: 'disabled');
        $this->member('deleted@example.com', 'Deleted', status: 'deleted');

        $this->searchingBooking($owner);
        $this->runDeferredJobs();

        Mail::assertQueued(PlayerSearchOpened::class, 1);
        Mail::assertQueued(PlayerSearchOpened::class, fn (PlayerSearchOpened $m) => $m->hasTo('anna@example.com'));
    }

    #[Test]
    public function recipients_are_mailed_in_chunks_without_losing_anyone(): void
    {
        $this->configureMail();
        $owner = $this->member('owner@example.com', 'Olga Owner');
        for ($i = 1; $i <= 53; $i++) {
            $this->member("member{$i}@example.com", "Member {$i}");
        }

        $this->searchingBooking($owner);
        $this->runDeferredJobs();

        Mail::assertQueued(PlayerSearchOpened::class, 53);
        Mail::assertQueued(PlayerSearchOpened::class, fn (PlayerSearchOpened $m) => $m->hasTo('member1@example.com'));
        Mail::assertQueued(PlayerSearchOpened::class, fn (PlayerSearchOpened $m) => $m->hasTo('member53@example.com'));
    }

    #[Test]
    public function a_search_no_longer_open_or_gone_sends_nothing(): void
    {
        $this->configureMail();
        $owner = $this->member('owner@example.com', 'Olga Owner');
        $this->member('anna@example.com', 'Anna');
        $booking = $this->searchingBooking($owner);
        $search = PlayerSearch::where('bid', $booking->bid)->sole();

        $search->update(['status' => PlayerSearch::ENDED]);
        $this->runDeferredJobs();
        $this->runJob($search->id);
        $this->runJob(999999);

        Mail::assertNotQueued(PlayerSearchOpened::class);
    }

    #[Test]
    public function opened_mail_contains_slot_time_needed_count_and_unsubscribe_link(): void
    {
        $owner = $this->member('owner@example.com', 'Olga Owner');
        $anna = $this->member('anna@example.com', 'Anna');
        $square = Square::factory()->create();
        $booking = $this->searchingBooking($owner, 4, 3, $square);
        $search = PlayerSearch::where('bid', $booking->bid)->sole();

        $mail = new PlayerSearchOpened($search, $anna);
        $this->assertInstanceOf(ShouldQueue::class, $mail);

        $subject = $mail->envelope()->subject;
        $this->assertStringContainsString($square->display_name, $subject);
        $this->assertStringContainsString('10:00', $subject);

        $html = $mail->render();
        $this->assertStringContainsString(e($square->display_name), $html);
        $this->assertStringContainsString('8. November 2026', $html);
        $this->assertStringContainsString('10:00', $html);
        $this->assertStringContainsString('11:00', $html);
        $this->assertStringContainsString(e(trans_choice('booking.mail.player_search.needed', 3, ['count' => 3])), $html);
        $this->assertStringContainsString(e(route('calendar.index', ['date' => '2026-11-08'])), $html);
        $this->assertStringContainsString(e($this->exchange()->unsubscribeUrl($anna)), $html);
        $this->assertStringContainsString(e(__('booking.mail.player_search.greeting', ['name' => 'Anna'])), $html);
        $this->assertStringNotContainsString('player_search.', $html, 'no untranslated keys');

        $this->assertEquals(app(SystemMailSender::class)->replyTo(SystemMailSender::SCOPE_BOOKING), $mail->envelope()->replyTo);
    }

    #[Test]
    public function opened_mail_unsubscribe_link_opts_the_recipient_out(): void
    {
        $owner = $this->member('owner@example.com', 'Olga Owner');
        $anna = $this->member('anna@example.com', 'Anna');
        $booking = $this->searchingBooking($owner);
        $search = PlayerSearch::where('bid', $booking->bid)->sole();

        $html = (new PlayerSearchOpened($search, $anna))->render();
        $this->assertSame(1, preg_match('#href="([^"]*/player-exchange/unsubscribe/[^"]*)"#', $html, $m));

        $this->get(html_entity_decode($m[1]))->assertOk();
        $this->assertFalse($this->exchange()->isOptedIn($anna->fresh()));
    }

    #[Test]
    public function join_mails_the_owner(): void
    {
        $this->configureMail();
        $owner = $this->member('owner@example.com', 'Olga Owner');
        $booking = $this->searchingBooking($owner, 4, 2);
        $max = $this->member('max@example.com', 'Max Muster');
        $this->runDeferredJobs();

        // The test request terminates the app itself, which runs the after-response mail.
        $this->actingAs($max)->post(route('player-exchange.join', $booking))->assertRedirect();

        Mail::assertQueued(PlayerSearchJoined::class, 1);
        Mail::assertQueued(PlayerSearchJoined::class, function (PlayerSearchJoined $m): bool {
            $html = $m->render();

            return $m->hasTo('owner@example.com')
                && str_contains($html, 'Max Muster')
                && str_contains($html, e(trans_choice('booking.mail.player_search.needed', 1, ['count' => 1])))
                && $m->envelope()->replyTo == app(SystemMailSender::class)->replyTo(SystemMailSender::SCOPE_BOOKING);
        });
    }

    #[Test]
    public function join_that_fills_the_search_says_the_booking_is_complete(): void
    {
        $this->configureMail();
        $owner = $this->member('owner@example.com', 'Olga Owner');
        $booking = $this->searchingBooking($owner, 2, 1);
        $max = $this->member('max@example.com', 'Max Muster');

        $this->exchange()->join($max, $booking);
        $this->runDeferredJobs();

        Mail::assertQueued(PlayerSearchJoined::class, 1);
        Mail::assertQueued(
            PlayerSearchJoined::class,
            fn (PlayerSearchJoined $m) => str_contains($m->render(), e(__('booking.mail.player_search.joined_complete'))),
        );
    }

    #[Test]
    public function refused_join_sends_no_joined_mail(): void
    {
        $this->configureMail();
        $owner = $this->member('owner@example.com', 'Olga Owner');
        $booking = $this->searchingBooking($owner, 2, 1);
        $outsider = $this->member('out@example.com', 'Out', optIn: false);

        $this->actingAs($outsider)->post(route('player-exchange.join', $booking))->assertSessionHas('error');

        Mail::assertNotQueued(PlayerSearchJoined::class);
    }

    #[Test]
    public function no_mail_when_mail_is_not_configured(): void
    {
        // The test environment uses the array mailer - mail is "not available".
        $owner = $this->member('owner@example.com', 'Olga Owner');
        $this->member('anna@example.com', 'Anna');
        $booking = $this->searchingBooking($owner);
        $this->runDeferredJobs();

        $this->exchange()->join($this->member('max@example.com', 'Max Muster'), $booking);
        $this->runDeferredJobs();

        $this->runJob(PlayerSearch::where('bid', $booking->bid)->sole()->id);

        Mail::assertNothingOutgoing();
    }

    #[Test]
    public function expired_and_cancelled_mails_render_in_both_languages(): void
    {
        $owner = $this->member('owner@example.com', 'Olga Owner');
        $square = Square::factory()->create();
        $booking = $this->searchingBooking($owner, 4, 2, $square);
        $max = $this->member('max@example.com', 'Max Muster');
        $search = PlayerSearch::where('bid', $booking->bid)->sole();
        $replyTo = app(SystemMailSender::class)->replyTo(SystemMailSender::SCOPE_BOOKING);

        foreach (['de', 'en'] as $locale) {
            App::setLocale($locale);

            $expired = new PlayerSearchExpired($search);
            $this->assertInstanceOf(ShouldQueue::class, $expired);
            $this->assertStringContainsString($square->display_name, $expired->envelope()->subject);
            $this->assertEquals($replyTo, $expired->envelope()->replyTo);
            $html = $expired->render();
            $this->assertStringContainsString(e(__('booking.mail.player_search.expired_heading')), $html);
            $this->assertStringContainsString(e(__('booking.mail.player_search.greeting', ['name' => 'Olga Owner'])), $html);
            $this->assertStringContainsString('10:00', $html);
            $this->assertStringNotContainsString('player_search.', $html);

            $cancelled = new PlayerSearchBookingCancelled($search, $max);
            $this->assertInstanceOf(ShouldQueue::class, $cancelled);
            $this->assertStringContainsString($square->display_name, $cancelled->envelope()->subject);
            $this->assertEquals($replyTo, $cancelled->envelope()->replyTo);
            $html = $cancelled->render();
            $this->assertStringContainsString(e(__('booking.mail.player_search.cancelled_heading')), $html);
            $this->assertStringContainsString(e(__('booking.mail.player_search.greeting', ['name' => 'Max Muster'])), $html);
            $this->assertStringContainsString('10:00', $html);
            $this->assertStringNotContainsString('player_search.', $html);
        }
    }
}
