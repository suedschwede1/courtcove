<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaseLayoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{0: string, 1: bool}> */
    public static function pages(): array
    {
        return [
            'calendar' => ['/calendar', false],
            'login' => ['/login', false],
            'register' => ['/register', false],
            'forgot' => ['password.forgot', false],
            'admin dashboard' => ['admin.dashboard', true],
            'admin popup' => ['admin.events.create', true],
            'members dashboard' => ['members.dashboard', true],
        ];
    }

    #[Test]
    #[DataProvider('pages')]
    public function every_page_shares_the_base_frame(string $target, bool $admin): void
    {
        if ($admin) {
            $this->actingAs(User::factory()->create(['status' => 'admin']));
        }

        $url = str_starts_with($target, '/') ? $target : route($target, $target === 'admin.events.create' ? ['popup' => 1] : []);
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertSame(0, substr_count($html, 'fonts.googleapis'));
        $this->assertSame(1, substr_count($html, 'css/booking.css?v='));
        $this->assertSame(1, substr_count($html, '<meta name="viewport"'));
        // Vite also emits a preload hint for the CSS; the stylesheet link itself must appear once.
        $this->assertSame(1, preg_match_all('#<link rel="stylesheet"[^>]*/build/assets/app-[^"]+\.css#', $html));
        $this->assertLessThan(strpos($html, '</html>'), strpos($html, '<!-- '));
        $this->assertDoesNotMatchRegularExpression('/<body[^>]*style=/', $html);
    }

    #[Test]
    public function every_layout_extends_base(): void
    {
        $files = [
            'resources/views/layouts/app.blade.php',
            'resources/views/layouts/admin.blade.php',
            'resources/views/layouts/popup.blade.php',
            'resources/views/auth/login.blade.php',
            'resources/views/auth/register.blade.php',
            'resources/views/auth/forgot-password.blade.php',
            'resources/views/auth/reset-password.blade.php',
            'resources/views/auth/login-verify.blade.php',
        ];

        foreach ($files as $file) {
            $content = file_get_contents(base_path($file));
            $this->assertStringContainsString("@extends('layouts.base')", $content, $file);
            $this->assertStringNotContainsStringIgnoringCase('<!doctype', $content, $file);
        }
    }

    #[Test]
    public function admin_pages_keep_the_admin_prefix_even_when_the_view_sets_its_own_title(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'admin']));

        $html = $this->get(route('admin.events.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<title>'.preg_quote(__('booking.nav.admin'), '/').' - '.preg_quote(__('booking.admin.events.create_title'), '/').'<\/title>/', $html);
    }

    #[Test]
    public function popup_mode_shows_the_views_own_title(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'admin']));

        $html = $this->get(route('admin.events.create', ['popup' => 1]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<title>'.preg_quote(__('booking.admin.events.create_title'), '/').'<\/title>/', $html);
    }
}
