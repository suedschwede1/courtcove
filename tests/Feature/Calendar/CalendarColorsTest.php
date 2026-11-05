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
            'own.bg' => 'var(--color-cell-own)',
            'own.line' => 'var(--color-cell-own-line)',
            'own.label' => 'var(--color-cell-own-ink)',
            'own.label_secondary' => 'var(--color-cell-own-ink)',
            'single_future.bg' => 'var(--color-cell-booked)',
            'single_future.label' => 'var(--color-ink)',
            'single_future.label_secondary' => 'var(--color-ink-2)',
            'series.bg' => 'var(--color-cell-series)',
            'series.label' => 'var(--color-cell-series-ink)',
            'past.bg' => 'var(--color-cell-past-a)',
            'past.bg_alt' => 'var(--color-cell-past-b)',
            'past.label' => 'var(--color-ink-3)',
            'event.bg' => 'var(--color-cell-event)',
            'event.line' => 'var(--color-cell-event-line)',
            'event.label' => 'var(--color-cell-event-ink)',
            'blocked.a' => 'var(--color-cell-blocked-a)',
            'blocked.b' => 'var(--color-cell-blocked-b)',
            'blocked.label' => 'var(--color-ink-2)',
            'tournament.bg' => 'var(--color-cell-match)',
            'tournament.line' => 'var(--color-cell-match-line)',
            'tournament.label' => 'var(--color-cell-match-ink)',
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

    private function customisationReason(): ?string
    {
        if (file_exists(config_path('calendar.local.php'))) {
            return 'config/calendar.local.php exists on this machine.';
        }
        foreach (array_merge(array_keys($_ENV), array_keys($_SERVER)) as $name) {
            if (is_string($name) && str_starts_with($name, 'CAL_COLOR_')) {
                return 'A CAL_COLOR_* environment variable is set on this machine.';
            }
        }

        return null;
    }

    private function skipOnCustomisedMachine(): void
    {
        $reason = $this->customisationReason();
        if ($reason !== null) {
            $this->markTestSkipped($reason);
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
        $this->skipOnCustomisedMachine();

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
        $this->skipOnCustomisedMachine();

        $file = config_path('calendar.local.php');
        // A dying process must not leave the override file behind.
        register_shutdown_function(static function () use ($file): void {
            if (is_file($file)) {
                @unlink($file);
            }
        });

        file_put_contents($file, "<?php return ['tournament' => ['bg' => '#654321']];");

        try {
            $colors = $this->freshColors();
            $this->assertSame('#654321', Arr::get($colors, 'tournament.bg'));
            $this->assertSame('var(--color-cell-match-line)', Arr::get($colors, 'tournament.line'));
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
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

        if ($this->customisationReason() === null) {
            $this->assertStringContainsString('--cal-own-bg: var(--color-cell-own);', $html);
        }
    }
}
