<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\TrustedDevice;
use App\Models\User;
use App\Support\Mail\SystemMailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Notice to the account owner that a new device was confirmed (so a stolen mailbox or password shows). */
class TrustedDeviceAdded extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly TrustedDevice $device,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('booking.mail.trusted_device_subject', ['system' => (string) config('app.name')]),
            replyTo: app(SystemMailSender::class)->replyTo(SystemMailSender::SCOPE_ACCOUNT),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trusted-device-added',
            with: [
                'device' => $this->device->label,
                'time' => $this->device->created_at?->timezone((string) config('app.timezone'))->format('d.m.Y H:i') ?? '',
                'country' => $this->device->country_code ?? '–',
                'url' => route('account.edit'),
            ],
        );
    }
}
