<?php

namespace Tests\Feature\Ui;

use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\NormalizesHtml;
use Tests\TestCase;

class BadgeComponentTest extends TestCase
{
    use NormalizesHtml;

    #[Test]
    public function status_renders_tone_and_label(): void
    {
        app()->setLocale('de');

        $this->assertSame(
            '<span class="ds-badge ds-badge--good">gespielt</span>',
            $this->html((string) $this->blade('<x-ui.badge status="played" />')),
        );
    }

    #[Test]
    public function explicit_tone_with_slot(): void
    {
        $this->assertSame(
            '<span class="ds-badge ds-badge--problem">Gesperrt</span>',
            $this->html((string) $this->blade('<x-ui.badge tone="problem">Gesperrt</x-ui.badge>')),
        );
    }

    #[Test]
    public function you_status(): void
    {
        app()->setLocale('de');
        $html = $this->html((string) $this->blade('<x-ui.badge status="you" />'));

        $this->assertStringContainsString('ds-badge--you', $html);
        $this->assertStringContainsString('>du<', $html);
    }

    #[Test]
    public function label_follows_locale(): void
    {
        app()->setLocale('en');

        $this->assertStringContainsString('>played<', $this->html((string) $this->blade('<x-ui.badge status="played" />')));
    }

    #[Test]
    public function tone_or_status_is_required(): void
    {
        $this->expectException(ViewException::class);

        $this->blade('<x-ui.badge />');
    }
}
