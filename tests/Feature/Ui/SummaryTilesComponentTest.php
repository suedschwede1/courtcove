<?php

namespace Tests\Feature\Ui;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SummaryTilesComponentTest extends TestCase
{
    #[Test]
    public function tiles_render_static_and_bound_values(): void
    {
        $html = (string) $this->blade('<x-ui.summary-tiles :items="[[\'label\' => \'Datum\', \'value\' => \'Do, 5. Nov.\'], [\'label\' => \'Platz\', \'bind\' => \'courtLabel\']]" />');
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xp = new DOMXPath($doc);

        $this->assertSame(1, $xp->query('//dl[@class="ds-summary"]')->length);
        $items = $xp->query('//dl/div[@class="ds-summary__item"]');
        $this->assertSame(2, $items->length);

        $values = $xp->query('//dd[@class="ds-summary__value"]');
        $this->assertSame('Do, 5. Nov.', trim($values->item(0)->textContent));
        $this->assertFalse($values->item(0)->hasAttribute('x-text'));
        $this->assertSame('courtLabel', $values->item(1)->getAttribute('x-text'));
        $this->assertSame('', trim($values->item(1)->textContent));
        $this->assertSame('Platz', trim($xp->query('//dt[@class="ds-summary__label"]')->item(1)->textContent));
    }
}
