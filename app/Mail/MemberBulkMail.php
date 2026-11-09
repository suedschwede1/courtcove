<?php

declare(strict_types=1);

namespace App\Mail;

use App\Modules\Members\Models\MailSend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberBulkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $mailSubject,
        public readonly string $bodyText,
        public readonly ?int $mailSendId = null,
    ) {}

    public function send($mailer)
    {
        $result = parent::send($mailer);
        if (isset($this->mailSendId)) {
            MailSend::whereKey($this->mailSendId)->update([
                'status' => $result === null ? 'failed' : 'submitted',
                'submitted_at' => $result === null ? null : now(),
            ]);
        }

        return $result;
    }

    public function failed(\Throwable $exception): void
    {
        if (isset($this->mailSendId)) {
            MailSend::whereKey($this->mailSendId)->update(['status' => 'failed']);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-bulk',
            with: ['body' => $this->bodyText],
        );
    }
}
