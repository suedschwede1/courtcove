<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhoneBarComponentTest extends TestCase
{
    #[Test]
    public function it_renders_back_arrow_eyebrow_and_title_on_the_chrome_colour(): void
    {
        $html = (string) $this->blade('<x-layout.phone-bar title="Herren 2026" eyebrow="Pyramide verwalten" back="/pyramide/admin" back-label="Zurück" />');

        $this->assertStringContainsString('class="ds-phone-bar ds-on-chrome no-print"', $html);
        $this->assertStringContainsString('href="/pyramide/admin"', $html);
        $this->assertStringContainsString('aria-label="Zurück"', $html);
        $this->assertStringContainsString('ds-icon-btn--chrome', $html);
        $this->assertStringContainsString('<p class="ds-phone-bar__eyebrow">Pyramide verwalten</p>', $html);
        $this->assertStringContainsString('<p class="ds-phone-bar__title">Herren 2026</p>', $html);
    }

    #[Test]
    public function the_title_can_be_the_page_heading_and_eyebrow_and_arrow_are_optional(): void
    {
        $html = (string) $this->blade('<x-layout.phone-bar title="Forderung" heading />');

        $this->assertStringContainsString('<h1 class="ds-phone-bar__title">Forderung</h1>', $html);
        $this->assertStringNotContainsString('ds-phone-bar__eyebrow', $html);
        $this->assertStringNotContainsString('ds-icon-btn', $html);
    }

    #[Test]
    public function the_arrow_falls_back_to_the_generic_back_label(): void
    {
        app()->setLocale('de');

        $html = (string) $this->blade('<x-layout.phone-bar title="Forderung" back="/x" />');

        $this->assertStringContainsString('aria-label="Zurück"', $html);
    }
}
