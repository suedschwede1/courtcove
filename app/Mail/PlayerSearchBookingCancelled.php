<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\DescribesPlayerSearch;
use App\Models\PlayerSearch;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Mail to a joined player: the booker cancelled the booking. */
class PlayerSearchBookingCancelled extends Mailable implements ShouldQueue
{
    use DescribesPlayerSearch, Queueable, SerializesModels;

    public function __construct(
        public readonly PlayerSearch $search,
        public readonly User $player,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('booking.mail.player_search.cancelled_subject', ['title' => $this->slotTitle()]),
            replyTo: $this->bookingReplyTo(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.player-search-booking-cancelled',
            with: [
                ...$this->slot(),
                'player' => $this->player,
            ],
        );
    }
}
