<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Mail;

use App\Modules\TournamentBase\Support\MailSender;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The one plain-text mail behind all eleven Pyramid notifications.
 *
 * The greeting reuses the booking lang key. From, Reply-To and signature come from the shared
 * TournamentBase MailSender (admin option > config mail.tournament.* > default); with nothing
 * configured the mail sets no From of its own (the global mail.from applies, as for the
 * booking mails), no Reply-To, and the default signature. Texts are pinned to German.
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
        return app(MailSender::class)->envelope(__('pyramid::mail.prefix', [], self::LOCALE).$this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            text: 'pyramid::mail.notification',
            with: [
                'greeting' => __('booking.mail.greeting_confirmed', ['name' => $this->alias], self::LOCALE),
                'body' => $this->body,
                'signature' => app(MailSender::class)->signature(self::LOCALE),
            ],
        );
    }
}
