<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Option;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordReset extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('booking.mail.password_reset_subject', ['system' => $this->bookingName()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: ['user' => $this->user, 'resetUrl' => $this->resetUrl],
        );
    }

    private function bookingName(): string
    {
        return rescue(function (): string {
            $name = trim((string) Option::getValue('service.name', config('booking.name')));

            return $name !== '' ? $name : (string) config('booking.name');
        }, (string) config('booking.name'), report: false);
    }
}
