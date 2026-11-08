<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use App\Services\MainNavigation;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhoneMenuTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function xpath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    #[Test]
    public function menu_lists_every_item_with_icons(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $this->actingAs($member);

        $expected = count(app(MainNavigation::class)->items($member, Request::create('/calendar')));
        $xp = $this->xpath($this->get('/calendar')->assertOk()->getContent());

        $links = $xp->query('//div[@id="phone-menu"]//nav[@aria-label="Menü"]/a');
        $this->assertSame($expected, $links->length);
        $this->assertGreaterThan(0, $expected);

        foreach ($links as $link) {
            $this->assertSame(1, $xp->query('.//svg[@aria-hidden="true"]', $link)->length);
        }

        $current = $xp->query('//div[@id="phone-menu"]//nav/a[@aria-current="page"]');
        $this->assertSame(1, $current->length);
        $this->assertStringContainsString('Kalender', $current->item(0)->textContent);

        $this->assertSame(1, $xp->query('//div[@id="phone-menu"]//*[contains(concat(" ", @class, " "), " ds-avatar ")]')->length);
    }

    #[Test]
    public function menu_has_close_language_admin_mode_and_logout(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $xp = $this->xpath($this->actingAs($member)->get('/calendar')->assertOk()->getContent());

        $close = $xp->query('//div[@id="phone-menu"]//button[@aria-label="Menü schließen"][@x-ref="menuClose"]');
        $this->assertSame(1, $close->length);
        $click = $close->item(0)->getAttribute('x-on:click');
        $this->assertStringContainsString('menuOpen = false', $click);
        $this->assertStringContainsString('$refs.menuButton', $click);

        $this->assertSame(1, $xp->query('//div[@id="phone-menu"]//span[@aria-current="true"]')->length);
        $this->assertSame(0, $xp->query('//div[@id="phone-menu"]//button[@role="switch"][@form="admin-mode-form-menu"]')->length);

        $logout = $xp->query('//div[@id="phone-menu"]//form[@action="'.route('logout').'"]//button[@type="submit"]');
        $this->assertSame(1, $logout->length);
        $this->assertContains('ds-btn', explode(' ', $logout->item(0)->getAttribute('class')));
        $this->assertContains('ds-btn--block', explode(' ', $logout->item(0)->getAttribute('class')));

        $admin = User::factory()->create(['status' => 'admin']);
        $xp = $this->xpath($this->actingAs($admin)->get('/calendar')->assertOk()->getContent());
        $this->assertSame(1, $xp->query('//div[@id="phone-menu"]//button[@role="switch"][@form="admin-mode-form-menu"]')->length);
    }

    #[Test]
    public function members_is_its_own_row_right_before_admin(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $xp = $this->xpath($this->actingAs($admin)->get('/calendar')->assertOk()->getContent());

        $hrefs = array_map(fn ($a) => $a->getAttribute('href'), iterator_to_array($xp->query('//div[@id="phone-menu"]//nav[@aria-label="Menü"]/a')));
        $this->assertSame([route('members.dashboard'), route('admin.dashboard')], array_slice($hrefs, -2));
        $this->assertSame(1, $xp->query('//div[@id="phone-menu"]//nav/a[@href="'.route('members.dashboard').'"]//svg')->length);
    }

    #[Test]
    public function guest_menu_offers_login_without_avatar(): void
    {
        $xp = $this->xpath($this->get('/calendar')->assertOk()->getContent());

        $this->assertSame(0, $xp->query('//div[@id="phone-menu"]//form[@action="'.route('logout').'"]')->length);
        $this->assertSame(0, $xp->query('//div[@id="phone-menu"]//*[contains(@class,"ds-avatar")]')->length);
        $login = $xp->query('//div[@id="phone-menu"]//a[@href="'.route('login', ['redirect_to' => url('/calendar')]).'"]');
        $this->assertSame(1, $login->length);
        $this->assertContains('ds-btn', explode(' ', $login->item(0)->getAttribute('class')));
    }

    #[Test]
    public function menu_is_cloaked_and_cloak_rule_is_built(): void
    {
        $xp = $this->xpath($this->get('/calendar')->assertOk()->getContent());
        $menu = $xp->query('//div[@id="phone-menu"]');
        $this->assertSame(1, $menu->length);
        $this->assertTrue($menu->item(0)->hasAttribute('x-cloak'));
        $this->assertSame('menuOpen', $menu->item(0)->getAttribute('x-show'));
        $this->assertSame('dialog', $menu->item(0)->getAttribute('role'));
        $this->assertSame('true', $menu->item(0)->getAttribute('aria-modal'));

        $css = '';
        foreach (glob(public_path('build/assets/*.css')) ?: [] as $file) {
            $css .= file_get_contents($file);
        }
        $this->assertMatchesRegularExpression('/\[x-cloak\]\s*\{\s*display:\s*none\s*!important/', $css);
        $this->assertStringContainsString('.ds-scroll-lock', $css);
    }
}
