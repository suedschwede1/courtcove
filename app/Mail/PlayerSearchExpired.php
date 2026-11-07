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

/**
 * Mail to the booker: nobody joined in time, the booking was lifted and the court is free again.
 *
 * Holds only plain values taken when the search lapsed, so a queue worker can render it even
 * after the search, booking or member rows are gone (no model is restored on unserialize).
 */
class PlayerSearchExpired extends Mailable implements ShouldQueue
{
    use DescribesPlayerSearch, Queueable;

    public readonly int $searchId;

    /** @var array{bid: int, court: string|null, date: string|null, timeStart: string|null, timeEnd: string|null} */
    public readonly array $details;

    public readonly ?string $greetingName;

    public function __construct(PlayerSearch $search)
    {
        $this->searchId = (int) $search->id;
        $this->details = static::snapshotSlot($search);
        $this->greetingName = $search->booking?->user?->alias;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('booking.mail.player_search.expired_subject', ['title' => $this->slotTitle($this->details)]),
            replyTo: $this->bookingReplyTo(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.player-search-expired',
            with: [
                ...$this->slot($this->details),
                'greetingName' => $this->greetingName,
            ],
        );
    }
}
