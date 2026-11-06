<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\ClubRole;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every Members page renders in the admin shell with the Members sidebar (group "Mitglieder": Übersicht, Mitglieder,
 * Beiträge, Rundmail, Protokoll; group "Einstellungen": the settings pages), the phone bar with the slide-in menu,
 * and no tab rows under the page head.
 */
class MembersLayoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const VIEWS = 'app/Modules/Members/resources/views/';

    /** Routes of the group "Mitglieder" in order. */
    private const ENTRIES = ['members.dashboard', 'members.index', 'members.dues.index', 'members.mail.index', 'members.changes.index'];

    /** Routes of the group "Einstellungen" in order. */
    private const SETTINGS = ['members.fiscal-years.index', 'members.fee-categories.index', 'members.functions.index', 'members.mail.config', 'members.dsgvo.index'];

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function render(string $url): string
    {
        return (string) $this->actingAs($this->admin())->get($url)->assertOk()->getContent();
    }

    private function between(string $html, string $open, string $close): string
    {
        $start = strpos($html, $open);
        $this->assertNotFalse($start, $open.' missing');
        $end = strpos($html, $close, (int) $start);
        $this->assertNotFalse($end, $close.' missing');

        return substr($html, (int) $start, (int) $end - (int) $start);
    }

    private function sidebarNav(string $html): string
    {
        return $this->between($html, '<nav class="ds-admin-sidebar__nav"', '</nav>');
    }

    /**
     * Every HTML page of the module => route of its highlighted sidebar entry.
     *
     * @return array<string, string>
     */
    private function pages(): array
    {
        $member = Member::factory()->create();
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $role = ClubRole::create(['name' => 'Kassier', 'is_board' => true]);
        $feeCategory = FeeCategory::create(['name' => 'Vollmitglied']);

        return [
            route('members.dashboard') => 'members.dashboard',
            route('members.index') => 'members.index',
            route('members.create') => 'members.index',
            route('members.edit', $member) => 'members.index',
            route('members.payments.create', $member) => 'members.index',
            route('members.dues.index') => 'members.dues.index',
            route('members.mail.index') => 'members.mail.index',
            route('members.changes.index') => 'members.changes.index',
            route('members.fiscal-years.index') => 'members.fiscal-years.index',
            route('members.fiscal-years.create') => 'members.fiscal-years.index',
            route('members.fiscal-years.edit', $fiscalYear) => 'members.fiscal-years.index',
            route('members.fee-categories.index') => 'members.fee-categories.index',
            route('members.fee-categories.edit', $feeCategory) => 'members.fee-categories.index',
            route('members.functions.index') => 'members.functions.index',
            route('members.functions.create') => 'members.functions.index',
            route('members.functions.edit', $role) => 'members.functions.index',
            route('members.mail.config') => 'members.mail.config',
            route('members.dsgvo.index') => 'members.dsgvo.index',
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

        $this->assertCount(18, $names);
        $this->assertCount(18, $this->pages());
    }

    #[Test]
    public function every_page_renders_in_the_admin_shell_with_the_members_sidebar(): void
    {
        $admin = $this->admin();

        foreach (array_keys($this->pages()) as $url) {
            $html = (string) $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<div class="ds-admin"'), $url);
            $this->assertSame(1, substr_count($html, '<aside id="admin-nav"'), $url);
            $this->assertSame(1, substr_count($html, '<main id="content" class="ds-admin-main"'), $url);
            // No light app header inside the shell (as on the administration pages).
            $this->assertStringNotContainsString('<header class="ds-header', $html, $url);

            $aside = $this->between($html, '<aside id="admin-nav"', '</aside>');
            $this->assertStringContainsString('aria-label="'.e(__('members::admin.area.nav')).'"', $aside, $url);
            $this->assertStringContainsString('<span class="ds-admin-sidebar__eyebrow">'.e(__('booking.nav.members')).'</span>', $aside, $url);
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('calendar.index'), '#').'" class="ds-admin-sidebar__brand">#', $aside, $url);
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('calendar.index'), '#').'" class="ds-admin-sidebar__back">#', $aside, $url);
            $this->assertMatchesRegularExpression('#<form method="POST" action="'.preg_quote(route('logout'), '#').'">#', $aside, $url);

            $this->assertSame(1, substr_count($html, '<h1'), $url);
            $this->assertStringContainsString('<p class="mbr-page-header__eyebrow">'.e(__('members::admin.area.eyebrow')).'</p>', $html, $url);
            $this->assertStringContainsString('<meta name="csrf-token"', $html, $url);
            $this->assertMatchesRegularExpression('#<title>\s*[^<]+ - '.preg_quote(e(__('members::admin.area.eyebrow')), '#').'\s*</title>#', $html, $url);
            $this->assertStringContainsString('<p class="ds-admin-bar__eyebrow">'.e(__('members::admin.area.eyebrow')).'</p>', $html, $url);
        }
    }

    #[Test]
    public function the_sidebar_lists_the_members_groups_and_never_the_admin_groups(): void
    {
        $admin = $this->admin();

        foreach (array_keys($this->pages()) as $url) {
            $nav = $this->sidebarNav((string) $this->actingAs($admin)->get($url)->assertOk()->getContent());

            $this->assertSame(2, substr_count($nav, '<p class="ds-admin-sidebar__group"'), $url);
            $this->assertStringContainsString('id="admin-nav-members">'.e(__('members::admin.members.title')).'</p>', $nav, $url);
            $this->assertStringContainsString('id="admin-nav-settings">'.e(__('members::admin.settings.title')).'</p>', $nav, $url);

            foreach (['members' => self::ENTRIES, 'settings' => self::SETTINGS] as $group => $routes) {
                $list = $this->between($nav, '<ul class="ds-admin-sidebar__list" aria-labelledby="admin-nav-'.$group.'">', '</ul>');
                preg_match_all('#<a href="([^"]+)" class="ds-admin-sidebar__link"#', $list, $m);
                $this->assertSame(array_map(fn (string $r) => route($r), $routes), $m[1], $url.' '.$group);
            }

            foreach (['admin.dashboard', 'admin.bookings.index', 'admin.users.index', 'admin.squares.index'] as $adminRoute) {
                $this->assertStringNotContainsString('href="'.route($adminRoute).'"', $nav, $url);
            }
        }
    }

    #[Test]
    public function every_page_highlights_exactly_its_entry(): void
    {
        $admin = $this->admin();

        foreach ($this->pages() as $url => $entryRoute) {
            $nav = $this->sidebarNav((string) $this->actingAs($admin)->get($url)->assertOk()->getContent());

            $this->assertSame(1, substr_count($nav, 'aria-current='), $url);
            $this->assertMatchesRegularExpression('#<li><a href="'.preg_quote(route($entryRoute), '#').'" class="ds-admin-sidebar__link" aria-current="page">#', $nav, $url);
        }
    }

    #[Test]
    public function every_page_has_the_phone_bar_with_the_slide_in_menu(): void
    {
        $admin = $this->admin();

        foreach (array_keys($this->pages()) as $url) {
            $html = (string) $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('x-data="{ navOpen: false }"', $html, $url);
            $bar = $this->between($html, '<header class="ds-admin-bar ds-on-chrome"', '</header>');
            $this->assertStringContainsString('x-bind:inert="navOpen"', $bar, $url);
            // The x-on:click handler contains "=>", so the tag is cut at its aria-label instead of at ">".
            $button = $this->between($bar, '<button', 'aria-label="'.e(__('booking.admin.nav.open')).'"');
            $this->assertStringContainsString('x-ref="navButton"', $button, $url);
            $this->assertStringContainsString('aria-controls="admin-nav"', $button, $url);
            $this->assertStringContainsString('aria-expanded="false"', $button, $url);
            $this->assertStringContainsString('x-bind:aria-expanded="navOpen ? \'true\' : \'false\'"', $button, $url);
            $this->assertStringContainsString('<p class="ds-admin-bar__title">', $bar, $url);

            $this->assertStringContainsString('<div class="ds-admin-backdrop" x-cloak x-show="navOpen" x-on:click="navOpen = false"></div>', $html, $url);
            $this->assertStringContainsString('<main id="content" class="ds-admin-main" x-bind:inert="navOpen">', $html, $url);
            $this->assertStringContainsString("x-bind:class=\"{ 'is-open': navOpen }\"", $html, $url);
            $close = $this->between($html, 'ds-admin-sidebar__close"', '</button>');
            $this->assertStringContainsString('x-ref="navClose"', $close, $url);
            $this->assertStringContainsString('aria-label="'.e(__('booking.admin.nav.close')).'"', $close, $url);
        }
    }

    #[Test]
    public function the_phone_bar_shows_the_page_title(): void
    {
        $html = $this->render(route('members.dues.index'));

        $this->assertMatchesRegularExpression('#<p class="ds-admin-bar__title">\s*'.preg_quote(e(__('members::admin.dues.title')), '#').'\s*</p>#', $html);
    }

    #[Test]
    public function no_page_renders_the_old_tab_rows(): void
    {
        $admin = $this->admin();

        foreach (array_keys($this->pages()) as $url) {
            $html = (string) $this->actingAs($admin)->get($url)->assertOk()->getContent();

            foreach (['ds-tabs', 'mbr-tabs', 'mbr-subnav', 'role="tablist"'] as $old) {
                $this->assertStringNotContainsString($old, $html, $url);
            }
        }

        $header = (string) file_get_contents(base_path(self::VIEWS.'components/page-header.blade.php'));
        $this->assertStringNotContainsString('x-ui.tabs', $header);
        $this->assertStringNotContainsString('x-ui.chip', $header);
    }

    #[Test]
    public function pages_have_no_markup_that_forces_horizontal_scroll(): void
    {
        $admin = $this->admin();

        foreach (array_keys($this->pages()) as $url) {
            $html = (string) $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertDoesNotMatchRegularExpression('#class="[^"]*\b(?:min-)?w-(?:screen|\[\d{3,}px\])#', $html, $url);
            $this->assertDoesNotMatchRegularExpression('#style="[^"]*(?:min-)?width:\s*\d{3,}px#', $html, $url);
            $this->assertStringNotContainsString('overflow-x-scroll', $html, $url);
        }

        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $css = (string) file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));
        // The content column may shrink, and below 48rem the sidebar is a fixed overlay, not a column.
        $this->assertMatchesRegularExpression('/\.ds-admin-main\{[^}]*min-width:0/', $css);
        $this->assertMatchesRegularExpression('/\.ds-admin-sidebar\{(?=[^}]*position:fixed)(?=[^}]*max-width:85vw)[^}]*\}/', $css);
    }

    #[Test]
    public function pages_work_the_same_with_admin_mode_on_and_off(): void
    {
        $admin = $this->admin();
        $urls = array_keys($this->pages());

        foreach (['0', '1'] as $mode) {
            foreach ($urls as $url) {
                $html = (string) $this->actingAs($admin)->withCookie('admin_mode', $mode)->get($url)->assertOk()->getContent();
                $this->assertStringContainsString('<aside id="admin-nav"', $html, $url.' admin_mode='.$mode);
                $this->assertSame(1, substr_count($html, '<h1'), $url.' admin_mode='.$mode);
            }
        }
    }

    #[Test]
    public function a_members_only_assistant_gets_the_members_sidebar(): void
    {
        $assist = User::factory()->status('assist')->create();
        $assist->syncPrivileges(['admin.members']);

        $html = (string) $this->actingAs($assist->fresh())->get(route('members.dashboard'))->assertOk()->getContent();
        $nav = $this->sidebarNav($html);
        foreach (self::ENTRIES as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $nav);
        }

        $bookingOnly = User::factory()->status('assist')->create();
        $bookingOnly->syncPrivileges(['admin.see-menu', 'admin.booking']);
        $this->actingAs($bookingOnly->fresh())->get(route('members.dashboard'))->assertForbidden();
    }

    #[Test]
    public function the_administration_keeps_its_own_sidebar_without_members_entries(): void
    {
        $nav = $this->sidebarNav($this->render(route('admin.dashboard')));

        $this->assertStringContainsString('id="admin-nav-operations"', $nav);
        $this->assertStringContainsString('id="admin-nav-users"', $nav);
        $this->assertStringNotContainsString('/members', $nav);
    }

    #[Test]
    public function list_and_detail_pin_their_primary_action_on_phones(): void
    {
        $html = $this->render(route('members.index'));
        $bar = $this->between($html, '<div class="ds-phone-actionbar mbr-phone-action">', '</div>');
        $this->assertStringContainsString('href="'.route('members.create').'"', $bar);
        $this->assertStringContainsString(e(__('members::admin.members.create')), $bar);
        $this->assertStringContainsString('class="ds-phone-actionbar-spacer"', $html);

        $html = $this->render(route('members.edit', Member::factory()->create()));
        $bar = $this->between($html, '<div class="ds-phone-actionbar mbr-phone-action">', '</div>');
        $this->assertMatchesRegularExpression('#<button type="submit"[^>]*form="member-edit-form"#', $bar);

        $this->assertStringNotContainsString('ds-phone-actionbar', $this->render(route('members.mail.index')));
    }

    #[Test]
    public function layout_keeps_logout_and_flashes(): void
    {
        $admin = $this->admin();

        $html = (string) $this->actingAs($admin)
            ->withSession(['success' => 'Gespeichert', 'status' => 'Beiträge erstellt'])
            ->get('/members/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#<form[^>]*action="'.preg_quote(route('logout'), '#').'"#', $html);
        $this->assertStringContainsString('href="'.route('calendar.index').'"', $html);
        $this->assertMatchesRegularExpression('#<div[^>]*role="status"[^>]*>\s*Gespeichert\s*</div>#', $html);
        $this->assertMatchesRegularExpression('#<div[^>]*role="status"[^>]*>\s*Beiträge erstellt\s*</div>#', $html);

        // Sessions serialize as JSON, so the error bag has to come from a real failed validation.
        $html = (string) $this->actingAs($admin)
            ->from('/members/dashboard')
            ->followingRedirects()
            ->post(route('members.fee-categories.store'), [])
            ->assertOk()
            ->getContent();

        $this->assertSame(1, preg_match_all('#<div[^>]*role="alert"[^>]*>\s*[^<\s][^<]*</div>#', $html));
    }

    #[Test]
    public function every_page_view_uses_the_module_layout_which_extends_the_admin_layout(): void
    {
        $layout = (string) file_get_contents(base_path(self::VIEWS.'layout.blade.php'));
        $this->assertStringContainsString("@extends('layouts.admin')", $layout);
        $this->assertStringContainsString("@section('admin-sidebar')", $layout);
        $this->assertStringContainsString('MembersNavigation::groups(', $layout);

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
            $this->assertStringNotContainsString("@section('admin-", $source, $name);
            $pages++;
        }
        $this->assertSame(18, $pages);
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
