<?php

namespace App\Support\Calendar;

/**
 * Visual state of a calendar cell. The legend lists these states; the grid
 * resolves each cell through for().
 */
enum CellState: string
{
    case Free = 'free';
    case Own = 'own';
    case SingleFuture = 'single_future';
    case Series = 'series';
    case Seeking = 'seeking';
    case Event = 'event';
    case Blocked = 'blocked';
    case Tournament = 'tournament';
    case Past = 'past';

    /**
     * Precedence: tournament > past > seeking > own > series > single future.
     * Seeking only applies to someone else's booking; an own booking with an open
     * search stays Own. Event and Blocked are never returned here; they belong to
     * event parts (EventKind).
     */
    public static function for(bool $hasReservation, bool $isPast, bool $isOwn, bool $isSeries, bool $isTournament, bool $isSeeking): self
    {
        if (! $hasReservation) {
            return $isPast ? self::Past : self::Free;
        }

        return match (true) {
            $isTournament => self::Tournament,
            $isPast => self::Past,
            $isSeeking && ! $isOwn => self::Seeking,
            $isOwn => self::Own,
            $isSeries => self::Series,
            default => self::SingleFuture,
        };
    }

    /** @return list<self> */
    public static function legend(bool $loggedIn): array
    {
        $states = [self::Free, self::Own, self::SingleFuture, self::Series, self::Seeking, self::Event, self::Blocked, self::Tournament, self::Past];

        return array_values(array_filter($states, fn (self $s) => $loggedIn || $s !== self::Own));
    }

    public function legendKey(): string
    {
        return 'booking.calendar.legend.'.$this->value;
    }
}
