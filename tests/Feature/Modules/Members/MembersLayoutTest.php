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
 * The Members area is standalone: every page renders in the normal app layout (light header with
 * "Mitglieder" active), without the admin sidebar, with one module tab row under the page head.
 */
class MembersLayoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const VIEWS = 'app/Modules/Members/resources/views/';

    /** Tab routes in order. */
    private const TABS = ['members.dashboard', 'members.index', 'members.dues.index', 'members.mail.index', 'members.fiscal-years.index'];

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function render(string $url): string
    {
        return (string) $this->actingAs($this->admin())->get($url)->assertOk()->getContent();
    }

    private function tabs(string $html): string
    {
        return $this->between($html, '<nav class="ds-tabs mbr-tabs', '</nav>');
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
     * Every HTML page of the module => [route of the active tab, route of the active settings entry or null].
     *
     * @return array<string, array{0: string, 1: string|null}>
     */
    private function pages(): array
    {
        $member = Member::factory()->create();
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $role = ClubRole::create(['name' => 'Kassier', 'is_board' => true]);

        return [
            route('members.dashboard') => ['members.dashboard', null],
            route('members.index') => ['members.index', null],
            route('members.create') => ['members.index', null],
            route('members.edit', $member) => ['members.index', null],
            route('members.payments.create', $member) => ['members.index', null],
            route('members.dues.index') => ['members.dues.index', null],
            route('members.mail.index') => ['members.mail.index', null],
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
    public function every_page_renders_in_the_app_layout_without_the_admin_sidebar(): void
    {
        $admin = $this->admin();

        foreach (array_keys($this->pages()) as $url) {
            $html = (string) $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<header class="ds-header'), $url);
            foreach (['class="ds-admin"', 'id="admin-nav"', 'ds-admin-sidebar', 'ds-admin-bar', '<aside'] as $adminShell) {
                $this->assertStringNotContainsString($adminShell, $html, $url);
            }
            $this->assertSame(1, substr_count($html, '<h1'), $url);
            $this->assertStringContainsString('<p class="mbr-page-header__eyebrow">Mitgliederverwaltung</p>', $html, $url);
            $this->assertStringContainsString('<meta name="csrf-token"', $html, $url);
            $this->assertMatchesRegularExpression('#<title>[^<]+ - '.preg_quote(e(__('members::admin.area.eyebrow')), '#').'\s*</title>#', $html, $url);

            // The header marks "Mitglieder" as the current area, never the administration.
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('members.dashboard'), '#').'"[^>]*aria-current="page"#', $html, $url);
            $this->assertDoesNotMatchRegularExpression('#<a href="'.preg_quote(route('admin.dashboard'), '#').'"[^>]*aria-current="page"#', $html, $url);
        }
    }

    #[Test]
    public function every_page_has_the_tab_row_with_the_right_tab_active(): void
    {
        $admin = $this->admin();

        foreach ($this->pages() as $url => [$tabRoute, $settingsRoute]) {
            $html = (string) $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $tabs = $this->tabs($html);
            $this->assertSame(1, substr_count($html, '<nav class="ds-tabs'), $url);
            $this->assertStringContainsString('aria-label="'.e(__('members::admin.area.tabs')).'"', $tabs, $url);
            preg_match_all('#<a href="([^"]+)"#', $tabs, $links);
            $this->assertSame(array_map(fn (string $r) => route($r), self::TABS), $links[1], $url);
            $this->assertSame(1, substr_count($tabs, 'aria-current="page"'), $url);
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route($tabRoute), '#').'"[^>]*aria-current="page"#', $tabs, $url);

            if ($settingsRoute === null) {
                $this->assertStringNotContainsString('class="mbr-subnav"', $html, $url);

                continue;
            }
            $subnav = $this->between($html, '<nav class="mbr-subnav"', '</nav>');
            $this->assertSame(5, substr_count($subnav, '<a href='), $url);
            $this->assertSame(1, substr_count($subnav, 'aria-current="true"'), $url);
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route($settingsRoute), '#').'"[^>]*aria-current="true"#', $subnav, $url);
        }
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
    public function every_page_view_uses_the_module_layout_which_extends_the_app_layout(): void
    {
        $layout = (string) file_get_contents(base_path(self::VIEWS.'layout.blade.php'));
        $this->assertStringContainsString("@extends('layouts.app')", $layout);
        $this->assertStringNotContainsString('layouts.admin', $layout);

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
