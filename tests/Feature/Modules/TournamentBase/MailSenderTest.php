<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TournamentBase;

use App\Mail\TestMail;
use App\Models\Option;
use App\Modules\Pyramid\Mail\PyramidNotificationMail;
use App\Modules\TournamentBase\Support\MailSender;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Address as MimeAddress;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Club decision: sender, Reply-To and signature of the tournament mails are configurable.
 * Precedence: admin option (non-empty) > config mail.tournament.* (.env) > built-in default.
 */
class MailSenderTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function sender(): MailSender
    {
        return $this->app->make(MailSender::class);
    }

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
        Mail::mailer('array')->to('player@example.test')->send($mailable);

        /** @var SentMessage $sent */
        $sent = Mail::mailer('array')->getSymfonyTransport()->messages()->last();
        $message = $sent->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $message);

        return $message;
    }

    /**
     * @param  list<MimeAddress>  $addresses
     * @return list<string>
     */
    private static function addresses(array $addresses): array
    {
        return array_map(static fn (MimeAddress $a): string => $a->toString(), $addresses);
    }

    #[Test]
    public function nothing_configured_keeps_the_built_in_defaults(): void
    {
        config(['booking.name' => 'Tennisclub Beispiel', 'app.url' => 'https://club.example.test']);

        $this->assertNull($this->sender()->from());
        $this->assertSame([], $this->sender()->replyTo());
        $this->assertSame("\nViele Grüße\nTennisclub Beispiel\nhttps://club.example.test\n", $this->sender()->signature('de'));
        $this->assertSame("\nKind regards\nTennisclub Beispiel\nhttps://club.example.test\n", $this->sender()->signature('en'));
    }

    #[Test]
    public function config_overrides_the_defaults(): void
    {
        config([
            'mail.tournament.from.address' => 'turnier@example.test',
            'mail.tournament.from.name' => 'Turnierleitung',
            'mail.tournament.reply_to.address' => 'sport@example.test',
            'mail.tournament.reply_to.name' => 'Sportwart',
            'mail.tournament.signature.club' => 'Sportverein Muster',
            'mail.tournament.signature.url' => 'https://verein.example.test',
        ]);

        $from = $this->sender()->from();
        $this->assertNotNull($from);
        $this->assertSame(['turnier@example.test', 'Turnierleitung'], [$from->address, $from->name]);
        $replyTo = $this->sender()->replyTo();
        $this->assertCount(1, $replyTo);
        $this->assertSame(['sport@example.test', 'Sportwart'], [$replyTo[0]->address, $replyTo[0]->name]);
        $this->assertSame("\nViele Grüße\nSportverein Muster\nhttps://verein.example.test\n", $this->sender()->signature('de'));
        $this->assertSame("\nKind regards\nSportverein Muster\nhttps://verein.example.test\n", $this->sender()->signature('en'));
    }

    #[Test]
    public function an_admin_option_overrides_the_config(): void
    {
        config([
            'mail.tournament.from.address' => 'turnier@example.test',
            'mail.tournament.from.name' => 'Turnierleitung',
            'mail.tournament.reply_to.address' => 'sport@example.test',
            'mail.tournament.reply_to.name' => 'Sportwart',
            'mail.tournament.signature.text' => 'Config-Gruß',
            'mail.tournament.signature.club' => 'Config-Verein',
            'mail.tournament.signature.url' => 'https://config.example.test',
        ]);
        $this->option(MailSender::OPTION_FROM_ADDRESS, 'option@example.test');
        $this->option(MailSender::OPTION_FROM_NAME, 'Option-Absender');
        $this->option(MailSender::OPTION_REPLY_TO_ADDRESS, 'antwort@example.test');
        $this->option(MailSender::OPTION_REPLY_TO_NAME, 'Option-Antwort');
        $this->option(MailSender::OPTION_SIGNATURE_TEXT, "Sportliche Grüße\r\n:club – :url");
        $this->option(MailSender::OPTION_SIGNATURE_CLUB, 'Option-Verein');
        $this->option(MailSender::OPTION_SIGNATURE_URL, 'https://option.example.test');

        $from = $this->sender()->from();
        $this->assertNotNull($from);
        $this->assertSame(['option@example.test', 'Option-Absender'], [$from->address, $from->name]);
        $replyTo = $this->sender()->replyTo();
        $this->assertSame(['antwort@example.test', 'Option-Antwort'], [$replyTo[0]->address, $replyTo[0]->name]);
        $this->assertSame("\nSportliche Grüße\nOption-Verein – https://option.example.test\n", $this->sender()->signature('de'));
        // A custom text is the same in every language.
        $this->assertSame($this->sender()->signature('de'), $this->sender()->signature('en'));
    }

    #[Test]
    public function empty_or_blank_options_fall_back_to_the_config(): void
    {
        config(['mail.tournament.from.address' => 'turnier@example.test', 'mail.tournament.signature.club' => 'Config-Verein']);
        $this->option(MailSender::OPTION_FROM_ADDRESS, '');
        $this->option(MailSender::OPTION_SIGNATURE_CLUB, '   ');

        $this->assertSame('turnier@example.test', $this->sender()->from()?->address);
        $this->assertStringContainsString("\nConfig-Verein\n", $this->sender()->signature('de'));
    }

    #[Test]
    public function a_partial_from_keeps_the_global_part(): void
    {
        config(['mail.from.address' => 'buchung@example.test', 'mail.from.name' => 'Buchungssystem']);

        $this->option(MailSender::OPTION_FROM_NAME, 'Turnierleitung');
        $from = $this->sender()->from();
        $this->assertSame(['buchung@example.test', 'Turnierleitung'], [$from?->address, $from?->name]);

        Option::query()->delete();
        config(['mail.tournament.from.address' => 'turnier@example.test']);
        $from = $this->sender()->from();
        $this->assertSame(['turnier@example.test', 'Buchungssystem'], [$from?->address, $from?->name]);
    }

    #[Test]
    public function a_reply_to_name_without_address_gives_no_header(): void
    {
        $this->option(MailSender::OPTION_REPLY_TO_NAME, 'Sportwart');
        config(['mail.tournament.reply_to.name' => 'Sportwart']);

        $this->assertSame([], $this->sender()->replyTo());
        $this->assertSame([], $this->deliver(new PyramidNotificationMail('Betreff', 'Text', 'Anna'))->getReplyTo());
    }

    #[Test]
    public function without_configuration_the_headers_match_the_booking_mails(): void
    {
        config(['mail.from.address' => 'buchung@example.test', 'mail.from.name' => 'Buchungssystem']);

        $pyramid = $this->deliver(new PyramidNotificationMail('Betreff', 'Text', 'Anna'));
        $booking = $this->deliver(new TestMail);

        $this->assertSame(['"Buchungssystem" <buchung@example.test>'], self::addresses($pyramid->getFrom()));
        $this->assertSame(self::addresses($booking->getFrom()), self::addresses($pyramid->getFrom()));
        $this->assertSame([], $pyramid->getReplyTo());
        $this->assertSame([], $booking->getReplyTo());
    }

    #[Test]
    public function configured_headers_and_signature_reach_the_sent_mail(): void
    {
        config(['mail.from.address' => 'buchung@example.test', 'mail.from.name' => 'Buchungssystem']);
        $this->option(MailSender::OPTION_FROM_ADDRESS, 'turnier@example.test');
        $this->option(MailSender::OPTION_FROM_NAME, 'Turnierleitung');
        $this->option(MailSender::OPTION_REPLY_TO_ADDRESS, 'sport@example.test');
        $this->option(MailSender::OPTION_SIGNATURE_CLUB, 'Sportverein Muster');
        $this->option(MailSender::OPTION_SIGNATURE_URL, 'https://verein.example.test');

        $message = $this->deliver(new PyramidNotificationMail('Betreff', 'Text', 'Anna'));

        $this->assertSame(['"Turnierleitung" <turnier@example.test>'], self::addresses($message->getFrom()));
        $this->assertSame(['sport@example.test'], self::addresses($message->getReplyTo()));
        $this->assertStringContainsString("Text\n\nViele Grüße\nSportverein Muster\nhttps://verein.example.test\n", str_replace("\r\n", "\n", (string) $message->getTextBody()));
    }

    #[Test]
    public function the_tournament_reply_to_does_not_leak_into_other_mails(): void
    {
        // Laravel would put a global mail.reply_to on every mail; the .env value feeds mail.tournament only.
        config(['mail.tournament.reply_to.address' => 'sport@example.test']);
        $this->assertNull(config('mail.reply_to.address'));

        $this->assertSame([], $this->deliver(new TestMail)->getReplyTo());
        $this->assertSame(['sport@example.test'], self::addresses($this->deliver(new PyramidNotificationMail('Betreff', 'Text', 'Anna'))->getReplyTo()));
    }
}
