<?php

namespace Tests\Feature\Ui;

use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\NormalizesHtml;
use Tests\TestCase;

class TabsComponentTest extends TestCase
{
    use NormalizesHtml;

    private function render(string $template, array $data = []): string
    {
        return $this->html((string) $this->blade($template, $data));
    }

    #[Test]
    public function renders_tab_bar_with_current_page_and_count(): void
    {
        $this->assertSame(
            '<nav class="ds-tabs" aria-label="Meisterschaft"><a href="https://example.test/a" class="ds-tabs__tab" aria-current="page">Übersicht</a><a href="https://example.test/b" class="ds-tabs__tab">Gruppen<span class="ds-tabs__count">4</span></a></nav>',
            $this->render('<x-ui.tabs label="Meisterschaft" :items="[[\'label\' => \'Übersicht\', \'url\' => \'https://example.test/a\', \'active\' => true], [\'label\' => \'Gruppen\', \'url\' => \'https://example.test/b\', \'active\' => false, \'count\' => 4]]" />'),
        );
    }

    #[Test]
    public function escapes_label_and_url(): void
    {
        $html = $this->render('<x-ui.tabs label="X" :items="$items" />', [
            'items' => [['label' => '<b>x</b>', 'url' => 'https://example.test/?a="1"&b=2']],
        ]);

        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('&quot;1&quot;', $html);
    }

    #[Test]
    public function item_without_url_throws(): void
    {
        try {
            $this->render('<x-ui.tabs label="X" :items="[[\'label\' => \'A\']]" />');
            $this->fail('Expected exception.');
        } catch (ViewException $e) {
            $this->assertStringContainsString('Tab item needs label and url.', $e->getMessage());
        }
    }

    #[Test]
    public function chip_link_and_button_variants(): void
    {
        $this->assertSame(
            '<a href="https://example.test/f" class="ds-chip" aria-current="true">Offen</a>',
            $this->render('<x-ui.chip href="https://example.test/f" :active="true">Offen</x-ui.chip>'),
        );
        $this->assertSame(
            '<button type="button" class="ds-chip" aria-pressed="false">Alle</button>',
            $this->render('<x-ui.chip>Alle</x-ui.chip>'),
        );
        $this->assertSame(
            '<a href="https://example.test/f" class="ds-chip">Zu</a>',
            $this->render('<x-ui.chip href="https://example.test/f">Zu</x-ui.chip>'),
        );
    }
}
