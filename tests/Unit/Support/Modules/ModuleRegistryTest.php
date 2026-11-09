<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Modules;

use App\Models\User;
use App\Support\Modules\ModuleAdminEntry;
use App\Support\Modules\ModuleDefinition;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModuleRegistryTest extends TestCase
{
    /** @param  array<string, mixed>  $extra */
    private function module(string $key, int $order = 100, string $route = 'registry-test.index', array $extra = []): ModuleDefinition
    {
        return new ModuleDefinition(...[
            'key' => $key,
            'name' => 'modules.area',
            'icon' => 'trophy',
            'description' => 'modules.intro',
            'route' => $route,
            'routePatterns' => [$key.'.*'],
            'order' => $order,
            ...$extra,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/registry-test', fn () => 'ok')->name('registry-test.index');
        Route::get('/a/page', fn () => 'ok')->name('a.page');
        app('router')->getRoutes()->refreshNameLookups();
    }

    #[Test]
    public function modules_are_listed_by_order_then_registration(): void
    {
        $registry = new ModuleRegistry;
        $registry->register($this->module('b', 20));
        $registry->register($this->module('a', 10));
        $registry->register($this->module('c', 20));

        $this->assertSame(['a', 'b', 'c'], array_map(fn (ModuleDefinition $m) => $m->key, $registry->all()));
    }

    #[Test]
    public function registering_a_key_again_replaces_the_module(): void
    {
        $registry = new ModuleRegistry;
        $registry->register($this->module('a', 10));
        $registry->register($this->module('a', 50));

        $this->assertCount(1, $registry->all());
        $this->assertSame(50, $registry->find('a')?->order);
    }

    #[Test]
    public function a_module_without_its_entry_route_is_not_available(): void
    {
        $registry = new ModuleRegistry;
        $registry->register($this->module('a', 10));
        $registry->register($this->module('gone', 20, 'registry-test.missing'));

        $this->assertSame(['a'], array_map(fn (ModuleDefinition $m) => $m->key, $registry->available()));
    }

    #[Test]
    public function an_empty_key_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->module('');
    }

    #[Test]
    public function privileges_of_all_modules_are_collected_with_their_label_keys(): void
    {
        $registry = new ModuleRegistry;
        $registry->register($this->module('a', 10, extra: ['privileges' => ['admin.a' => 'x.label_a']]));
        $registry->register($this->module('b', 20, extra: ['privileges' => ['admin.b' => 'x.label_b']]));

        $this->assertSame(['admin.a' => 'x.label_a', 'admin.b' => 'x.label_b'], $registry->privileges());
    }

    #[Test]
    public function a_privilege_of_another_module_or_of_the_core_cannot_be_registered_again(): void
    {
        $registry = new ModuleRegistry;
        $registry->register($this->module('a', 10, extra: ['privileges' => ['admin.a' => 'x.label_a']]));
        $registry->register($this->module('a', 20, extra: ['privileges' => ['admin.a' => 'x.label_a2']]));

        try {
            $registry->register($this->module('b', 30, extra: ['privileges' => ['admin.a' => 'x.other']]));
            $this->fail('duplicate privilege accepted');
        } catch (InvalidArgumentException) {
            $this->assertSame(['admin.a' => 'x.label_a2'], $registry->privileges());
        }

        $this->expectException(InvalidArgumentException::class);
        $registry->register($this->module('c', 40, extra: ['privileges' => ['admin.user' => 'x.relabel']]));
    }

    #[Test]
    public function a_module_without_a_visible_rule_is_for_logged_in_users_and_a_closure_can_open_it_to_guests(): void
    {
        $private = $this->module('a');
        $public = $this->module('b', extra: ['visible' => fn () => true]);

        $this->assertFalse($private->visibleTo(null));
        $this->assertTrue($private->visibleTo(User::factory()->make()));
        $this->assertTrue($public->visibleTo(null));
    }

    #[Test]
    public function the_current_module_is_found_by_route_pattern(): void
    {
        $registry = new ModuleRegistry;
        $registry->register($this->module('a', 10));

        $request = Request::create('/a/page');
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        $this->assertSame('a', $registry->current($request)?->key);
        $this->assertTrue($registry->inArea($request));
        $this->assertNull($registry->current(Request::create('/elsewhere')));
    }

    #[Test]
    public function the_entry_url_comes_from_the_route_or_an_override(): void
    {
        $plain = $this->module('a');
        $custom = $this->module('b', extra: ['url' => fn () => 'https://example.test/b']);

        $this->assertSame(route('registry-test.index'), $plain->entryUrl());
        $this->assertSame('https://example.test/b', $custom->entryUrl());
    }

    #[Test]
    public function admin_entries_are_returned_for_available_routes_only(): void
    {
        $registry = new ModuleRegistry;
        $registry->register($this->module('a', 10, extra: ['adminEntries' => [
            new ModuleAdminEntry('a', 'registry-test.index', 'admin.a', 'x.a', ['a.*']),
            new ModuleAdminEntry('a_gone', 'registry-test.missing', 'admin.a', 'x.a', ['a.gone']),
        ]]));

        $this->assertSame(['a'], array_map(fn (ModuleAdminEntry $e) => $e->key, $registry->adminEntries()));
    }
}
