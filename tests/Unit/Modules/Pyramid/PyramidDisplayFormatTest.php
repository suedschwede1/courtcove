<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Pyramid;

use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Support\LadderChips;
use App\Modules\Pyramid\Support\PyramidFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PyramidDisplayFormatTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function names(): array
    {
        return [
            'first and last name' => ['Karl Beispiel', 'K. Beispiel'],
            'several last-name words' => ['Anna van der Berg', 'A. van der Berg'],
            'single word' => ['Beispiel', 'Beispiel'],
            'already an initial' => ['K. Beispiel', 'K. Beispiel'],
            'single letter first name' => ['K Beispiel', 'K Beispiel'],
            'extra blanks' => ["  Karl   Beispiel \n", 'K. Beispiel'],
            'umlaut initial' => ['Ümit Kaya', 'Ü. Kaya'],
            'empty' => ['', ''],
            'null' => [null, ''],
        ];
    }

    #[Test]
    #[DataProvider('names')]
    public function names_are_abbreviated_like_the_mockup(?string $label, string $expected): void
    {
        $this->assertSame($expected, PyramidFormat::shortName($label));
    }

    #[Test]
    public function day_time_and_short_date_follow_the_locale(): void
    {
        app()->setLocale('de');
        $this->assertSame('Sa, 7. Nov 10:00', PyramidFormat::dayTime('2026-11-07 10:00:00'));
        $this->assertSame('18.11.', PyramidFormat::shortDate('2026-11-18 23:59:59'));

        app()->setLocale('en');
        $this->assertSame('Sat, Nov 7 10:00', PyramidFormat::dayTime('2026-11-07 10:00:00'));
        $this->assertSame('Nov 18', PyramidFormat::shortDate('2026-11-18'));
    }

    #[Test]
    public function broken_or_empty_dates_render_as_a_dash(): void
    {
        foreach ([null, '', '  ', 'not a date'] as $value) {
            $this->assertSame('–', PyramidFormat::dayTime($value));
            $this->assertSame('–', PyramidFormat::shortDate($value));
            $this->assertSame('–', PyramidFormat::safeTime($value));
            $this->assertSame('–', PyramidFormat::safeDateTime($value));
        }
    }

    #[Test]
    public function chips_are_named_by_category_and_never_repeat(): void
    {
        $herren = new PyramidLadder(['id' => 1, 'title' => 'Herren 2027', 'season' => 2027, 'category' => 'Herren']);
        $damen = new PyramidLadder(['id' => 2, 'title' => 'Damen 2027', 'season' => 2027, 'category' => 'Damen']);
        $old = new PyramidLadder(['id' => 3, 'title' => 'Herren 2026', 'season' => 2026, 'category' => 'Herren']);

        $labels = static fn (array $ladders): array => array_column(LadderChips::for($ladders), 'label');

        // One season: just the category, like the mockup.
        $this->assertSame(['Damen', 'Herren'], $labels([$herren, $damen]));
        // Several seasons: the season is added, newest first.
        $this->assertSame(['Damen · 2027', 'Herren · 2027', 'Herren · 2026'], $labels([$herren, $damen, $old]));
    }

    #[Test]
    public function chips_without_a_category_use_the_title(): void
    {
        $a = new PyramidLadder(['id' => 1, 'title' => 'Demo Herren 2027', 'season' => 2027, 'category' => null]);
        $b = new PyramidLadder(['id' => 2, 'title' => 'Demo Damen 2027', 'season' => 2027, 'category' => null]);
        $c = new PyramidLadder(['id' => 3, 'title' => 'Demo Herren 2026', 'season' => 2026, 'category' => null]);

        $labels = array_column(LadderChips::for([$a, $b, $c]), 'label');

        $this->assertSame(['Demo Herren 2027', 'Demo Damen 2027', 'Demo Herren 2026'], $labels);
        $this->assertSame($labels, array_values(array_unique($labels)));
    }

    #[Test]
    public function a_title_without_the_season_gets_it_appended_when_seasons_differ(): void
    {
        $a = new PyramidLadder(['id' => 1, 'title' => 'Demo Herren', 'season' => 2027, 'category' => null]);
        $b = new PyramidLadder(['id' => 2, 'title' => 'Demo Damen', 'season' => 2027, 'category' => null]);
        $c = new PyramidLadder(['id' => 3, 'title' => 'Demo Herren', 'season' => 2026, 'category' => null]);

        $this->assertSame(['Demo Herren · 2027', 'Demo Damen · 2027', 'Demo Herren · 2026'], array_column(LadderChips::for([$a, $b, $c]), 'label'));
    }

    #[Test]
    public function equal_category_and_season_fall_back_to_the_title_and_id(): void
    {
        $a = new PyramidLadder(['id' => 1, 'title' => 'Herren A', 'season' => 2027, 'category' => 'Herren']);
        $b = new PyramidLadder(['id' => 2, 'title' => 'Herren B', 'season' => 2027, 'category' => 'Herren']);

        $this->assertSame(['Herren A #1', 'Herren B #2'], array_column(LadderChips::for([$a, $b]), 'label'));
    }
}
