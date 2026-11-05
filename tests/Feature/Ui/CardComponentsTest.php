<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\Support\NormalizesHtml;
use Tests\TestCase;

class CardComponentsTest extends TestCase
{
    use NormalizesHtml;

    private function render(string $template): string
    {
        return $this->html((string) $this->blade($template));
    }

    #[Test]
    public function card_with_title(): void
    {
        $this->assertSame(
            '<section class="ds-card ds-card--default ds-card--pad-md"><header class="ds-card__header"><h2 class="ds-card__title">Lose</h2></header><div class="ds-card__body">Text</div></section>',
            $this->render('<x-ui.card title="Lose">Text</x-ui.card>'),
        );
    }

    #[Test]
    public function card_with_eyebrow_actions_and_footer(): void
    {
        $html = $this->render('<x-ui.card title="T" eyebrow="MEINE FORDERUNG" :level="3"> <x-slot:actions><a href="#a">x</a></x-slot:actions> B<x-slot:footer>F</x-slot:footer></x-ui.card>');

        $this->assertStringContainsString('<p class="ds-card__eyebrow">MEINE FORDERUNG</p>', $html);
        $this->assertStringContainsString('<h3 class="ds-card__title">T</h3>', $html);
        $this->assertStringContainsString('<div class="ds-card__actions"><a href="#a">x</a></div>', $html);
        $this->assertStringContainsString('<footer class="ds-card__footer">F</footer>', $html);
    }

    #[Test]
    public function card_variants(): void
    {
        $this->assertStringNotContainsString('<header', $this->render('<x-ui.card>B</x-ui.card>'));
        $this->assertStringContainsString('ds-card--highlight ds-on-chrome', $this->render('<x-ui.card tone="highlight">B</x-ui.card>'));
        $this->assertStringContainsString('ds-card--pad-none', $this->render('<x-ui.card padding="none">B</x-ui.card>'));
        $this->assertStringStartsWith('<article class="ds-card', $this->render('<x-ui.card as="article">B</x-ui.card>'));
    }

    #[Test]
    public function disclosure(): void
    {
        $this->assertSame(
            '<details class="ds-card ds-disclosure"><summary class="ds-disclosure__summary">Nachrechnen</summary><div class="ds-disclosure__body">X</div></details>',
            $this->render('<x-ui.disclosure summary="Nachrechnen">X</x-ui.disclosure>'),
        );
        $this->assertStringContainsString('<details class="ds-card ds-disclosure" open>', $this->render('<x-ui.disclosure summary="S" :open="true">X</x-ui.disclosure>'));
    }

    #[Test]
    public function kpi(): void
    {
        $this->assertSame(
            '<div class="ds-kpi"><p class="ds-kpi__label">Buchungen</p><p class="ds-kpi__value">18/30</p><p class="ds-kpi__meta">diese Woche</p></div>',
            $this->render('<x-ui.kpi label="Buchungen" value="18/30" meta="diese Woche" />'),
        );
    }

    #[Test]
    public function notice(): void
    {
        $this->assertSame(
            '<div class="ds-notice ds-notice--problem" role="alert">E</div>',
            $this->render('<x-ui.notice tone="problem">E</x-ui.notice>'),
        );
        $html = $this->render('<x-ui.notice title="Hinweis">E</x-ui.notice>');
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('ds-notice--attention', $html);
        $this->assertStringContainsString('<p class="ds-notice__title">Hinweis</p>', $html);
    }
}
