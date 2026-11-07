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

/** Mail to the booker: a member joined the search. */
class PlayerSearchJoined extends Mailable implements ShouldQueue
{
    use DescribesPlayerSearch, Queueable, SerializesModels;

    /**
     * @param  int  $remaining  places still searched right after this join (fixed at join time,
     *                          the mail may be sent later from the queue)
     */
    public function __construct(
        public readonly PlayerSearch $search,
        public readonly User $joiner,
        public readonly int $remaining,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('booking.mail.player_search.joined_subject', ['title' => $this->slotTitle()]),
            replyTo: $this->bookingReplyTo(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.player-search-joined',
            with: [
                ...$this->slot(),
                'joiner' => $this->joiner,
                'remaining' => $this->remaining,
            ],
        );
    }
}
