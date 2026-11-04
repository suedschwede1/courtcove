<?php

namespace Tests\Feature\Ui;

use App\Support\Ui\Icons;
use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\NormalizesHtml;
use Tests\TestCase;

class IconComponentTest extends TestCase
{
    use NormalizesHtml;

    #[Test]
    public function decorative_icon_markup(): void
    {
        $this->assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ds-icon" aria-hidden="true" focusable="false"><path d="M5 12l5 5 9-10"/></svg>',
            $this->html((string) $this->blade('<x-ui.icon name="check" />')),
        );
    }

    #[Test]
    public function labelled_icon_is_an_image(): void
    {
        $html = $this->html((string) $this->blade('<x-ui.icon name="close" :size="24" label="Schließen" />'));

        $this->assertStringContainsString('width="24"', $html);
        $this->assertStringContainsString('role="img" aria-label="Schließen"', $html);
        $this->assertStringNotContainsString('aria-hidden', $html);
    }

    #[Test]
    public function unknown_icon_throws(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Unknown icon [nope]');

        $this->blade('<x-ui.icon name="nope" />');
    }

    #[Test]
    public function every_icon_renders(): void
    {
        foreach (array_keys(Icons::PATHS) as $name) {
            $html = (string) $this->blade('<x-ui.icon name="'.$name.'" />');
            $this->assertStringStartsWith('<svg', trim($html), $name);
            $this->assertStringNotContainsString('<script', $html, $name);
        }
    }
}
