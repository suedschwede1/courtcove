<?php

namespace Tests\Unit\Css;

use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CssBlocks;
use Tests\TestCase;

final class ModalCssTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(resource_path('css/ds.css'));
    }

    private function mediaBlock(string $query): string
    {
        $css = $this->css();
        $offset = 0;
        while (($pos = strpos($css, '@media '.$query.' {', $offset)) !== false) {
            $body = CssBlocks::body(substr($css, $pos), '@media '.$query);
            if (str_contains($body, '.ds-modal')) {
                return $body;
            }
            $offset = $pos + 1;
        }

        return '';
    }

    #[Test]
    public function dialog_uses_dialog_tokens(): void
    {
        $css = $this->css();
        $panel = CssBlocks::body($css, '.ds-modal__panel');
        $this->assertStringContainsString('var(--shadow-dialog)', $panel);
        $this->assertStringContainsString('border-radius: 14px', $panel);
        $this->assertStringContainsString('var(--color-surface-sunk)', CssBlocks::body($css, '.ds-modal__footer'));
        $this->assertStringContainsString('var(--ds-backdrop)', CssBlocks::body($css, '.ds-modal__backdrop'));
        $this->assertStringContainsString('--ds-backdrop: rgba(29, 27, 24, 0.55);', (string) file_get_contents(resource_path('css/app.css')));
        $this->assertStringContainsString('var(--color-ground)', CssBlocks::body($css, '.ds-summary__item'));
    }

    #[Test]
    public function sheet_fits_the_dynamic_viewport(): void
    {
        $sheet = $this->mediaBlock('(width < 48rem)');
        foreach ([
            'var(--shadow-sheet)',
            'var(--radius-xl) var(--radius-xl) 0 0',
            'align-items: flex-end',
            '100dvh',
            'env(safe-area-inset-bottom)',
            '.ds-modal__handle',
            'column-reverse',
        ] as $needle) {
            $this->assertStringContainsString($needle, $sheet);
        }

        $body = CssBlocks::body($this->css(), '.ds-modal__body');
        $this->assertStringContainsString('overflow-y: auto', $body);
        $this->assertStringContainsString('min-height: 0', $body);
    }

    #[Test]
    public function modal_respects_reduced_motion(): void
    {
        $block = $this->mediaBlock('(prefers-reduced-motion: reduce)');
        $this->assertStringContainsString('.ds-modal', $block);
        $this->assertStringContainsString('transition: none !important', $block);
    }
}
