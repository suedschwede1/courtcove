<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The one plain-text mail behind all eleven Pyramid notifications.
 *
 * Deliberately not queued: it is sent synchronously after the database commit,
 * and the notifier swallows (and logs) a failing mail server.
 */
final class PyramidNotificationMail extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('pyramid::mail.prefix', [], 'de').$this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(text: 'pyramid::mail.notification');
    }
}
