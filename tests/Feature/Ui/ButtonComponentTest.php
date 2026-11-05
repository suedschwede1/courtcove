<?php

namespace Tests\Feature\Ui;

use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\NormalizesHtml;
use Tests\TestCase;

class ButtonComponentTest extends TestCase
{
    use NormalizesHtml;

    #[Test]
    public function default_button_is_primary_block(): void
    {
        $this->assertSame(
            '<button type="button" class="ds-btn ds-btn--primary ds-btn--block"><span>Save</span></button>',
            $this->html((string) $this->blade('<x-ui.button>Save</x-ui.button>')),
        );
    }

    #[Test]
    public function secondary_submit_inline(): void
    {
        $this->assertSame(
            '<button type="submit" class="ds-btn ds-btn--secondary"><span>Cancel</span></button>',
            $this->html((string) $this->blade('<x-ui.button variant="secondary" type="submit" :block="false">Cancel</x-ui.button>')),
        );
    }

    #[Test]
    public function link_button_has_no_type(): void
    {
        $this->assertSame(
            '<a href="https://example.test/x" class="ds-btn ds-btn--danger ds-btn--block"><span>Delete</span></a>',
            $this->html((string) $this->blade('<x-ui.button href="https://example.test/x" variant="danger">Delete</x-ui.button>')),
        );
    }

    #[Test]
    public function icon_precedes_label_and_disabled_is_rendered(): void
    {
        $html = $this->html((string) $this->blade('<x-ui.button variant="dark" icon="plus" disabled>Add</x-ui.button>'));

        $this->assertLessThan(strpos($html, '<span>Add</span>'), strpos($html, '<svg'));
        $this->assertStringContainsString('disabled', $html);
    }

    #[Test]
    public function disabled_link_uses_aria(): void
    {
        $html = $this->html((string) $this->blade('<x-ui.button href="https://example.test/x" disabled>Go</x-ui.button>'));

        $this->assertStringContainsString('aria-disabled="true" tabindex="-1"', $html);
        $this->assertStringNotContainsString(' disabled', $html);
    }

    #[Test]
    public function attributes_are_merged(): void
    {
        $html = $this->html((string) $this->blade('<x-ui.button class="mt-2" data-x="1">A</x-ui.button>'));

        $this->assertStringContainsString('class="ds-btn ds-btn--primary ds-btn--block mt-2"', $html);
        $this->assertStringContainsString('data-x="1"', $html);
    }

    #[Test]
    public function icon_button_markup(): void
    {
        $html = $this->html((string) $this->blade('<x-ui.icon-button icon="chevron-left" label="Vorheriger Tag" />'));

        $this->assertStringStartsWith('<button type="button" class="ds-icon-btn ds-icon-btn--secondary" aria-label="Vorheriger Tag"><svg', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringEndsWith('</svg></button>', $html);
    }

    #[Test]
    public function icon_button_link(): void
    {
        $html = $this->html((string) $this->blade('<x-ui.icon-button icon="chevron-left" label="Zurück" href="https://example.test/y" />'));

        $this->assertStringStartsWith('<a href="https://example.test/y" class="ds-icon-btn ds-icon-btn--secondary" aria-label="Zurück"><svg', $html);
        $this->assertStringEndsWith('</svg></a>', $html);
    }

    #[Test]
    public function unknown_variant_throws(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Unknown button variant [x].');

        $this->blade('<x-ui.button variant="x">A</x-ui.button>');
    }

    #[Test]
    public function icon_button_without_label_throws(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Icon button needs a label.');

        $this->blade('<x-ui.icon-button icon="plus" />');
    }
}
