<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
    ) {}

    public function envelope(): Envelope
    {
        $this->booking->loadMissing(['reservations', 'square']);
        $reservation = $this->booking->reservations->first();
        $square = $this->booking->square;

        $parts = array_filter([
            $square?->display_name,
            $reservation ? Carbon::parse($reservation->date)->isoFormat('D. MMMM Y') : null,
        ]);

        $title = $parts ? implode(', ', $parts) : 'Buchung #'.$this->booking->bid;

        return new Envelope(subject: 'Buchungsbestätigung – '.$title);
    }

    public function content(): Content
    {
        $this->booking->loadMissing(['reservations', 'square', 'user']);

        return new Content(
            view: 'emails.booking-confirmed',
            with: [
                'booking' => $this->booking,
                'reservation' => $this->booking->reservations->first(),
                'square' => $this->booking->square,
                'user' => $this->booking->user,
            ],
        );
    }
}
