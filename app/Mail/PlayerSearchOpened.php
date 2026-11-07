<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\DescribesPlayerSearch;
use App\Models\PlayerSearch;
use App\Models\User;
use App\Services\PlayerExchangeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Exchange mail to one opted-in member: a booking is looking for players. */
class PlayerSearchOpened extends Mailable implements ShouldQueue
{
    use DescribesPlayerSearch, Queueable, SerializesModels;

    public function __construct(
        public readonly PlayerSearch $search,
        public readonly User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('booking.mail.player_search.opened_subject', ['title' => $this->slotTitle()]),
            replyTo: $this->bookingReplyTo(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.player-search-opened',
            with: [
                ...$this->slot(),
                'recipient' => $this->recipient,
                'remaining' => $this->search->remaining(),
                'unsubscribeUrl' => app(PlayerExchangeService::class)->unsubscribeUrl($this->recipient),
            ],
        );
    }
}
