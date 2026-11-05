<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use DOMDocument;
use DOMNodeList;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminSidebarTest extends TestCase
{
    use LazilyRefreshDatabase;

    private DOMXPath $xpath;

    private function render(string $route = 'admin.dashboard'): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $html = $this->actingAs($admin)->get(route($route))->assertOk()->getContent();

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $this->xpath = new DOMXPath($dom);
    }

    private function nodes(string $query): DOMNodeList
    {
        $nodes = $this->xpath->query($query);
        $this->assertNotFalse($nodes);

        return $nodes;
    }

    #[Test]
    public function sidebar_has_brand_back_link_and_named_nav(): void
    {
        $this->render();

        $this->assertSame(1, $this->nodes('//aside[@id="admin-nav"]')->length);

        $backQuery = '//aside[@id="admin-nav"]//a[@href="'.route('calendar.index').'"][contains(normalize-space(), "Zum Kalender")]';
        $back = $this->nodes($backQuery);
        $this->assertGreaterThanOrEqual(1, $back->length);
        $this->assertStringContainsString('Zum Kalender', $back->item(0)->textContent);
        $this->assertSame(1, $this->nodes($backQuery.'//*[local-name()="svg"][@aria-hidden="true"]')->length);

        $navs = $this->nodes('//aside[@id="admin-nav"]//nav');
        $this->assertSame(1, $navs->length);
        $this->assertSame('Verwaltung', $navs->item(0)->attributes->getNamedItem('aria-label')->nodeValue);
    }

    #[Test]
    public function groups_render_as_labelled_lists(): void
    {
        $this->render();

        $texts = [];
        foreach ($this->nodes('//aside//ul[@aria-labelledby="admin-nav-operations"]/li/a') as $a) {
            $texts[] = trim($a->textContent);
        }
        $this->assertSame(['Übersicht', 'Buchungen', 'Veranstaltungen', 'Statistik'], $texts);

        $this->assertSame('Einstellungen', trim($this->nodes('//p[@id="admin-nav-settings"]')->item(0)->textContent));
        $this->assertSame(1, $this->nodes('//aside//a[@href="'.route('admin.testmail.index').'"][normalize-space()="Test-E-Mail"]')->length);
    }

    #[Test]
    public function active_item_has_aria_current(): void
    {
        $this->render('admin.users.index');

        $active = $this->nodes('//aside//a[@aria-current="page"]');
        $this->assertSame(1, $active->length);
        $this->assertSame(__('booking.admin.users.members'), trim($active->item(0)->textContent));
    }

    #[Test]
    public function no_inline_styles_or_hover_handlers(): void
    {
        $this->render();

        $aside = $this->nodes('//aside[@id="admin-nav"]')->item(0);
        $outer = $aside->ownerDocument->saveHTML($aside);

        $this->assertStringNotContainsString('style=', $outer);
        $this->assertStringNotContainsString('onmouse', $outer);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', $outer);
    }

    #[Test]
    public function logout_stays_reachable(): void
    {
        $this->render();

        $this->assertSame(1, $this->nodes('//aside//form[@action="'.route('logout').'"]//button[@type="submit"]')->length);
    }
}
