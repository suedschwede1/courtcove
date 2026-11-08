<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\TestMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Email;
use Tests\Support\CaptureMailTransport;
use Tests\TestCase;

/** Staging mail protection: every outgoing mail goes to MAIL_STAGING_REDIRECT or nowhere; live is untouched. */
class StagingMailRedirectTest extends TestCase
{
    private const REDIRECT = 'inbox@example.test';

    private string $originalDatabase;

    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        $this->originalDatabase = (string) DB::connection()->getDatabaseName();
        CaptureMailTransport::$sent = [];
        Mail::extend('capture', fn () => new CaptureMailTransport);
        config(['mail.mailers.capture' => ['transport' => 'capture']]);
    }

    protected function tearDown(): void
    {
        config(['database.default' => $this->originalConnection]);
        DB::connection()->setDatabaseName($this->originalDatabase);

        parent::tearDown();
    }

    private function staging(?string $redirect = self::REDIRECT, string $mailer = 'capture'): void
    {
        DB::connection()->setDatabaseName('club_staging');
        $this->useMailer($mailer, $redirect);
    }

    private function useMailer(string $mailer, ?string $redirect): void
    {
        config(['mail.default' => $mailer, 'mail.staging_redirect' => $redirect]);
        Mail::forgetMailers();
    }

    /** @return list<string> */
    private function addresses(array $list): array
    {
        return array_map(fn ($a) => $a->getAddress(), $list);
    }

    private function header(Email $mail, string $name): string
    {
        return $mail->getHeaders()->get($name)->getBodyAsString();
    }

    private function sendPlain(array $to, array $cc = [], array $bcc = []): void
    {
        Mail::to($to)->cc($cc)->bcc($bcc)->send(new PlainStagingMail);
    }

    #[Test]
    public function on_staging_to_is_replaced_and_original_is_kept_in_subject_and_header(): void
    {
        $this->staging();

        $this->sendPlain(['player@example.test']);

        $this->assertCount(1, CaptureMailTransport::$sent);
        $mail = CaptureMailTransport::$sent[0];
        $this->assertSame([self::REDIRECT], $this->addresses($mail->getTo()));
        $this->assertSame('[STAGING → player@example.test] Hello', $mail->getSubject());
        $this->assertSame('player@example.test', $this->header($mail, 'X-Original-To'));
    }

    #[Test]
    public function reply_to_stays_unchanged(): void
    {
        $this->staging();

        Mail::to('player@example.test')->send(new PlainStagingMail(replyToAddress: 'office@example.test'));

        $this->assertSame(['office@example.test'], $this->addresses(CaptureMailTransport::$sent[0]->getReplyTo()));
    }

    #[Test]
    public function cc_and_bcc_are_removed_and_recorded(): void
    {
        $this->staging();

        $this->sendPlain(['a@example.test'], ['c@example.test'], ['b@example.test']);

        $mail = CaptureMailTransport::$sent[0];
        $this->assertSame([self::REDIRECT], $this->addresses($mail->getTo()));
        $this->assertSame([], $mail->getCc());
        $this->assertSame([], $mail->getBcc());
        $this->assertSame('c@example.test', $this->header($mail, 'X-Original-Cc'));
        $this->assertSame('b@example.test', $this->header($mail, 'X-Original-Bcc'));
    }

    #[Test]
    public function many_recipients_are_sent_once_to_the_redirect_and_the_subject_is_shortened(): void
    {
        $this->staging();

        $this->sendPlain(['a@example.test', 'b@example.test', 'c@example.test', 'd@example.test']);

        $this->assertCount(1, CaptureMailTransport::$sent);
        $mail = CaptureMailTransport::$sent[0];
        $this->assertSame([self::REDIRECT], $this->addresses($mail->getTo()));
        $this->assertSame('[STAGING → a@example.test, b@example.test +2] Hello', $mail->getSubject());
        $this->assertSame(
            'a@example.test, b@example.test, c@example.test, d@example.test',
            $this->header($mail, 'X-Original-To')
        );
    }

    #[Test]
    public function fail_closed_without_redirect_on_a_real_mailer(): void
    {
        $this->staging(redirect: null);
        Log::spy();

        $this->sendPlain(['player@example.test'], ['c@example.test']);

        $this->assertSame([], CaptureMailTransport::$sent);
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context = []) => ! str_contains($message.json_encode($context), 'example.test')
        );
    }

    #[Test]
    #[DataProvider('invalidRedirects')]
    public function fail_closed_with_an_invalid_redirect_address(string $redirect): void
    {
        $this->staging(redirect: $redirect);

        $this->sendPlain(['player@example.test']);

        $this->assertSame([], CaptureMailTransport::$sent);
    }

    /** @return array<string, array{string}> */
    public static function invalidRedirects(): array
    {
        return [
            'empty' => [''],
            'blank' => ['   '],
            'no at sign' => ['inbox.example.test'],
            'two addresses' => ['a@example.test,b@example.test'],
            'header injection' => ["a@example.test\r\nBcc: x@example.test"],
        ];
    }

    #[Test]
    public function failover_mailer_counts_as_real_when_it_lists_a_real_transport(): void
    {
        config(['mail.mailers.failover' => ['transport' => 'failover', 'mailers' => ['capture', 'log']]]);
        $this->staging(redirect: null, mailer: 'failover');

        $this->sendPlain(['player@example.test']);

        $this->assertSame([], CaptureMailTransport::$sent);
    }

    #[Test]
    public function log_and_array_mailers_on_staging_are_not_blocked_without_redirect(): void
    {
        $this->staging(redirect: null, mailer: 'array');

        $this->sendPlain(['player@example.test']);

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame(['player@example.test'], $this->addresses($messages[0]->getOriginalMessage()->getTo()));
    }

    #[Test]
    public function array_mailer_on_staging_applies_the_redirect_when_set(): void
    {
        $this->staging(mailer: 'array');

        $this->sendPlain(['player@example.test']);

        $mail = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage();
        $this->assertSame([self::REDIRECT], $this->addresses($mail->getTo()));
    }

    #[Test]
    public function live_database_is_untouched_even_with_the_variable_set(): void
    {
        $this->useMailer('capture', self::REDIRECT);

        $this->sendPlain(['player@example.test'], ['c@example.test']);

        $mail = CaptureMailTransport::$sent[0];
        $this->assertSame(['player@example.test'], $this->addresses($mail->getTo()));
        $this->assertSame(['c@example.test'], $this->addresses($mail->getCc()));
        $this->assertSame('Hello', $mail->getSubject());
        $this->assertFalse($mail->getHeaders()->has('X-Original-To'));
    }

    #[Test]
    public function live_database_sends_normally_without_the_variable(): void
    {
        $this->useMailer('capture', null);

        $this->sendPlain(['player@example.test']);

        $this->assertCount(1, CaptureMailTransport::$sent);
    }

    #[Test]
    public function an_unknown_database_counts_as_staging(): void
    {
        $this->useMailer('capture', null);
        config(['database.default' => 'no_such_connection']);

        $this->sendPlain(['player@example.test']);

        $this->assertSame([], CaptureMailTransport::$sent);
    }

    #[Test]
    public function an_unknown_database_redirects_when_the_address_is_set(): void
    {
        $this->useMailer('capture', self::REDIRECT);
        config(['database.default' => 'no_such_connection']);

        $this->sendPlain(['player@example.test']);

        $this->assertSame([self::REDIRECT], $this->addresses(CaptureMailTransport::$sent[0]->getTo()));
    }

    #[Test]
    public function a_blank_database_name_counts_as_staging(): void
    {
        $this->useMailer('capture', null);
        DB::connection()->setDatabaseName('');

        $this->sendPlain(['player@example.test']);

        $this->assertSame([], CaptureMailTransport::$sent);
    }

    #[Test]
    public function notification_mail_channel_is_redirected(): void
    {
        $this->staging();

        \Illuminate\Support\Facades\Notification::route('mail', 'player@example.test')->notify(new StagingTestNotification);

        $this->assertCount(1, CaptureMailTransport::$sent);
        $mail = CaptureMailTransport::$sent[0];
        $this->assertSame([self::REDIRECT], $this->addresses($mail->getTo()));
        $this->assertStringStartsWith('[STAGING → player@example.test]', (string) $mail->getSubject());
    }

    #[Test]
    public function notification_mail_channel_is_blocked_without_redirect(): void
    {
        $this->staging(redirect: null);

        \Illuminate\Support\Facades\Notification::route('mail', 'player@example.test')->notify(new StagingTestNotification);

        $this->assertSame([], CaptureMailTransport::$sent);
    }

    #[Test]
    public function queued_mailable_is_redirected_when_the_worker_sends_it(): void
    {
        $this->staging();
        config(['queue.default' => 'sync']);

        Mail::to('player@example.test')->queue(new QueuedStagingMail);

        $this->assertCount(1, CaptureMailTransport::$sent);
        $this->assertSame([self::REDIRECT], $this->addresses(CaptureMailTransport::$sent[0]->getTo()));
    }

    #[Test]
    public function queued_mailable_is_blocked_without_redirect(): void
    {
        $this->staging(redirect: null);
        config(['queue.default' => 'sync']);

        Mail::to('player@example.test')->queue(new QueuedStagingMail);

        $this->assertSame([], CaptureMailTransport::$sent);
    }

    #[Test]
    public function raw_mail_is_redirected(): void
    {
        $this->staging();

        Mail::raw('body', fn ($m) => $m->to('player@example.test')->subject('Raw'));

        $this->assertSame([self::REDIRECT], $this->addresses(CaptureMailTransport::$sent[0]->getTo()));
        $this->assertSame('[STAGING → player@example.test] Raw', CaptureMailTransport::$sent[0]->getSubject());
    }

    #[Test]
    public function always_to_cannot_bypass_the_protection(): void
    {
        $this->staging(redirect: null);
        Mail::alwaysTo('real@example.test');

        $this->sendPlain(['player@example.test']);

        $this->assertSame([], CaptureMailTransport::$sent);
    }

    #[Test]
    public function app_mailable_with_a_view_is_redirected(): void
    {
        $this->staging();

        Mail::to('player@example.test')->send(new TestMail);

        $this->assertSame([self::REDIRECT], $this->addresses(CaptureMailTransport::$sent[0]->getTo()));
    }

    #[Test]
    public function a_message_without_a_mailer_name_is_treated_as_real(): void
    {
        $this->staging(redirect: null, mailer: 'array');
        $email = (new Email)->from('a@example.test')->to('player@example.test')->subject('x')->text('x');

        $result = event(new MessageSending($email), halt: true);

        $this->assertFalse($result);
    }

    #[Test]
    public function check_command_refuses_on_a_live_database(): void
    {
        $this->useMailer('capture', self::REDIRECT);

        $this->artisan('mail:staging-check', ['--send' => true])->assertFailed();

        $this->assertSame([], CaptureMailTransport::$sent);
    }

    #[Test]
    public function check_command_reports_state_without_the_address_and_sends_nothing_without_send(): void
    {
        $this->staging();

        $this->artisan('mail:staging-check')
            ->expectsOutputToContain('capture')
            ->doesntExpectOutputToContain('example.test')
            ->assertSuccessful();

        $this->assertSame([], CaptureMailTransport::$sent);
    }

    #[Test]
    public function check_command_send_goes_to_the_redirect_address(): void
    {
        $this->staging();

        $this->artisan('mail:staging-check', ['--send' => true])
            ->doesntExpectOutputToContain('example.test')
            ->assertSuccessful();

        $this->assertCount(1, CaptureMailTransport::$sent);
        $this->assertSame([self::REDIRECT], $this->addresses(CaptureMailTransport::$sent[0]->getTo()));
        $this->assertSame('nobody@example.test', $this->header(CaptureMailTransport::$sent[0], 'X-Original-To'));
    }

    #[Test]
    public function check_command_send_without_redirect_reports_not_sent(): void
    {
        $this->staging(redirect: null);

        $this->artisan('mail:staging-check', ['--send' => true])
            ->expectsOutputToContain('nicht gesendet')
            ->assertFailed();

        $this->assertSame([], CaptureMailTransport::$sent);
    }
}

class PlainStagingMail extends Mailable
{
    public function __construct(private readonly ?string $replyToAddress = null) {}

    public function build(): static
    {
        $this->subject('Hello')->html('<p>Hi</p>');

        return $this->replyToAddress !== null ? $this->replyTo($this->replyToAddress) : $this;
    }
}

class QueuedStagingMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function build(): static
    {
        return $this->subject('Queued')->html('<p>Hi</p>');
    }
}

class StagingTestNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Notice')->line('Hi');
    }
}
