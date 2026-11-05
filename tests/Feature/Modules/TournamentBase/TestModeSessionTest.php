<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TournamentBase;

use App\Models\User;
use App\Models\UserMeta;
use App\Modules\TournamentBase\Support\TestModeSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TestModeSessionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function sessionMode(): TestModeSession
    {
        return new TestModeSession(new Store('test', new ArraySessionHandler(120)));
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function non_admin_cannot_simulate(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);

        $this->expectException(AuthorizationException::class);
        $this->sessionMode()->simulate($member, 'member');
    }

    #[Test]
    public function member_simulation_is_stored(): void
    {
        $mode = $this->sessionMode();
        $admin = $this->admin();

        $mode->simulate($admin, 'member');

        $this->assertSame(['mode' => 'member', 'tpid' => null], $mode->simulation($admin));
    }

    #[Test]
    public function player_simulation_needs_a_tpid(): void
    {
        $mode = $this->sessionMode();
        $admin = $this->admin();

        try {
            $mode->simulate($admin, 'player');
            $this->fail('expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('invalid simulation request', $e->getMessage());
        }

        $this->assertNull($mode->simulation($admin));

        $mode->simulate($admin, 'player', 7);
        $this->assertSame(['mode' => 'player', 'tpid' => 7], $mode->simulation($admin));

        $this->expectException(InvalidArgumentException::class);
        $mode->simulate($admin, 'ghost');
    }

    #[Test]
    public function stop_clears_the_simulation(): void
    {
        $mode = $this->sessionMode();
        $admin = $this->admin();
        $mode->simulate($admin, 'member');

        $mode->stop();

        $this->assertNull($mode->simulation($admin));
    }

    #[Test]
    public function admin_may_select_a_tournament(): void
    {
        $mode = $this->sessionMode();
        $admin = $this->admin();

        $mode->selectTid($admin, 5);

        $this->assertSame(5, $mode->selectedTid($admin));

        $assist = User::factory()->create(['status' => 'assist']);
        UserMeta::create(['uid' => $assist->uid, 'key' => 'allow.admin.tournament', 'value' => 'true']);
        $mode->selectTid($assist, 6);
        $this->assertSame(6, $mode->selectedTid($assist));
    }

    #[Test]
    public function switching_tournament_stops_simulation(): void
    {
        $mode = $this->sessionMode();
        $admin = $this->admin();
        $mode->selectTid($admin, 5);
        $mode->simulate($admin, 'player', 9);

        $mode->selectTid($admin, 6);

        $this->assertNull($mode->simulation($admin));
        $this->assertSame(6, $mode->selectedTid($admin));
    }

    #[Test]
    public function same_tournament_keeps_simulation(): void
    {
        $mode = $this->sessionMode();
        $admin = $this->admin();
        $mode->selectTid($admin, 5);
        $mode->simulate($admin, 'player', 9);

        $mode->selectTid($admin, 5);

        $this->assertSame(['mode' => 'player', 'tpid' => 9], $mode->simulation($admin));
    }

    #[Test]
    public function non_admin_sees_no_selection(): void
    {
        $mode = $this->sessionMode();
        $admin = $this->admin();
        $mode->selectTid($admin, 5);
        $mode->simulate($admin, 'member');

        $member = User::factory()->create(['status' => 'enabled']);

        $this->assertNull($mode->simulation($member));
        $this->assertNull($mode->selectedTid($member));

        $this->expectException(AuthorizationException::class);
        $mode->selectTid($member, 5);
    }
}
