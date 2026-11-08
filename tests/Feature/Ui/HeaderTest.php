<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HeaderTest extends TestCase
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
    public function header_is_the_light_sand_bar(): void
    {
        $html = $this->get('/calendar')->assertOk()
            ->assertSee('class="ds-header no-print"', false)
            ->assertSee(config('booking.name'))
            ->assertDontSee(__('booking.nav.eyebrow'))
            ->getContent();

        $xp = $this->xpath($html);
        $header = $xp->query('//body/header[contains(concat(" ", @class, " "), " ds-header ")]');
        $this->assertSame(1, $header->length);
        $this->assertStringNotContainsString('chrome', $header->item(0)->getAttribute('class'));
        $this->assertStringNotContainsString('dark', $header->item(0)->getAttribute('class'));
        $this->assertSame(0, $xp->query('//*[contains(@class,"ds-header__eyebrow")]')->length);

        $active = $xp->query('//nav[@aria-label="Hauptmenü"]//a[@aria-current="page"]');
        $this->assertSame(1, $active->length);
        $this->assertSame('Kalender', trim($active->item(0)->textContent));
    }

    #[Test]
    public function phone_menu_keeps_the_chrome_surface_inside_the_light_header(): void
    {
        $xp = $this->xpath($this->get('/calendar')->assertOk()->getContent());
        $menu = $xp->query('//header//div[@id="phone-menu"]');
        $this->assertSame(1, $menu->length);
        $this->assertContains('ds-on-chrome', explode(' ', $menu->item(0)->getAttribute('class')));

        $button = $xp->query('//header//button[contains(@class,"ds-header__menu-button")]');
        $this->assertSame(1, $button->length);
        $this->assertContains('ds-icon-btn--secondary', explode(' ', $button->item(0)->getAttribute('class')));
        $this->assertNotSame('', $button->item(0)->getAttribute('aria-label'));
    }

    #[Test]
    public function nav_items_follow_the_light_item_spec(): void
    {
        $css = (string) file_get_contents(resource_path('css/ds.css'));

        $link = $this->ruleBody($css, '.ds-header__link,'."\n".'    .ds-header__menu-link');
        $this->assertMatchesRegularExpression('/display:\s*inline-flex;/', $link);
        $this->assertMatchesRegularExpression('/align-items:\s*center;/', $link);
        $this->assertMatchesRegularExpression('/min-height:\s*var\(--spacing-touch-min\);/', $link);
        $this->assertMatchesRegularExpression('/padding:\s*0 14px;/', $link);
        $this->assertMatchesRegularExpression('/color:\s*var\(--color-ink\);/', $link);
        $this->assertMatchesRegularExpression('/font:\s*500 /', $link);

        $active = $this->ruleBody($css, '.ds-header__link[aria-current]');
        $this->assertMatchesRegularExpression('/font-weight:\s*700;/', $active);
        $this->assertMatchesRegularExpression('/box-shadow:\s*inset 0 -3px 0 var\(--color-clay\);/', $active);
        $this->assertStringNotContainsString('background', $active);

        $hover = $this->ruleBody($css, '.ds-header__link:hover,'."\n".'    .ds-header__link[aria-expanded="true"]');
        $this->assertMatchesRegularExpression('/box-shadow:\s*inset 0 -3px 0 var\(--color-line-strong\);/', $hover);

        $bar = $this->ruleBody($css, '.ds-header');
        $this->assertMatchesRegularExpression('/background:\s*var\(--color-ground\);/', $bar);
        $this->assertMatchesRegularExpression('/border-bottom:\s*1px solid var\(--color-line\);/', $bar);
        $this->assertMatchesRegularExpression('/color:\s*var\(--color-ink\);/', $bar);

        $panel = $this->ruleBody($css, '.ds-header__menu');
        $this->assertMatchesRegularExpression('/background:\s*var\(--color-surface\);/', $panel);
        $this->assertMatchesRegularExpression('/border:\s*1px solid var\(--color-line\);/', $panel);
        $this->assertMatchesRegularExpression('/border-radius:\s*var\(--radius-lg\);/', $panel);
    }

    #[Test]
    public function header_css_never_overrides_the_global_focus_ring(): void
    {
        $css = (string) file_get_contents(resource_path('css/ds.css'));
        preg_match_all('/\.ds-header[^{]*\{[^}]*\}/', $css, $rules);

        foreach ($rules[0] as $rule) {
            $this->assertStringNotContainsString('outline', $rule);
        }
    }

    private function ruleBody(string $css, string $selector): string
    {
        $pattern = '#(?:[{}]|\*/)\s*'.preg_quote($selector, '#').'\s*\{([^}]*)\}#';
        $this->assertMatchesRegularExpression($pattern, $css, $selector);
        preg_match($pattern, $css, $m);

        return $m[1];
    }

    #[Test]
    public function guest_sees_login_not_tournaments(): void
    {
        $html = $this->get('/calendar')->assertOk()->assertDontSee('Forderungspyramide')->getContent();

        $login = $this->xpath($html)->query('//*[contains(@class,"ds-header__tools")]//a[@href="'.route('login', ['redirect_to' => url('/calendar')]).'"]');
        $this->assertSame(1, $login->length);
        $this->assertContains('ds-btn', explode(' ', $login->item(0)->getAttribute('class')));
    }

    #[Test]
    public function member_bar_and_tools(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $xp = $this->xpath($this->actingAs($member)->get('/my-bookings')->assertOk()->getContent());

        $current = $xp->query('//nav[@aria-label="Hauptmenü"]//a[@aria-current="page"]');
        $this->assertSame('Meine Buchungen', trim($current->item(0)->textContent));

        $avatar = $xp->query('//a[contains(concat(" ", @class, " "), " ds-avatar ")][@href="'.route('account.edit').'"]');
        $this->assertSame(1, $avatar->length);

        $this->assertSame(1, $xp->query('//*[contains(@class,"ds-header__tools")]//form[@action="'.route('logout').'"]//button[@type="submit"]')->length);
    }

    #[Test]
    public function members_never_see_admin_mode(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $html = $this->actingAs($member)->get('/calendar')->assertOk()->assertDontSee('admin-mode', false)->getContent();
        $this->assertStringNotContainsString('admin-mode-form-', $html);

        $admin = User::factory()->create(['status' => 'admin']);
        $html = $this->actingAsWithoutAdminMode($admin)->get('/calendar')->assertOk()
            ->assertSee(route('admin-mode.set', ['state' => 'on']), false)->getContent();
        $this->assertSame(2, $this->xpath($html)->query('//button[@role="switch"][starts-with(@form, "admin-mode-form-")][@aria-checked="false"]')->length); // bar + phone menu

        $html = $this->actingAsAdminMode($admin)->get('/calendar')->assertOk()
            ->assertSee(route('admin-mode.set', ['state' => 'off']), false)->getContent();
        $this->assertSame(2, $this->xpath($html)->query('//button[@role="switch"][starts-with(@form, "admin-mode-form-")][@aria-checked="true"]')->length);
    }

    #[Test]
    public function locale_switch_links_only_the_other_locale(): void
    {
        $html = $this->get('/calendar')->assertOk()
            ->assertSee('href="'.route('lang.switch', ['locale' => 'en']).'"', false)
            ->assertDontSee('href="'.route('lang.switch', ['locale' => 'de']).'"', false)
            ->getContent();

        $current = $this->xpath($html)->query('//span[@aria-current="true"]');
        $this->assertSame(2, $current->length); // bar + phone menu
        $this->assertSame('DE', trim($current->item(0)->textContent));
    }

    #[Test]
    public function rendered_page_has_unique_ids_and_named_navs(): void
    {
        $users = [
            'guest' => null,
            'member' => User::factory()->create(['status' => 'enabled']),
            'admin' => User::factory()->create(['status' => 'admin']),
        ];

        foreach ($users as $role => $user) {
            $user ? $this->actingAs($user) : $this->app['auth']->logout();
            $xp = $this->xpath($this->get('/calendar')->assertOk()->getContent());

            $ids = [];
            foreach ($xp->query('//*[@id]') as $node) {
                $ids[] = $node->getAttribute('id');
            }
            $this->assertSame([], array_keys(array_filter(array_count_values($ids), fn ($n) => $n > 1)), "duplicate ids for {$role}");

            $labels = [];
            foreach ($xp->query('//nav') as $nav) {
                $label = trim($nav->getAttribute('aria-label'));
                $this->assertNotSame('', $label, "unnamed nav for {$role}");
                $labels[] = $label;
            }
            $this->assertSame($labels, array_values(array_unique($labels)), "duplicate nav labels for {$role}");
        }
    }

    #[Test]
    public function legacy_date_nav_stays_reachable(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $html = $this->actingAs($member)->get('/calendar')->assertOk()->getContent();
        $xp = $this->xpath($html);

        $this->assertSame(2, $xp->query('//nav[@id="calendar-date-nav"]//a[@data-cal-nav]')->length);
        $this->assertSame(1, $xp->query('//*[@data-panel-toggle="system-info-panel"]')->length);
        $this->assertSame(1, $xp->query('//*[@data-panel-toggle="help-panel"]')->length);
    }

    #[Test]
    public function brand_link_is_a_44px_tap_target_on_phones(): void
    {
        $css = (string) file_get_contents(resource_path('css/ds.css'));

        $this->assertMatchesRegularExpression('/\.ds-header__brand\s*\{\s*min-height:\s*44px;/', $css);
    }

    #[Test]
    public function header_spans_the_viewport_outside_the_page_shell(): void
    {
        $xp = $this->xpath($this->get('/calendar')->assertOk()->getContent());

        $this->assertSame(1, $xp->query('//body/header[contains(concat(" ", @class, " "), " ds-header ")]')->length);
        $this->assertSame(0, $xp->query('//*[contains(concat(" ", @class, " "), " page-shell ")]//header')->length);
    }

    #[Test]
    public function header_bar_is_one_full_width_72px_row(): void
    {
        $css = (string) file_get_contents(resource_path('css/ds.css'));
        $this->assertMatchesRegularExpression('/\.ds-header__bar\s*\{([^}]*)\}/', $css);
        preg_match('/\.ds-header__bar\s*\{([^}]*)\}/', $css, $m);

        $this->assertStringNotContainsString('max-width', $m[1]);
        $this->assertMatchesRegularExpression('/min-height:\s*72px;/', $m[1]);
        $this->assertMatchesRegularExpression('/flex-wrap:\s*nowrap;/', $m[1]);
        $this->assertMatchesRegularExpression('/padding:\s*14px var\(--spacing-page-gutter\);/', $m[1]);

        preg_match('/\.ds-header__nav\s*\{([^}]*)\}/', $css, $nav);
        $this->assertMatchesRegularExpression('/flex-wrap:\s*nowrap;/', $nav[1]);
    }

    #[Test]
    public function tournaments_collapse_into_one_dropdown(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $xp = $this->xpath($this->actingAs($member)->get('/calendar')->assertOk()->getContent());
        $nav = '//nav[@aria-label="Hauptmenü"]';

        $this->assertSame(3, $xp->query($nav.'/ul/li')->length); // Kalender, Meine Buchungen, Turniere

        $button = $xp->query($nav.'//button[@aria-controls="nav-group-tournaments"]');
        $this->assertSame(1, $button->length);
        $btn = $button->item(0);
        $this->assertSame('button', $btn->getAttribute('type'));
        $this->assertSame('false', $btn->getAttribute('aria-expanded'));
        $this->assertStringContainsString('open', $btn->getAttribute('x-bind:aria-expanded'));
        $this->assertStringContainsString('Turniere', $btn->textContent);
        $this->assertFalse($btn->hasAttribute('aria-current'));

        $panel = $xp->query($nav.'//*[@id="nav-group-tournaments"]');
        $this->assertSame(1, $panel->length);
        $this->assertTrue($panel->item(0)->hasAttribute('x-cloak'));
        $this->assertSame('open', $panel->item(0)->getAttribute('x-show'));
        $labels = array_map(fn ($a) => trim($a->textContent), iterator_to_array($xp->query('.//a', $panel->item(0))));
        $this->assertSame(['Forderungspyramide', 'Mixed-/Fun-Turnier'], $labels);

        $group = $xp->query($nav.'//*[@x-data="dsNavGroup"]');
        $this->assertSame(1, $group->length);
        $this->assertNotSame('', $group->item(0)->getAttribute('x-on:keydown.escape'));
        $this->assertNotSame('', $group->item(0)->getAttribute('x-on:click.outside'));
    }

    #[Test]
    public function members_and_admin_are_own_entries_and_members_is_current_on_member_pages(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $xp = $this->xpath($this->actingAs($admin)->get('/calendar')->assertOk()->getContent());
        $nav = '//nav[@aria-label="Hauptmenü"]';

        $this->assertSame(5, $xp->query($nav.'/ul/li')->length);
        $this->assertSame(0, $xp->query($nav.'//*[@id="nav-group-administration"]')->length);
        $direct = array_map(fn ($a) => $a->getAttribute('href'), iterator_to_array($xp->query($nav.'/ul/li/a')));
        $this->assertSame([route('members.dashboard'), route('admin.dashboard')], array_slice($direct, -2));

        $this->actingAs($admin);
        $xp = $this->xpath($this->renderHeader(route('members.dashboard')));
        $this->assertSame(0, $xp->query($nav.'//button[@aria-current="true"]')->length);
        $current = $xp->query($nav.'//a[@aria-current="page"]');
        $this->assertSame(1, $current->length);
        $this->assertSame(route('members.dashboard'), $current->item(0)->getAttribute('href'));
    }

    #[Test]
    public function tournaments_group_is_current_on_a_tournament_page(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']));
        $xp = $this->xpath($this->renderHeader(url('/pyramide/regeln')));
        $nav = '//nav[@aria-label="Hauptmenü"]';

        $this->assertSame('true', $xp->query($nav.'//button[@aria-controls="nav-group-tournaments"]')->item(0)->getAttribute('aria-current'));
        $current = $xp->query($nav.'//a[@aria-current="page"]');
        $this->assertSame(1, $current->length);
        $this->assertSame('Forderungspyramide', trim($current->item(0)->textContent));
    }

    private function renderHeader(string $url): string
    {
        $request = Request::create($url);
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));
        $this->app->instance('request', $request);

        return Blade::render('<x-layout.header />');
    }
}
