<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
    public function header_is_the_chrome_bar(): void
    {
        $html = $this->get('/calendar')->assertOk()
            ->assertSee('class="ds-header ds-on-chrome no-print"', false)
            ->assertSee(config('booking.name'))
            ->assertSee(__('booking.nav.eyebrow'))
            ->getContent();

        $active = $this->xpath($html)->query('//nav[@aria-label="Hauptmenü"]//a[@aria-current="page"]');
        $this->assertSame(1, $active->length);
        $this->assertSame('Kalender', trim($active->item(0)->textContent));
    }

    #[Test]
    public function guest_sees_login_not_tournaments(): void
    {
        $this->get('/calendar')->assertOk()
            ->assertSee('href="'.route('login', ['redirect_to' => url('/calendar')]).'"', false)
            ->assertSee('ds-btn')
            ->assertDontSee('Forderungspyramide');
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
        $this->assertStringNotContainsString('role="switch"', $html);

        $admin = User::factory()->create(['status' => 'admin']);
        $html = $this->actingAs($admin)->get('/calendar')->assertOk()
            ->assertSee(route('admin-mode.set', ['state' => 'on']), false)->getContent();
        $this->assertSame(1, $this->xpath($html)->query('//a[@role="switch"][@aria-checked="false"]')->length);

        $html = $this->actingAs($admin)->withUnencryptedCookie('admin_mode', '1')->get('/calendar')->assertOk()
            ->assertSee(route('admin-mode.set', ['state' => 'off']), false)->getContent();
        $this->assertSame(1, $this->xpath($html)->query('//a[@role="switch"][@aria-checked="true"]')->length);
    }

    #[Test]
    public function locale_switch_links_only_the_other_locale(): void
    {
        $html = $this->get('/calendar')->assertOk()
            ->assertSee('href="'.route('lang.switch', ['locale' => 'en']).'"', false)
            ->assertDontSee('href="'.route('lang.switch', ['locale' => 'de']).'"', false)
            ->getContent();

        $current = $this->xpath($html)->query('//span[@aria-current="true"]');
        $this->assertSame(1, $current->length);
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

        $this->assertSame(2, $xp->query('//div[@id="calendar-header-nav"]//a[contains(@class,"ui-calendar-nav-btn--arrow")]')->length);
        $this->assertSame(1, $xp->query('//*[@data-panel-toggle="system-info-panel"]')->length);
        $this->assertSame(1, $xp->query('//*[@data-panel-toggle="help-panel"]')->length);
    }
}
