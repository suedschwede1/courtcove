<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\DescribesPlayerSearch;
use App\Models\PlayerSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Mail to the booker: nobody joined in time, the booking was lifted and the court is free again. */
class PlayerSearchExpired extends Mailable implements ShouldQueue
{
    use DescribesPlayerSearch, Queueable, SerializesModels;

    public function __construct(
        public readonly PlayerSearch $search,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('booking.mail.player_search.expired_subject', ['title' => $this->slotTitle()]),
            replyTo: $this->bookingReplyTo(),
        );
    }

    public function content(): Content
    {
        $slot = $this->slot();

        return new Content(
            view: 'emails.player-search-expired',
            with: [
                ...$slot,
                'user' => $slot['booking']?->user,
            ],
        );
    }
}
