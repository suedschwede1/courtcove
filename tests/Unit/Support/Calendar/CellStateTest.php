<?php

namespace Tests\Unit\Support\Calendar;

use App\Support\Calendar\CellState;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CellStateTest extends TestCase
{
    /** @return array<string, array{bool, bool, bool, bool, bool, bool, CellState}> hasReservation, isPast, isOwn, isSeries, isTournament, isSeeking, expected */
    public static function matrix(): array
    {
        return [
            'empty future' => [false, false, false, false, false, false, CellState::Free],
            'empty past' => [false, true, false, false, false, false, CellState::Past],
            'own future' => [true, false, true, false, false, false, CellState::Own],
            'own series' => [true, false, true, true, false, false, CellState::Own],
            'other future' => [true, false, false, false, false, false, CellState::SingleFuture],
            'other series' => [true, false, false, true, false, false, CellState::Series],
            'own past' => [true, true, true, false, false, false, CellState::Past],
            'tournament future' => [true, false, false, false, true, false, CellState::Tournament],
            'tournament own past' => [true, true, true, false, true, false, CellState::Tournament],
            'other seeking' => [true, false, false, false, false, true, CellState::Seeking],
            'own seeking stays own' => [true, false, true, false, false, true, CellState::Own],
            'seeking past' => [true, true, false, false, false, true, CellState::Past],
            'seeking tournament' => [true, false, false, false, true, true, CellState::Tournament],
            'seeking without reservation' => [false, false, false, false, false, true, CellState::Free],
        ];
    }

    #[Test]
    #[DataProvider('matrix')]
    public function state_follows_the_precedence(bool $hasReservation, bool $isPast, bool $isOwn, bool $isSeries, bool $isTournament, bool $isSeeking, CellState $expected): void
    {
        $this->assertSame($expected, CellState::for($hasReservation, $isPast, $isOwn, $isSeries, $isTournament, $isSeeking));
    }

    #[Test]
    public function legend_order_and_guest_variant(): void
    {
        $values = fn (bool $loggedIn) => array_map(fn (CellState $s) => $s->value, CellState::legend($loggedIn));

        $this->assertSame(['free', 'own', 'single_future', 'series', 'seeking', 'event', 'blocked', 'tournament', 'past'], $values(true));
        $this->assertSame(['free', 'single_future', 'series', 'seeking', 'event', 'blocked', 'tournament', 'past'], $values(false));
    }

    #[Test]
    public function every_legend_key_exists_in_both_locales(): void
    {
        foreach (CellState::legend(true) as $state) {
            foreach (['de', 'en'] as $locale) {
                $this->assertTrue(Lang::has($state->legendKey(), $locale, false), $state->legendKey().' missing in '.$locale);
            }
        }
        foreach (['de', 'en'] as $locale) {
            $this->assertTrue(Lang::has('booking.calendar.legend.label', $locale, false));
        }
    }
}
