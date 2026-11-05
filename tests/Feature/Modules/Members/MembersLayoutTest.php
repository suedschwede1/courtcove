<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\ClubRole;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every Members page renders inside the shared admin shell (layouts.admin): one
 * admin sidebar with the Mitglieder entries, and the module's sub-pages as tabs.
 */
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
        return $this->between($html, '<aside id="admin-nav"', '</aside>');
    }

    private function tabs(string $html): string
    {
        return $this->between($html, '<nav class="ds-tabs', '</nav>');
    }

    private function between(string $html, string $open, string $close): string
    {
        $start = strpos($html, $open);
        $this->assertNotFalse($start, $open.' missing');
        $end = strpos($html, $close, (int) $start);
        $this->assertNotFalse($end, $close.' missing');

        return substr($html, (int) $start, (int) $end - (int) $start);
    }

    /**
     * Every HTML page of the module => [route of the active admin sidebar entry, route of the active tab].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function pages(): array
    {
        $member = Member::factory()->create();
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $role = ClubRole::create(['name' => 'Kassier', 'is_board' => true]);

        return [
            route('members.dashboard') => ['members.dashboard', 'members.dashboard'],
            route('members.index') => ['members.dashboard', 'members.index'],
            route('members.create') => ['members.dashboard', 'members.index'],
            route('members.edit', $member) => ['members.dashboard', 'members.index'],
            route('members.payments.create', $member) => ['members.dashboard', 'members.index'],
            route('members.dues.index') => ['members.dashboard', 'members.dues.index'],
            route('members.mail.index') => ['members.dashboard', 'members.mail.index'],
            route('members.fiscal-years.index') => ['members.fiscal-years.index', 'members.fiscal-years.index'],
            route('members.fiscal-years.create') => ['members.fiscal-years.index', 'members.fiscal-years.index'],
            route('members.fiscal-years.edit', $fiscalYear) => ['members.fiscal-years.index', 'members.fiscal-years.index'],
            route('members.fee-categories.index') => ['members.fiscal-years.index', 'members.fee-categories.index'],
            route('members.functions.index') => ['members.fiscal-years.index', 'members.functions.index'],
            route('members.functions.create') => ['members.fiscal-years.index', 'members.functions.index'],
            route('members.functions.edit', $role) => ['members.fiscal-years.index', 'members.functions.index'],
            route('members.mail.config') => ['members.fiscal-years.index', 'members.mail.config'],
            route('members.dsgvo.index') => ['members.fiscal-years.index', 'members.dsgvo.index'],
        ];
    }

    #[Test]
    public function the_page_list_covers_every_html_get_route_of_the_module(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true) && str_starts_with((string) $route->getName(), 'members.'))
            ->map(fn ($route) => (string) $route->getName())
            ->reject(fn (string $name) => $name === 'members.export')
            ->values()->all();

        $this->assertCount(16, $names);
        $this->assertCount(16, $this->pages());
    }

    #[Test]
    public function every_page_renders_in_the_shared_admin_shell(): void
    {
        $admin = $this->admin();

        foreach (array_keys($this->pages()) as $url) {
            $html = (string) $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'class="ds-admin"'), $url);
            $this->assertSame(1, substr_count($html, 'id="admin-nav"'), $url);
            $this->assertSame(1, substr_count($html, '<aside'), $url);
            $this->assertSame(1, substr_count($html, '<h1'), $url);
            $this->assertStringContainsString('aria-controls="admin-nav"', $html, $url);
            $this->assertStringContainsString('<meta name="csrf-token"', $html, $url);
            $this->assertStringContainsString('<title>'.e(__('booking.nav.admin')).' - ', $html, $url);

            // Phones hide the h1 and show the admin bar title instead, so both carry the same text.
            $this->assertMatchesRegularExpression('#<h1[^>]*>([^<]+)</h1>#', $html, $url);
            preg_match('#<h1[^>]*>([^<]+)</h1>#', $html, $h1);
            preg_match('#<p class="ds-admin-bar__title">([^<]+)</p>#', $html, $bar);
            $this->assertSame(trim($h1[1]), trim($bar[1] ?? ''), $url);

            foreach (['members-sidebar', 'mbr-shell', 'mbr-sidebar', 'mbr-topbar', 'mbr-main', 'mbr-content'] as $old) {
                $this->assertStringNotContainsString($old, $html, $url);
            }
        }
    }

    #[Test]
    public function the_right_sidebar_entry_and_tab_are_active_on_every_page(): void
    {
        $admin = $this->admin();

        foreach ($this->pages() as $url => [$sidebarRoute, $tabRoute]) {
            $html = (string) $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $sidebar = $this->sidebar($html);
            $this->assertSame(1, substr_count($sidebar, 'aria-current="page"'), $url);
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route($sidebarRoute), '#').'"[^>]*aria-current="page"#', $sidebar, $url);

            $tabs = $this->tabs($html);
            $this->assertSame(1, substr_count($html, '<nav class="ds-tabs'), $url);
            $this->assertSame(1, substr_count($tabs, 'aria-current="page"'), $url);
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route($tabRoute), '#').'"[^>]*aria-current="page"#', $tabs, $url);
        }
    }

    #[Test]
    public function tabs_show_the_sub_pages_of_the_active_section_only(): void
    {
        $tabs = $this->tabs($this->render('/members/dues'));
        $this->assertStringContainsString('aria-label="'.e(__('booking.nav.members')).'"', $tabs);
        foreach (['members.dashboard', 'members.index', 'members.dues.index', 'members.mail.index'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $tabs);
        }
        $this->assertStringNotContainsString('href="'.route('members.dsgvo.index').'"', $tabs);

        $tabs = $this->tabs($this->render('/members/mail/config'));
        $this->assertStringContainsString('aria-label="'.e(__('members::admin.dashboard.configuration')).'"', $tabs);
        foreach (['members.fiscal-years.index', 'members.fee-categories.index', 'members.functions.index', 'members.mail.config', 'members.dsgvo.index'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $tabs);
        }
        $this->assertStringNotContainsString('href="'.route('members.mail.index').'"', $tabs);
    }

    #[Test]
    public function phone_menu_closes_on_escape_and_makes_the_page_inert(): void
    {
        $html = $this->render('/members/dashboard');

        $this->assertStringContainsString('x-on:keydown.escape.window="if (navOpen) { navOpen = false; $refs.navButton?.focus() }"', $html);
        $this->assertStringContainsString('<main id="content" class="ds-admin-main" x-bind:inert="navOpen">', $html);
        $this->assertMatchesRegularExpression('#class="ds-admin-backdrop"[^>]*x-on:click="navOpen = false"#', $html);
    }

    #[Test]
    public function layout_keeps_logout_admin_link_and_flashes(): void
    {
        $admin = $this->admin();

        $html = (string) $this->actingAs($admin)
            ->withSession(['success' => 'Gespeichert', 'status' => 'Beiträge erstellt'])
            ->get('/members/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#<form[^>]*action="'.preg_quote(route('logout'), '#').'"#', $html);
        $this->assertStringContainsString('href="'.route('admin.dashboard').'"', $html);
        $this->assertMatchesRegularExpression('#<div[^>]*role="status"[^>]*>\s*Gespeichert\s*</div>#', $html);
        $this->assertMatchesRegularExpression('#<div[^>]*role="status"[^>]*>\s*Beiträge erstellt\s*</div>#', $html);

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
    public function every_page_view_uses_the_module_layout_which_extends_the_admin_shell(): void
    {
        $layout = (string) file_get_contents(base_path(self::VIEWS.'layout.blade.php'));
        $this->assertStringStartsWith("@extends('layouts.admin')", $layout);
        $this->assertFileDoesNotExist(base_path(self::VIEWS.'components/sidebar.blade.php'));

        $pages = 0;
        foreach (glob(base_path(self::VIEWS.'{*,*/*}.blade.php'), GLOB_BRACE) ?: [] as $path) {
            $name = substr($path, strlen(base_path(self::VIEWS)));
            if ($name === 'layout.blade.php' || str_starts_with($name, 'components/') || str_starts_with($name, 'partials/')
                || str_starts_with(basename($name), '_')) {
                continue;
            }
            $source = (string) file_get_contents($path);
            $this->assertStringStartsWith("@extends('members::layout')", $source, $name);
            $this->assertStringContainsString("@section('members-content')", $source, $name);
            $pages++;
        }
        $this->assertSame(16, $pages);
    }

    #[Test]
    public function layout_sources_have_no_inline_styles_or_hex(): void
    {
        foreach (['layout.blade.php', 'components/page-header.blade.php'] as $file) {
            $path = base_path(self::VIEWS.$file);
            $this->assertFileExists($path);
            $source = (string) file_get_contents($path);

            $this->assertStringNotContainsString('style=', $source, $file);
            $this->assertStringNotContainsString('onmouse', $source, $file);
            $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/', $source, $file);
        }
    }
}
