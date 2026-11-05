<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The one plain-text mail behind all eleven Pyramid notifications.
 *
 * Greeting, From and signature follow the booking mails: the greeting reuses the booking
 * lang key and the From address and name come from config mail.from (nothing is set here).
 * Reply-To is set only when config mail.reply_to.address is configured (club decision;
 * the booking mails set none). Texts are pinned to German.
 *
 * Deliberately not queued: callers send it synchronously after their DB::transaction
 * has returned, and the notifier swallows (and logs) a failing mail server.
 */
final class PyramidNotificationMail extends Mailable
{
    private const LOCALE = 'de';

    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $alias = '',
    ) {}

    public function envelope(): Envelope
    {
        $address = trim((string) config('mail.reply_to.address'));
        $name = trim((string) config('mail.reply_to.name'));

        return new Envelope(
            replyTo: $address === '' ? [] : [new Address($address, $name === '' ? null : $name)],
            subject: __('pyramid::mail.prefix', [], self::LOCALE).$this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'pyramid::mail.notification',
            with: [
                'greeting' => __('booking.mail.greeting_confirmed', ['name' => $this->alias], self::LOCALE),
                'body' => $this->body,
                'signature' => __('pyramid::mail.signature', [
                    'club' => (string) config('booking.name'),
                    'url' => (string) config('app.url'),
                ], self::LOCALE),
            ],
        );
    }
}
