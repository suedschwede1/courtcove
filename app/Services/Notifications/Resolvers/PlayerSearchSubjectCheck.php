<?php

declare(strict_types=1);

namespace App\Services\Notifications\Resolvers;

use App\Models\Booking;
use App\Models\NotificationLogEntry;
use App\Models\PlayerSearch;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Notifications\MessageContent;
use App\Services\Notifications\SubjectDeadline;
use App\Services\Notifications\SubjectResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Send-time check of `player_search.opened`: the search must still be open with places left, its deadline
 * more than 15 minutes away and its booking active (the 2-minute debounce of the outbox makes a search that
 * was withdrawn at once send nothing). Builds the template variables from the search, never from the row:
 * 1 date ("Sa, 14.11."), 2 start time, 3 court, 4 places searched, 5 first and last name of the booker,
 * button suffix `calendar?date=YYYY-MM-DD&join={bid}` (sent as variable 6).
 */
final class PlayerSearchSubjectCheck implements SubjectDeadline, SubjectResolver
{
    /** A search whose deadline is this close is not advertised any more. */
    public const MIN_LEAD_MINUTES = 15;

    public function stillRelevant(NotificationLogEntry $row): bool
    {
        $search = $this->search($row);
        $booking = $search?->booking;

        return $search !== null
            && $booking !== null
            && $search->status === PlayerSearch::OPEN
            && $search->remaining() > 0
            && $search->ends_at->greaterThan(Carbon::now()->addMinutes(self::MIN_LEAD_MINUTES))
            && in_array($booking->status, Booking::ACTIVE_STATUSES, true);
    }

    /** The end of the search: a retry is never scheduled after it. */
    public function deadline(NotificationLogEntry $row): ?CarbonInterface
    {
        return $this->search($row)?->ends_at;
    }

    public function build(NotificationLogEntry $row, User $user, string $locale): MessageContent
    {
        $search = $this->search($row) ?? throw new RuntimeException('player search '.$row->subject_id.' is gone');

        return $this->openedContent($search, $locale);
    }

    public function openedContent(PlayerSearch $search, string $locale): MessageContent
    {
        $slot = $this->slot($search, $locale);
        $name = $this->searcherName($search->booking?->user);
        $remaining = (string) $search->remaining();
        $suffix = 'calendar?date='.$slot['day'].'&join='.$search->bid;

        return new MessageContent(
            variables: ['1' => $slot['date'], '2' => $slot['time'], '3' => $slot['court'], '4' => $remaining, '5' => $name],
            buttonSuffix: $suffix,
            freeText: (string) trans('booking.notifications.whatsapp.player_search_opened', [
                'date' => $slot['date'],
                'time' => $slot['time'],
                'court' => $slot['court'],
                'count' => $remaining,
                'name' => $name,
                'url' => url('/'.$suffix),
            ], $locale),
        );
    }

    /**
     * Date, start and court of the search's first reservation, formatted for the message.
     *
     * @return array{date: string, time: string, court: string, day: string, start: Carbon|null}
     */
    public function slot(PlayerSearch $search, string $locale): array
    {
        $booking = $search->booking;
        /** @var Reservation|null $first */
        $first = $booking?->reservations->sortBy(fn (Reservation $r): string => $r->date.' '.$r->time_start)->first();
        $day = $first !== null ? Carbon::parse($first->date)->format('Y-m-d') : '';
        $time = $first !== null ? substr((string) $first->time_start, 0, 5) : '';

        return [
            'date' => $day !== ''
                ? Carbon::parse($day)->locale($locale)->isoFormat((string) trans('booking.notifications.whatsapp.date_format', [], $locale))
                : '',
            'time' => $time,
            'court' => (string) $booking?->square?->display_name,
            'day' => $day,
            'start' => $day !== '' ? Carbon::parse($day.' '.$time) : null,
        ];
    }

    /** First and last name of the booker; the display name when the profile has none. */
    public function searcherName(?User $owner): string
    {
        if ($owner === null) {
            return '';
        }

        $name = trim(trim((string) $owner->getMeta('firstname')).' '.trim((string) $owner->getMeta('lastname')));

        return $name !== '' ? $name : trim((string) $owner->name);
    }

    public function search(NotificationLogEntry $row): ?PlayerSearch
    {
        return PlayerSearch::query()
            ->with(['booking.reservations', 'booking.square', 'booking.user'])
            ->find($row->subject_id);
    }
}
