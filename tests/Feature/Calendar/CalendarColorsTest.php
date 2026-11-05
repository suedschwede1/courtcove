<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CalendarColorsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string, string}> dotted key, default */
    public static function defaults(): array
    {
        $rows = [
            'free.bg' => 'var(--color-surface)',
            'free.bg_hover' => 'var(--color-clay-soft)',
            'free.line' => 'var(--color-cell-free-line)',
            'own.bg' => 'var(--color-clay)',
            'own.bg_hover' => 'var(--color-clay-hover)',
            'own.accent' => 'var(--color-clay-hover)',
            'own.label' => 'var(--color-on-clay)',
            'own.label_secondary' => 'var(--color-on-clay)',
            'single_future.bg' => 'var(--color-cell-booked)',
            'single_future.bg_hover' => 'var(--color-line)',
            'single_future.label' => 'var(--color-ink)',
            'single_future.label_secondary' => 'var(--color-ink-2)',
            'series.accent' => 'var(--color-line-strong)',
            'series.label' => 'var(--color-ink-2)',
            'past.bg' => 'var(--color-cell-past-a)',
            'past.bg_alt' => 'var(--color-cell-past-b)',
            'past.label' => 'var(--color-ink-3)',
            'event.bg' => 'var(--color-court)',
            'event.bg_hover' => 'var(--color-court-ink)',
            'event.accent' => 'var(--color-court)',
            'event.label' => 'var(--color-on-court)',
            'tournament.bg' => 'var(--color-court-soft)',
            'tournament.line' => 'var(--color-court)',
            'tournament.label' => 'var(--color-court-ink)',
        ];

        $out = [];
        foreach ($rows as $key => $value) {
            $out[$key] = [$key, $value];
        }

        return $out;
    }

    /** @return array<string, array<string, string>> */
    private function freshColors(): array
    {
        return (require config_path('calendar.php'))['colors'];
    }

    private function skipOnCustomisedMachine(): void
    {
        if (file_exists(config_path('calendar.local.php'))) {
            $this->markTestSkipped('config/calendar.local.php exists on this machine.');
        }
        foreach (array_merge(array_keys($_ENV), array_keys($_SERVER)) as $name) {
            if (is_string($name) && str_starts_with($name, 'CAL_COLOR_')) {
                $this->markTestSkipped('A CAL_COLOR_* environment variable is set on this machine.');
            }
        }
    }

    #[Test]
    #[DataProvider('defaults')]
    public function default_colour_maps_to_a_token(string $key, string $value): void
    {
        $this->skipOnCustomisedMachine();

        $this->assertSame($value, Arr::get($this->freshColors(), $key));
    }

    #[Test]
    public function env_override_still_wins(): void
    {
        $_SERVER['CAL_COLOR_OWN_BG'] = $_ENV['CAL_COLOR_OWN_BG'] = '#123456';
        putenv('CAL_COLOR_OWN_BG=#123456');

        try {
            $this->assertSame('#123456', Arr::get($this->freshColors(), 'own.bg'));
        } finally {
            unset($_SERVER['CAL_COLOR_OWN_BG'], $_ENV['CAL_COLOR_OWN_BG']);
            putenv('CAL_COLOR_OWN_BG');
        }
    }

    #[Test]
    public function local_override_file_still_wins(): void
    {
        $file = config_path('calendar.local.php');
        if (file_exists($file)) {
            $this->markTestSkipped('config/calendar.local.php exists on this machine.');
        }

        file_put_contents($file, "<?php return ['tournament' => ['bg' => '#654321']];");

        try {
            $colors = $this->freshColors();
            $this->assertSame('#654321', Arr::get($colors, 'tournament.bg'));
            $this->assertSame('var(--color-court)', Arr::get($colors, 'tournament.line'));
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function layout_exports_every_colour_as_a_cal_variable(): void
    {
        $html = $this->get('/calendar')->assertOk()->getContent();

        foreach (Arr::dot(config('calendar.colors')) as $key => $value) {
            [$group, $name] = explode('.', $key);
            $var = '--cal-'.str_replace('_', '-', $group).'-'.str_replace('_', '-', $name);
            $this->assertStringContainsString($var.': '.$value.';', $html, $key);
        }

        $this->assertStringContainsString('--cal-own-bg: var(--color-clay);', $html);
    }
}
