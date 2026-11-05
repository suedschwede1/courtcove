<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use DOMDocument;
use DOMNodeList;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\NormalizesHtml;
use Tests\TestCase;

final class AdminLayoutTest extends TestCase
{
    use LazilyRefreshDatabase;
    use NormalizesHtml;

    private DOMXPath $xpath;

    private function load(string $html): void
    {
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

    private function attr(string $query, string $name): ?string
    {
        $node = $this->nodes($query)->item(0);
        $this->assertNotNull($node, $query.' not found');

        return $node->attributes->getNamedItem($name)?->nodeValue;
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private static function cls(string $class): string
    {
        return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
    }

    #[Test]
    public function sidebar_is_rendered_without_cloak_and_bar_controls_it(): void
    {
        $this->load($this->actingAs($this->admin())->get('/admin')->assertOk()->getContent());

        $aside = '//aside[@id="admin-nav"]';
        $this->assertSame(1, $this->nodes($aside)->length);
        $this->assertNull($this->attr($aside, 'x-cloak'));
        $this->assertNull($this->attr($aside, 'x-show'));

        $bar = '//*['.self::cls('ds-admin-bar').']';
        $button = $bar.'//button[@aria-controls="admin-nav"]';
        $this->assertSame(1, $this->nodes($button)->length);
        $this->assertSame('Menü öffnen', $this->attr($button, 'aria-label'));
        $this->assertSame('navButton', $this->attr($button, 'x-ref'));
        $this->assertSame('false', $this->attr($button, 'aria-expanded'));
        $this->assertNotNull($this->attr($button, 'x-bind:aria-expanded'));

        $root = '//div['.self::cls('ds-admin').']';
        $this->assertSame(1, $this->nodes($root)->length);
        $this->assertSame('{ navOpen: false }', $this->attr($root, 'x-data'));
        $this->assertStringContainsString('navOpen = false', (string) $this->attr($root, 'x-on:keydown.escape.window'));

        $backdrop = '//div['.self::cls('ds-admin-backdrop').']';
        $this->assertSame(1, $this->nodes($backdrop)->length);
        $this->assertNotNull($this->attr($backdrop, 'x-cloak'));
        $this->assertSame('navOpen', $this->attr($backdrop, 'x-show'));

        $this->assertSame('navOpen', $this->attr($bar, 'x-bind:inert'));
        $this->assertSame('navOpen', $this->attr('//main[@id="content"]', 'x-bind:inert'));

        $this->assertSame('Zum Kalender', $this->attr($bar.'//a[@href="'.route('calendar.index').'"]', 'aria-label'));
    }

    #[Test]
    public function title_and_main_landmark(): void
    {
        $admin = $this->admin();
        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<title>'.preg_quote(__('booking.nav.admin'), '/').' - [^<]+<\/title>/', $html);
        $this->load($html);
        $this->assertSame(1, $this->nodes('//main')->length);
        $this->assertSame(1, $this->nodes('//main[@id="content"]')->length);

        $this->load($this->actingAs($admin)->withSession(['success' => 'Saved'])->get('/admin')->assertOk()->getContent());
        $good = $this->nodes('//main[@id="content"]//div['.self::cls('ds-notice--good').'][@role="status"]');
        $this->assertSame(1, $good->length);
        $this->assertSame('Saved', trim($good->item(0)->textContent));

        $this->load($this->actingAs($admin)->withSession(['error' => 'Nope'])->get('/admin')->assertOk()->getContent());
        $problem = $this->nodes('//main[@id="content"]//div['.self::cls('ds-notice--problem').'][@role="alert"]');
        $this->assertSame(1, $problem->length);
        $this->assertSame('Nope', trim($problem->item(0)->textContent));
    }

    #[Test]
    public function admin_back_section_replaces_the_menu_button(): void
    {
        Route::middleware('web')->get('/_test-admin-back', fn () => view()->file(__DIR__.'/fixtures/admin-back.blade.php'));

        $this->load($this->actingAs($this->admin())->get('/_test-admin-back')->assertOk()->getContent());

        $bar = '//*['.self::cls('ds-admin-bar').']';
        $this->assertSame(1, $this->nodes($bar.'//a[@href="https://example.test/list"][@aria-label="Zurück"]')->length);
        $this->assertSame(0, $this->nodes('//button[@aria-controls="admin-nav"]')->length);
    }

    #[Test]
    public function page_header_renders_all_parts(): void
    {
        $template = '<x-admin.page-header title="Mitglieder" eyebrow="Verwaltung" back-url="https://example.test/b" back-label="Buchungen">Intro<x-slot:actions><a href="#n">Neu</a></x-slot:actions></x-admin.page-header>';

        $this->assertSame(
            '<div class="ds-admin-head"><nav class="ds-admin-crumbs" aria-label="Pfad"><a href="https://example.test/b">Buchungen</a><span aria-hidden="true">/</span><span aria-current="page">Mitglieder</span></nav><div class="ds-admin-head__text"><p class="ds-admin-head__eyebrow">Verwaltung</p><h1 class="ds-admin-head__title">Mitglieder</h1><div class="ds-admin-head__meta">Intro</div></div><div class="ds-admin-head__actions"><a href="#n">Neu</a></div></div>',
            $this->html((string) $this->blade($template)),
        );

        $this->assertSame(
            '<div class="ds-admin-head"><div class="ds-admin-head__text"><h1 class="ds-admin-head__title">Mitglieder</h1></div></div>',
            $this->html((string) $this->blade('<x-admin.page-header title="Mitglieder" back-label="Buchungen" />')),
        );

        request()->merge(['popup' => 1]);
        $this->assertStringNotContainsString('<nav', (string) $this->blade($template));
    }

    #[Test]
    public function popup_mode_renders_no_shell_and_posts_close(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.events.create', ['popup' => 1]))->assertOk()->getContent();
        $this->load($html);

        $this->assertSame(0, $this->nodes('//*[@id="admin-nav"]')->length);
        $this->assertSame(0, $this->nodes('//*['.self::cls('ds-admin-bar').']')->length);
        $this->assertSame(0, $this->nodes('//*['.self::cls('ds-admin-crumbs').']')->length);

        $title = $this->nodes('//header['.self::cls('ds-popup__head').']/h1['.self::cls('ds-popup__title').']');
        $this->assertSame(1, $title->length);
        $this->assertSame(__('booking.admin.events.create_title'), trim($title->item(0)->textContent));

        $close = '//header['.self::cls('ds-popup__head').']//button[@aria-label="Schließen"]';
        $this->assertSame(1, $this->nodes($close)->length);
        $click = (string) $this->attr($close, 'x-on:click');
        $this->assertStringContainsString('booking-modal-close', $click);
        $this->assertStringContainsString('window.close()', $click);
        $this->assertStringNotContainsString('&times;', $html);
    }
}
