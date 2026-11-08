<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\Mail\SystemMailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginVerificationCode extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly bool $newDevice = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('booking.mail.login_verification_subject'),
            replyTo: app(SystemMailSender::class)->replyTo(SystemMailSender::SCOPE_ACCOUNT),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.login-verification-code',
            with: ['code' => $this->code, 'newDevice' => $this->newDevice],
        );
    }
}
