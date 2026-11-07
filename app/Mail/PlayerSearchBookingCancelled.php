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

/**
 * Mail to a joined player: the booker cancelled the booking.
 *
 * Holds only plain values taken at cancel time, so a queue worker can render it even after
 * the search, booking or member rows are gone (no model is restored on unserialize).
 */
class PlayerSearchBookingCancelled extends Mailable implements ShouldQueue
{
    use DescribesPlayerSearch, Queueable;

    public readonly int $searchId;

    public readonly int $playerId;

    /** @var array{bid: int, court: string|null, date: string|null, timeStart: string|null, timeEnd: string|null} */
    public readonly array $details;

    public readonly string $greetingName;

    public function __construct(PlayerSearch $search, User $player)
    {
        $this->searchId = (int) $search->id;
        $this->playerId = (int) $player->uid;
        $this->details = static::snapshotSlot($search);
        $this->greetingName = (string) $player->alias;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('booking.mail.player_search.cancelled_subject', ['title' => $this->slotTitle($this->details)]),
            replyTo: $this->bookingReplyTo(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.player-search-booking-cancelled',
            with: [
                ...$this->slot($this->details),
                'greetingName' => $this->greetingName,
            ],
        );
    }
}
