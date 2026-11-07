<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Models\PlayerSearch;
use App\Models\Reservation;
use App\Support\Mail\SystemMailSender;
use Carbon\Carbon;
use Illuminate\Mail\Mailables\Address;

/**
 * Shared data of the player search mails: court, date and time of the booking,
 * the calendar link of that day and the booking Reply-To.
 *
 * The slot is kept as plain values (snapshotSlot), so a mail that is rendered later by a
 * queue worker never needs the search, booking or member rows any more; they may be
 * cancelled or deleted by then.
 *
 * @phpstan-type SlotDetails array{bid: int, court: string|null, date: string|null, timeStart: string|null, timeEnd: string|null}
 */
trait DescribesPlayerSearch
{
    /**
     * Court, date and times of the search's booking as plain values.
     *
     * @return SlotDetails
     */
    protected static function snapshotSlot(PlayerSearch $search): array
    {
        $booking = $search->booking;
        $booking?->loadMissing(['reservations', 'square']);

        /** @var Reservation|null $reservation */
        $reservation = $booking?->reservations->sortBy(fn ($r) => $r->date.' '.$r->time_start)->first();

        return [
            'bid' => (int) $search->bid,
            'court' => $booking?->square?->display_name,
            'date' => $reservation ? Carbon::parse($reservation->date)->format('Y-m-d') : null,
            'timeStart' => $reservation ? substr((string) $reservation->time_start, 0, 5) : null,
            'timeEnd' => $reservation ? substr((string) $reservation->time_end, 0, 5) : null,
        ];
    }

    /**
     * "Court, 8. November 2026, 10:00 Uhr" for the subject.
     *
     * @param  SlotDetails  $details
     */
    protected function slotTitle(array $details): string
    {
        $time = $details['timeStart'] !== null
            ? trim($details['timeStart'].' '.__('booking.admin.common.clock_suffix'))
            : null;

        $parts = array_filter([
            $details['court'],
            $details['date'] !== null ? static::localDate($details['date'], 'subject_date_format') : null,
            $time,
        ]);

        return $parts ? implode(', ', $parts) : __('booking.mail.booking_reference', ['bid' => $details['bid']]);
    }

    /**
     * The date in the language the mail is rendered in. Members have no language of their
     * own, so this is the app locale at render time (the request's with a sync queue, the
     * configured default in a queue worker or the scheduler).
     *
     * @param  'date_format'|'subject_date_format'  $format  key under booking.mail.player_search
     */
    protected static function localDate(string $date, string $format): string
    {
        return Carbon::parse($date)->locale(app()->getLocale())->isoFormat(__('booking.mail.player_search.'.$format));
    }

    /**
     * View data shared by all player search mails (the slot partial reads only the plain values).
     *
     * @param  SlotDetails  $details
     * @return array{court: string|null, date: string|null, dateLabel: string|null, timeStart: string|null, timeEnd: string|null, calendarUrl: string}
     */
    protected function slot(array $details): array
    {
        return [
            'court' => $details['court'],
            'date' => $details['date'],
            'dateLabel' => $details['date'] !== null ? static::localDate($details['date'], 'date_format') : null,
            'timeStart' => $details['timeStart'],
            'timeEnd' => $details['timeEnd'],
            'calendarUrl' => $details['date'] !== null
                ? route('calendar.index', ['date' => $details['date']])
                : route('calendar.index'),
        ];
    }

    /** @return array<int, Address> */
    protected function bookingReplyTo(): array
    {
        return app(SystemMailSender::class)->replyTo(SystemMailSender::SCOPE_BOOKING);
    }
}
