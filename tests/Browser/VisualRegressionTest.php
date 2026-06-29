<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Booking;
use App\Models\Event;
use App\Models\EventMeta;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\SquareMeta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class VisualRegressionTest extends DuskTestCase
{
    use DatabaseMigrations;

    private const CALENDAR_DATE = '2026-07-01';
    private const UPDATE_ENV = 'UPDATE_DUSK_SNAPSHOTS';

    public function test_calendar_desktop_visual_regression(): void
    {
        $this->seedCalendarState();

        $this->browse(function (Browser $browser): void {
            $browser->resize(1440, 900)
                ->visit('/calendar?date='.self::CALENDAR_DATE)
                ->waitFor('#calendar-grid', 10)
                ->pause(300);

            $this->assertScreenshotMatchesBaseline($browser, 'calendar-desktop');
        });
    }

    public function test_calendar_mobile_visual_regression(): void
    {
        $this->seedCalendarState();

        $this->browse(function (Browser $browser): void {
            $browser->resize(390, 844)
                ->visit('/calendar?date='.self::CALENDAR_DATE)
                ->waitFor('#calendar-grid', 10)
                ->pause(300);

            $this->assertScreenshotMatchesBaseline($browser, 'calendar-mobile');
        });
    }

    public function test_booking_modal_visual_regression(): void
    {
        $member = $this->seedCalendarState();

        $this->browse(function (Browser $browser) use ($member): void {
            $browser->resize(1024, 768)
                ->loginAs($member)
                ->visit('/calendar?date='.self::CALENDAR_DATE)
                ->waitFor('#calendar-grid', 10)
                ->script("document.querySelector('.cc-free')?.click();");

            $browser->waitForText('Neue Buchung', 10)
                ->pause(300);

            $this->assertScreenshotMatchesBaseline($browser, 'booking-modal');
        });
    }

    private function seedCalendarState(): User
    {
        $squares = collect([1, 2, 3])->map(function (int $number): Square {
            $square = Square::factory()->create([
                'name' => (string) $number,
                'priority' => $number,
                'capacity' => 4,
                'capacity_heterogenic' => 1,
                'range_book' => 0,
                'time_block_bookable_max' => 0,
            ]);

            SquareMeta::create([
                'sid' => $square->sid,
                'key' => 'alias',
                'value' => match ($number) {
                    1 => 'Court B',
                    2 => 'Court D',
                    default => 'Court C',
                },
            ]);

            return $square;
        });

        $member = User::factory()->create([
            'alias' => 'Heinz Mayer',
            'email' => 'heinz@example.test',
            'status' => 'enabled',
        ]);
        $other = User::factory()->create([
            'alias' => 'Seniorendoppel Fredi',
            'status' => 'enabled',
        ]);

        $this->createReservation($member, $squares[0], self::CALENDAR_DATE, '11:00:00', '12:00:00', ['Helga']);
        $this->createReservation($other, $squares[1], self::CALENDAR_DATE, '09:00:00', '11:00:00', ['Alfred Lechner (17)']);
        $this->createReservation($member, $squares[2], self::CALENDAR_DATE, '17:00:00', '18:00:00', ['Helga und Sandra']);

        $event = Event::factory()->create([
            'sid' => null,
            'status' => 'enabled',
            'datetime_start' => Carbon::parse(self::CALENDAR_DATE.' 12:00:00'),
            'datetime_end' => Carbon::parse(self::CALENDAR_DATE.' 13:00:00'),
        ]);
        EventMeta::create([
            'eid' => $event->eid,
            'key' => 'name',
            'value' => 'TESTSPIEL',
        ]);

        return $member;
    }

    /** @param list<string> $playerNames */
    private function createReservation(User $user, Square $square, string $date, string $from, string $to, array $playerNames): void
    {
        $booking = Booking::factory()->create([
            'uid' => $user->uid,
            'sid' => $square->sid,
            'status' => 'single',
            'visibility' => 'public',
            'quantity' => count($playerNames) >= 3 ? 4 : 2,
        ]);

        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => $date,
            'time_start' => $from,
            'time_end' => $to,
        ]);

        if ($playerNames !== []) {
            $booking->meta()->create([
                'key' => 'player-names',
                'value' => serialize(array_map(
                    static fn (string $name, int $index): array => [
                        'name' => 'sb-player-name-'.($index + 2),
                        'value' => $name,
                    ],
                    $playerNames,
                    array_keys($playerNames),
                )),
            ]);
        }
    }

    private function assertScreenshotMatchesBaseline(Browser $browser, string $name, float $allowedMismatchRatio = 0.002): void
    {
        $browser->screenshot($name);

        $actual = base_path('tests/Browser/screenshots/'.$name.'.png');
        $baseline = base_path('tests/Browser/baselines/'.$name.'.png');

        if ($this->shouldUpdateSnapshots()) {
            copy($actual, $baseline);
            $this->addToAssertionCount(1);
            return;
        }

        Assert::assertFileExists($baseline, "Missing visual baseline for {$name}. Run with ".self::UPDATE_ENV.'=1 to create it.');

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('The gd extension is required for visual screenshot comparison.');
        }

        [$widthA, $heightA] = getimagesize($actual) ?: [0, 0];
        [$widthB, $heightB] = getimagesize($baseline) ?: [0, 0];

        Assert::assertSame([$widthB, $heightB], [$widthA, $heightA], "Screenshot size changed for {$name}.");

        $actualImage = imagecreatefrompng($actual);
        $baselineImage = imagecreatefrompng($baseline);

        Assert::assertNotFalse($actualImage, "Could not read actual screenshot for {$name}.");
        Assert::assertNotFalse($baselineImage, "Could not read baseline screenshot for {$name}.");

        $changed = 0;
        $total = $widthA * $heightA;

        for ($y = 0; $y < $heightA; $y++) {
            for ($x = 0; $x < $widthA; $x++) {
                if ($this->pixelDistance(imagecolorat($actualImage, $x, $y), imagecolorat($baselineImage, $x, $y)) > 24) {
                    $changed++;
                }
            }
        }

        imagedestroy($actualImage);
        imagedestroy($baselineImage);

        $ratio = $total > 0 ? $changed / $total : 1.0;

        Assert::assertLessThanOrEqual(
            $allowedMismatchRatio,
            $ratio,
            sprintf('Visual regression for %s: %.3f%% changed pixels.', $name, $ratio * 100),
        );
    }

    private function pixelDistance(int $a, int $b): int
    {
        return abs((($a >> 16) & 0xFF) - (($b >> 16) & 0xFF))
            + abs((($a >> 8) & 0xFF) - (($b >> 8) & 0xFF))
            + abs(($a & 0xFF) - ($b & 0xFF));
    }

    private function shouldUpdateSnapshots(): bool
    {
        return filter_var(getenv(self::UPDATE_ENV) ?: env(self::UPDATE_ENV), FILTER_VALIDATE_BOOL);
    }
}