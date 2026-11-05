<?php

namespace Tests\Feature\Ui;

use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\NormalizesHtml;
use Tests\TestCase;

class TableComponentTest extends TestCase
{
    use NormalizesHtml;

    private function render(string $template, array $data = []): string
    {
        return $this->html((string) $this->blade($template, $data));
    }

    #[Test]
    public function list_table_with_head_and_stack(): void
    {
        $this->assertSame(
            '<div class="ds-table-wrap"><table class="ds-table ds-table--list ds-table--stack"><caption class="sr-only">Ergebnis</caption><thead><tr><th scope="col">Slot</th><th scope="col">Los</th></tr></thead><tbody><tr><td data-label="Slot">A1</td><td data-label="Los">101</td></tr></tbody></table></div>',
            $this->render('<x-ui.table caption="Ergebnis"><x-slot:head><tr><th scope="col">Slot</th><th scope="col">Los</th></tr></x-slot:head> <tr><td data-label="Slot">A1</td><td data-label="Los">101</td></tr></x-ui.table>'),
        );
    }

    #[Test]
    public function stack_can_be_disabled(): void
    {
        $html = $this->render('<x-ui.table :stack="false"><tr><td>A</td></tr></x-ui.table>');

        $this->assertStringContainsString('ds-table ds-table--list"', $html);
        $this->assertStringNotContainsString('ds-table--stack', $html);
    }

    #[Test]
    public function kv_table_has_no_head_and_never_stacks(): void
    {
        $html = $this->render('<x-ui.table variant="kv"><x-slot:head><tr><th scope="col">X</th></tr></x-slot:head> <tr><th scope="row">K</th><td>V</td></tr></x-ui.table>');

        $this->assertStringContainsString('class="ds-table ds-table--kv"', $html);
        $this->assertStringNotContainsString('<thead', $html);
        $this->assertStringNotContainsString('ds-table--stack', $html);
    }

    #[Test]
    public function pagination_renders_links_and_current_page(): void
    {
        $p = new LengthAwarePaginator(range(1, 10), 50, 10, 2, ['path' => 'https://example.test/x']);
        $html = $this->render('<x-ui.pagination :paginator="$p" />', ['p' => $p]);

        $this->assertStringContainsString('<nav class="ds-pagination" aria-label="Seiten">', $html);
        $this->assertStringContainsString('href="https://example.test/x?page=1"', $html);
        $this->assertMatchesRegularExpression('/<a[^>]*href="https:\/\/example\.test\/x\?page=1"[^>]*aria-label="Zurück"|<a[^>]*aria-label="Zurück"[^>]*href="https:\/\/example\.test\/x\?page=1"/', $html);
        $this->assertStringContainsString('<span class="ds-pagination__page" aria-current="page">2</span>', $html);
        $this->assertStringContainsString('href="https://example.test/x?page=5"', $html);
    }

    #[Test]
    public function single_page_renders_nothing(): void
    {
        $p = new LengthAwarePaginator(range(1, 3), 3, 10, 1, ['path' => 'https://example.test/x']);

        $this->assertSame('', $this->render('<x-ui.pagination :paginator="$p" />', ['p' => $p]));
    }
}
