<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use App\Services\MainNavigation;
use App\Support\Ui\NavItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MainNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Schema::dropIfExists('bs_tournaments');
        parent::tearDown();
    }

    private function createTournamentsTable(): void
    {
        Schema::dropIfExists('bs_tournaments');
        Schema::create('bs_tournaments', function ($table): void {
            $table->increments('tid');
            $table->string('title');
            $table->string('status')->default('running');
            $table->integer('season')->default(2026);
            $table->tinyInteger('active')->default(0);
            $table->tinyInteger('is_test')->default(0);
        });
    }

    /** @return list<NavItem> */
    private function items(?User $user, string $path = '/calendar'): array
    {
        $request = Request::create(url($path));
        // Resolve the route like a real request so routeIs() works.
        $request->setRouteResolver(function () use ($request) {
            try {
                return app('router')->getRoutes()->match($request);
            } catch (\Throwable) {
                return null;
            }
        });

        return app(MainNavigation::class)->items($user, $request);
    }

    /**
     * @param  list<NavItem>  $items
     * @return list<string>
     */
    private function keys(array $items): array
    {
        return array_map(fn (NavItem $i) => $i->key, $items);
    }

    /**
     * @param  list<NavItem>  $items
     * @return list<string>
     */
    private function activeKeys(array $items): array
    {
        return array_values(array_map(fn (NavItem $i) => $i->key, array_filter($items, fn (NavItem $i) => $i->active)));
    }

    #[Test]
    public function guest_only_sees_calendar(): void
    {
        $items = $this->items(null);

        $this->assertSame(['calendar'], $this->keys($items));
        $this->assertSame(['calendar'], $this->activeKeys($items));
    }

    #[Test]
    public function member_gets_the_member_items(): void
    {
        $this->assertSame(
            ['calendar', 'bookings', 'pyramid', 'mixed', 'account'],
            $this->keys($this->items(User::factory()->create())),
        );
    }

    #[Test]
    public function running_championship_follows_bookings(): void
    {
        $this->createTournamentsTable();
        DB::table('bs_tournaments')->insert(['title' => 'VM 2026', 'status' => 'running', 'season' => 2026, 'active' => 1, 'is_test' => 0]);

        $items = $this->items(User::factory()->create());

        $this->assertSame(['calendar', 'bookings', 'championship', 'pyramid', 'mixed', 'account'], $this->keys($items));
        $this->assertSame('VM 2026', $items[2]->label);
        $this->assertSame(url('/tournament'), $items[2]->url);
    }

    #[Test]
    public function admin_gets_admin_and_members_last(): void
    {
        $keys = $this->keys($this->items(User::factory()->status('admin')->create()));

        $this->assertSame(['admin', 'members'], array_slice($keys, -2));
    }

    #[Test]
    public function tournament_path_activates_only_that_item(): void
    {
        $items = $this->items(User::factory()->create(), '/pyramide/ladder/1');

        $this->assertSame(['pyramid'], $this->activeKeys($items));
    }

    #[Test]
    public function account_is_active_but_not_in_the_bar(): void
    {
        $items = $this->items(User::factory()->create(), '/my-account');
        $account = collect($items)->firstWhere('key', 'account');

        $this->assertNotNull($account);
        $this->assertTrue($account->active);
        $this->assertFalse($account->inBar);
        $this->assertSame(['account'], $this->activeKeys($items));
    }
}
