<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\Support\NormalizesHtml;
use Tests\TestCase;

class PageHeaderTest extends TestCase
{
    use NormalizesHtml;

    #[Test]
    public function header_renders_eyebrow_title_and_intro(): void
    {
        $this->assertSame(
            '<header class="flex flex-col gap-1"><p class="text-label uppercase text-ink-3">E</p><h1 class="font-display text-display-xl text-ink">T</h1><p class="text-body text-ink-2">I</p></header>',
            $this->html((string) $this->blade('<x-ui.page-header title="T" eyebrow="E" intro="I" />')),
        );
    }

    #[Test]
    public function header_without_optional_parts_renders_only_the_title(): void
    {
        $this->assertSame(
            '<header class="flex flex-col gap-1"><h1 class="font-display text-display-xl text-ink">T</h1></header>',
            $this->html((string) $this->blade('<x-ui.page-header title="T" />')),
        );
    }
}
