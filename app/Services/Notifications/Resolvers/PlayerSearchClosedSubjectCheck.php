<?php

declare(strict_types=1);

namespace App\Services\Notifications\Resolvers;

use App\Models\NotificationLogEntry;
use App\Models\User;
use App\Services\Notifications\MessageContent;
use App\Services\Notifications\SubjectDeadline;
use App\Services\Notifications\SubjectResolver;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Send-time check of the optional follow-up `player_search.closed`: still relevant only while the search
 * exists, is not seeking again (5-minute debounce: a search opened again in between sends nothing) and its
 * start lies ahead. Variables: 1 date, 2 start time, 3 court; no button.
 */
final class PlayerSearchClosedSubjectCheck implements SubjectDeadline, SubjectResolver
{
    public function __construct(private readonly PlayerSearchSubjectCheck $opened) {}

    public function stillRelevant(NotificationLogEntry $row): bool
    {
        $search = $this->opened->search($row);
        if ($search === null || $search->booking === null || $search->isSeeking()) {
            return false;
        }

        $start = $this->opened->slot($search, 'de')['start'];

        return $start !== null && $start->isFuture();
    }

    /** The start of the booking: a "search closed" message after it is worthless. */
    public function deadline(NotificationLogEntry $row): ?CarbonInterface
    {
        $search = $this->opened->search($row);

        return $search === null ? null : $this->opened->slot($search, 'de')['start'];
    }

    public function build(NotificationLogEntry $row, User $user, string $locale): MessageContent
    {
        $search = $this->opened->search($row) ?? throw new RuntimeException('player search '.$row->subject_id.' is gone');
        $slot = $this->opened->slot($search, $locale);

        return new MessageContent(
            variables: ['1' => $slot['date'], '2' => $slot['time'], '3' => $slot['court']],
            buttonSuffix: null,
            freeText: (string) trans('booking.notifications.whatsapp.player_search_closed', [
                'date' => $slot['date'],
                'time' => $slot['time'],
                'court' => $slot['court'],
            ], $locale),
        );
    }
}
