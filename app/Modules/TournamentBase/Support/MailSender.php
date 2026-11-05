<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Support;

use App\Models\Option;
use Illuminate\Database\QueryException;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sender, Reply-To and signature of the tournament mails (Pyramid now, the other tournament
 * modules later), so the club can change them without touching code.
 *
 * Every value resolves with the same precedence: admin option (bs_options, set on the
 * admin "Texte" page) if set and non-empty > config mail.tournament.* (.env) > built-in default.
 *
 * - From: defaults to the mailer's global From (config mail.from.*, the booking mails' sender).
 *   As long as nothing tournament-specific is configured, from() returns null and the mail
 *   sets no From of its own, so the header stays exactly what the booking mails use.
 * - Reply-To: no default; empty everywhere means no Reply-To header at all.
 * - Signature: defaults to the localized tournament-base::mail.signature wording with the
 *   club name (config booking.name) and website (config app.url).
 */
final class MailSender
{
    public const OPTION_FROM_ADDRESS = 'service.mail.tournament.from.address';

    public const OPTION_FROM_NAME = 'service.mail.tournament.from.name';

    public const OPTION_REPLY_TO_ADDRESS = 'service.mail.tournament.reply_to.address';

    public const OPTION_REPLY_TO_NAME = 'service.mail.tournament.reply_to.name';

    public const OPTION_SIGNATURE_TEXT = 'service.mail.tournament.signature.text';

    public const OPTION_SIGNATURE_CLUB = 'service.mail.tournament.signature.club';

    public const OPTION_SIGNATURE_URL = 'service.mail.tournament.signature.url';

    /**
     * Envelope with subject, the configured From (if any) and the Reply-To (if any).
     */
    public function envelope(string $subject): Envelope
    {
        return new Envelope(
            from: $this->from(),
            replyTo: $this->replyTo(),
            subject: $subject,
        );
    }

    /**
     * The tournament-specific From, or null to keep the mailer's global From (mail.from.*).
     * A configured name without an address keeps the global address; an address without a
     * name keeps the global name.
     */
    public function from(): ?Address
    {
        $address = $this->value(self::OPTION_FROM_ADDRESS, 'mail.tournament.from.address');
        $name = $this->value(self::OPTION_FROM_NAME, 'mail.tournament.from.name');

        if ($address === '' && $name === '') {
            return null;
        }

        $address = $address !== '' ? $address : trim((string) config('mail.from.address'));
        $name = $name !== '' ? $name : trim((string) config('mail.from.name'));

        return new Address($address, $name === '' ? null : $name);
    }

    /**
     * @return list<Address> empty when no Reply-To address is configured
     */
    public function replyTo(): array
    {
        // mail.reply_to is Laravel's global Reply-To; it stays unset by default (the .env
        // variables feed mail.tournament.reply_to) and is only honoured as a last fallback.
        $address = $this->value(self::OPTION_REPLY_TO_ADDRESS, 'mail.tournament.reply_to.address', 'mail.reply_to.address');

        if ($address === '') {
            return [];
        }

        $name = $this->value(self::OPTION_REPLY_TO_NAME, 'mail.tournament.reply_to.name', 'mail.reply_to.name');

        return [new Address($address, $name === '' ? null : $name)];
    }

    /**
     * Plain-text signature block, starting and ending with a line break.
     * A custom text may use the placeholders :club and :url.
     */
    public function signature(string $locale): string
    {
        $club = $this->value(self::OPTION_SIGNATURE_CLUB, 'mail.tournament.signature.club', 'booking.name');
        $url = $this->value(self::OPTION_SIGNATURE_URL, 'mail.tournament.signature.url', 'app.url');
        $text = $this->value(self::OPTION_SIGNATURE_TEXT, 'mail.tournament.signature.text');

        if ($text === '') {
            $line = __('tournament-base::mail.signature', ['club' => $club, 'url' => $url], $locale);

            return is_string($line) ? $line : '';
        }

        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));

        return "\n".strtr($text, [':club' => $club, ':url' => $url])."\n";
    }

    /**
     * First non-empty value: the admin option, then the config keys in the given order.
     */
    private function value(string $option, string ...$configKeys): string
    {
        $value = $this->option($option);

        foreach ($configKeys as $key) {
            if ($value !== '') {
                break;
            }

            $config = config($key);
            $value = is_scalar($config) ? trim((string) $config) : '';
        }

        return $value;
    }

    /**
     * A missing options table (fresh install before migrating, bare unit tests) must not
     * stop a mail; the config and default values apply then.
     */
    private function option(string $key): string
    {
        try {
            $value = Option::getValue($key, '');
        } catch (QueryException) {
            return '';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
