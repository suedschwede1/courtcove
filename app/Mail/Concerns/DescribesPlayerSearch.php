<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\Reservation;
use App\Models\Square;
use App\Support\Mail\SystemMailSender;
use Carbon\Carbon;
use Illuminate\Mail\Mailables\Address;

/**
 * Shared data of the player search mails: court, date and time of the booking,
 * the calendar link of that day and the booking Reply-To.
 *
 * @property-read PlayerSearch $search
 */
trait DescribesPlayerSearch
{
    /** "Court, 8. November 2026, 10:00 Uhr" for the subject. */
    protected function slotTitle(): string
    {
        $slot = $this->slot();
        $time = $slot['reservation']
            ? trim(substr((string) $slot['reservation']->time_start, 0, 5).' '.__('booking.admin.common.clock_suffix'))
            : null;

        $parts = array_filter([
            $slot['square']?->display_name,
            $slot['reservation'] ? Carbon::parse($slot['reservation']->date)->isoFormat('D. MMMM Y') : null,
            $time,
        ]);

        return $parts ? implode(', ', $parts) : __('booking.mail.booking_reference', ['bid' => $this->search->bid]);
    }

    /**
     * View data shared by all player search mails.
     *
     * @return array{booking: Booking|null, square: Square|null, reservation: Reservation|null, calendarUrl: string}
     */
    protected function slot(): array
    {
        $booking = $this->search->booking;
        $booking?->loadMissing(['reservations', 'square', 'user']);

        /** @var Reservation|null $reservation */
        $reservation = $booking?->reservations->sortBy(fn ($r) => $r->date.' '.$r->time_start)->first();

        return [
            'booking' => $booking,
            'square' => $booking?->square,
            'reservation' => $reservation,
            'calendarUrl' => $reservation
                ? route('calendar.index', ['date' => Carbon::parse($reservation->date)->format('Y-m-d')])
                : route('calendar.index'),
        ];
    }

    /** @return array<int, Address> */
    protected function bookingReplyTo(): array
    {
        return app(SystemMailSender::class)->replyTo(SystemMailSender::SCOPE_BOOKING);
    }
}
