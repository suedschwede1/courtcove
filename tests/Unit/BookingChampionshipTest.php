<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\BookingMeta;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingChampionshipTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string, bool}> */
    public static function notesProvider(): array
    {
        return [
            'mixed case with suffix' => ['Vereinsmeisterschaft Runde 1', true],
            'upper case' => ['VEREINSMEISTERSCHAFT', true],
            'embedded in text' => ['Match der vereinsmeisterschaft 2026', true],
            'unrelated note' => ['Training', false],
            'empty note' => ['', false],
        ];
    }

    #[Test]
    #[DataProvider('notesProvider')]
    public function it_detects_championship_notes(string $notes, bool $expected): void
    {
        $booking = Booking::factory()->create();
        BookingMeta::create(['bid' => $booking->bid, 'key' => 'notes', 'value' => $notes]);

        $this->assertSame($expected, $booking->fresh()->isChampionshipMatch());
    }

    #[Test]
    public function it_is_false_without_notes_meta(): void
    {
        $booking = Booking::factory()->create();
        BookingMeta::create(['bid' => $booking->bid, 'key' => 'owner-name', 'value' => 'Vereinsmeisterschaft']);

        $this->assertFalse($booking->fresh()->isChampionshipMatch());
    }
}
