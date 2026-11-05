<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembersLayoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const VIEWS = 'app/Modules/Members/resources/views/';

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function render(string $url): string
    {
        return (string) $this->actingAs($this->admin())->get($url)->assertOk()->getContent();
    }

    private function sidebar(string $html): string
    {
        $start = strpos($html, '<aside');
        $end = strpos($html, '</aside>');
        $this->assertNotFalse($start, 'sidebar <aside> missing');
        $this->assertNotFalse($end, 'sidebar </aside> missing');

        return substr($html, (int) $start, (int) $end - (int) $start);
    }

    /** The opening <aside> tag; its Alpine handlers contain "=>", so a [^>]* match would stop early. */
    private function asideTag(string $html): string
    {
        $start = strpos($html, '<aside');
        $end = strpos($html, 'class="mbr-sidebar__brand"');
        $this->assertNotFalse($start, 'sidebar <aside> missing');
        $this->assertNotFalse($end, 'sidebar brand row missing');

        return substr($html, (int) $start, (int) $end - (int) $start);
    }

    #[Test]
    public function sidebar_is_rendered_once_and_never_cloaked(): void
    {
        $html = $this->render('/members/dashboard');

        $this->assertSame(1, substr_count($html, 'id="members-sidebar"'));
        $this->assertSame(1, substr_count($html, '<nav aria-label="Mitglieder"'));
        $this->assertSame(1, substr_count($html, '<aside'));

        $tag = $this->asideTag($html);
        $this->assertStringNotContainsString('x-show', $tag);
        $this->assertStringNotContainsString('x-cloak', $tag);

        $this->assertStringContainsString("x-bind:class=\"{ 'mbr-shell--nav-open': navOpen }\"", $html);
    }

    #[Test]
    public function active_item_is_marked_with_aria_current(): void
    {
        $sidebar = $this->sidebar($this->render('/members'));

        $this->assertSame(1, substr_count($sidebar, 'aria-current="page"'));
        $this->assertMatchesRegularExpression(
            '#<a href="'.preg_quote(route('members.index'), '#').'"[^>]*aria-current="page"#',
            $sidebar,
        );

        $sidebar = $this->sidebar($this->render('/members/mail/config'));

        $this->assertSame(1, substr_count($sidebar, 'aria-current="page"'));
        $this->assertMatchesRegularExpression(
            '#<a href="'.preg_quote(route('members.mail.config'), '#').'"[^>]*aria-current="page"#',
            $sidebar,
        );
        $this->assertDoesNotMatchRegularExpression(
            '#<a href="'.preg_quote(route('members.mail.index'), '#').'"[^>]*aria-current="page"#',
            $sidebar,
        );
    }

    #[Test]
    public function menu_button_controls_the_sidebar(): void
    {
        $html = $this->render('/members/dashboard');

        $this->assertStringContainsString('aria-controls="members-sidebar"', $html);
        $this->assertStringContainsString('x-bind:aria-expanded', $html);
        $this->assertStringContainsString('aria-label="'.e(__('booking.nav.open_menu')).'"', $html);
    }

    #[Test]
    public function phone_menu_traps_focus_and_restores_it_on_close(): void
    {
        $html = $this->render('/members/dashboard');

        $tag = $this->asideTag($html);
        $this->assertStringContainsString('@keydown.tab="if (!navOpen) return;', $tag);
        $this->assertStringContainsString('$event.preventDefault(); f[f.length - 1].focus()', $tag);
        $this->assertStringContainsString('$event.preventDefault(); f[0].focus()', $tag);

        $this->assertStringContainsString('@keydown.escape.window="if (navOpen) { navOpen = false; $refs.menuButton.focus() }"', $html);
        $this->assertMatchesRegularExpression('/class="mbr-shell__backdrop"[^>]*@click="navOpen = false; \$refs\.menuButton\.focus\(\)"/', $html);
    }

    #[Test]
    public function layout_keeps_logout_admin_link_and_flashes(): void
    {
        $admin = $this->admin();

        $html = (string) $this->actingAs($admin)
            ->withSession(['success' => 'Gespeichert'])
            ->get('/members/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#<form[^>]*action="'.preg_quote(route('logout'), '#').'"#', $html);
        $this->assertStringContainsString('href="'.route('admin.dashboard').'"', $html);
        $this->assertMatchesRegularExpression('#<div[^>]*role="status"[^>]*>\s*Gespeichert\s*</div>#', $html);

        // Sessions serialize as JSON, so the error bag has to come from a real failed validation.
        $html = (string) $this->actingAs($admin)
            ->from('/members/dashboard')
            ->followingRedirects()
            ->post(route('members.fee-categories.store'), [])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#<div[^>]*role="alert"[^>]*>\s*[^<\s][^<]*</div>#', $html);
    }

    #[Test]
    public function layout_sources_have_no_inline_styles_or_hex(): void
    {
        foreach (['layout.blade.php', 'components/sidebar.blade.php', 'components/page-header.blade.php'] as $file) {
            $path = base_path(self::VIEWS.$file);
            $this->assertFileExists($path);
            $source = (string) file_get_contents($path);

            $this->assertStringNotContainsString('style=', $source, $file);
            $this->assertStringNotContainsString('onmouse', $source, $file);
            $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/', $source, $file);
        }
    }
}
