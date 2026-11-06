<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\BookingCancelled;
use App\Mail\BookingConfirmed;
use App\Mail\LoginVerificationCode;
use App\Mail\MemberBulkMail;
use App\Mail\PasswordReset;
use App\Mail\TestMail;
use App\Mail\UserActivated;
use App\Models\Booking;
use App\Models\Option;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Modules\Pyramid\Mail\PyramidNotificationMail;
use App\Modules\TournamentBase\Support\MailSender;
use App\Support\Mail\SystemMailSender;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Address as MimeAddress;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Club decision: the booking mails and the password/account mails get a configurable
 * Reply-To (admin option > .env > none), each scope with its own value, independent of
 * the tournament mails' Reply-To. Default: no Reply-To header.
 */
class SystemMailReplyToTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function option(string $key, string $value): void
    {
        Option::query()->create(['key' => $key, 'value' => $value, 'locale' => null]);
    }

    /**
     * Sends through the real array transport (not Mail::fake) and returns the built message.
     */
    private function deliver(Mailable $mailable): Email
    {
        Mail::purge('array');
        Mail::mailer('array')->to('member@example.test')->send($mailable);

        /** @var SentMessage $sent */
        $sent = Mail::mailer('array')->getSymfonyTransport()->messages()->last();
        $message = $sent->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $message);

        return $message;
    }

    /** @return list<string> */
    private function replyTo(Mailable $mailable): array
    {
        return array_map(static fn (MimeAddress $a): string => $a->getAddress(), $this->deliver($mailable)->getReplyTo());
    }

    private function booking(): Booking
    {
        $square = Square::factory()->create(['name' => 'Court A']);
        $booking = Booking::factory()->create(['uid' => User::factory()->create()->uid, 'sid' => $square->sid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => '2026-07-10', 'time_start' => '10:00:00', 'time_end' => '11:00:00']);

        return $booking;
    }

    /** @return array<string, Mailable> */
    private function bookingMails(): array
    {
        $booking = $this->booking();

        return ['confirmed' => new BookingConfirmed($booking), 'cancelled' => new BookingCancelled($booking)];
    }

    /** @return array<string, Mailable> */
    private function accountMails(): array
    {
        $user = User::factory()->create();

        return [
            'password reset' => new PasswordReset($user, 'https://booking.example.test/password/reset/token'),
            'activated' => new UserActivated($user),
            'login code' => new LoginVerificationCode('123456'),
        ];
    }

    #[Test]
    public function nothing_configured_means_no_reply_to_header(): void
    {
        foreach ($this->bookingMails() + $this->accountMails() as $name => $mail) {
            $this->assertSame([], $this->replyTo($mail), $name);
        }

        $this->assertSame([], $this->app->make(SystemMailSender::class)->replyTo(SystemMailSender::SCOPE_BOOKING));
        $this->assertSame([], $this->app->make(SystemMailSender::class)->replyTo('unknown'));
    }

    #[Test]
    public function the_env_values_set_the_reply_to_per_scope(): void
    {
        config([
            'mail.system.booking.reply_to.address' => 'office@example.test',
            'mail.system.account.reply_to.address' => 'support@example.test',
        ]);

        foreach ($this->bookingMails() as $name => $mail) {
            $this->assertSame(['office@example.test'], $this->replyTo($mail), $name);
        }
        foreach ($this->accountMails() as $name => $mail) {
            $this->assertSame(['support@example.test'], $this->replyTo($mail), $name);
        }
    }

    #[Test]
    public function an_admin_option_overrides_the_env_value_and_a_blank_one_falls_back(): void
    {
        config([
            'mail.system.booking.reply_to.address' => 'env-office@example.test',
            'mail.system.account.reply_to.address' => 'env-support@example.test',
        ]);
        $this->option(SystemMailSender::OPTION_BOOKING_REPLY_TO_ADDRESS, ' admin-office@example.test ');
        $this->option(SystemMailSender::OPTION_ACCOUNT_REPLY_TO_ADDRESS, '   ');

        $this->assertSame(['admin-office@example.test'], $this->replyTo($this->bookingMails()['confirmed']));
        $this->assertSame(['env-support@example.test'], $this->replyTo($this->accountMails()['password reset']));
    }

    #[Test]
    public function one_scope_alone_leaves_the_other_without_reply_to(): void
    {
        $this->option(SystemMailSender::OPTION_ACCOUNT_REPLY_TO_ADDRESS, 'support@example.test');

        $this->assertSame([], $this->replyTo($this->bookingMails()['confirmed']));
        $this->assertSame(['support@example.test'], $this->replyTo($this->accountMails()['password reset']));
    }

    #[Test]
    public function the_tournament_reply_to_and_the_system_reply_to_are_independent(): void
    {
        $this->option(MailSender::OPTION_REPLY_TO_ADDRESS, 'sport@example.test');

        $this->assertSame([], $this->replyTo($this->bookingMails()['confirmed']));
        $this->assertSame([], $this->replyTo($this->accountMails()['password reset']));

        $this->option(SystemMailSender::OPTION_BOOKING_REPLY_TO_ADDRESS, 'office@example.test');
        $this->option(SystemMailSender::OPTION_ACCOUNT_REPLY_TO_ADDRESS, 'support@example.test');

        $this->assertSame(['sport@example.test'], $this->replyTo(new PyramidNotificationMail('Betreff', 'Text', 'Anna')));
        $this->assertSame(['office@example.test'], $this->replyTo($this->bookingMails()['cancelled']));
    }

    #[Test]
    public function the_system_reply_to_does_not_leak_into_other_mails(): void
    {
        $this->option(SystemMailSender::OPTION_BOOKING_REPLY_TO_ADDRESS, 'office@example.test');
        $this->option(SystemMailSender::OPTION_ACCOUNT_REPLY_TO_ADDRESS, 'support@example.test');

        $this->assertNull(config('mail.reply_to.address'));
        $this->assertSame([], $this->replyTo(new TestMail));
        $this->assertSame([], $this->replyTo(new MemberBulkMail('Rundmail', 'Text')));
        $this->assertSame([], $this->replyTo(new PyramidNotificationMail('Betreff', 'Text', 'Anna')));
    }

    #[Test]
    public function the_global_laravel_reply_to_is_not_a_fallback(): void
    {
        config(['mail.reply_to.address' => 'global@example.test', 'mail.tournament.reply_to.address' => 'sport@example.test']);

        $sender = $this->app->make(SystemMailSender::class);
        $this->assertSame([], $sender->replyTo(SystemMailSender::SCOPE_ACCOUNT));
        $this->assertSame([], $sender->replyTo(SystemMailSender::SCOPE_BOOKING));
    }

    #[Test]
    public function the_queued_mails_carry_it_in_their_envelope(): void
    {
        Mail::fake();
        $this->option(SystemMailSender::OPTION_BOOKING_REPLY_TO_ADDRESS, 'office@example.test');
        $this->option(SystemMailSender::OPTION_ACCOUNT_REPLY_TO_ADDRESS, 'support@example.test');
        $user = User::factory()->create(['email' => 'pat@example.test', 'status' => 'enabled']);

        $this->post('/password/forgot', ['email' => 'pat@example.test'])->assertRedirect();
        Mail::to('pat@example.test')->queue($this->bookingMails()['confirmed']);

        Mail::assertQueued(PasswordReset::class, fn (PasswordReset $mail): bool => $mail->hasReplyTo('support@example.test')
            && ! $mail->hasReplyTo('office@example.test') && $mail->user->is($user));
        Mail::assertQueued(BookingConfirmed::class, fn (BookingConfirmed $mail): bool => $mail->hasReplyTo('office@example.test'));
    }

    #[Test]
    public function the_password_reset_request_sends_the_header(): void
    {
        $this->option(SystemMailSender::OPTION_ACCOUNT_REPLY_TO_ADDRESS, 'support@example.test');
        User::factory()->create(['email' => 'pat@example.test', 'status' => 'enabled']);
        Mail::purge('array');

        $this->post('/password/forgot', ['email' => 'pat@example.test'])->assertRedirect();

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->last()?->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $message);
        $this->assertSame(['support@example.test'], array_map(static fn (MimeAddress $a): string => $a->getAddress(), $message->getReplyTo()));
    }
}
