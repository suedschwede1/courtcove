<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Services\TournamentManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

/**
 * Manual lot orders that no longer fit their tie (review I-1): saving one is refused, an old one is ignored
 * (derived or open order instead of a 500 on every table) and reported on the admin page with a way to clear
 * it. A fitting order is applied exactly as before.
 */
class StaleLotOrderTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    /** Group A: seeds 1 and 4 both beat seed 5 by the same score and have not met: a full tie of 1 and 4. */
    private function tieInGroupA(): void
    {
        $this->beat(1, 5);
        $this->beat(4, 5);
    }

    private function storeOrders(array $orders): void
    {
        $this->tournament->update(['lot_orders' => json_encode($orders)]);
        $this->app->forgetScopedInstances();
    }

    #[Test]
    public function saving_an_order_that_does_not_match_the_tie_is_refused(): void
    {
        $this->championship();
        $this->tieInGroupA();
        $admin = $this->admin();
        $tied = [$this->tpid[1], $this->tpid[4]];
        sort($tied);

        $this->actingAs($admin)->post(route('tournament.admin.lot'), ['scope' => 'group:A', 'order' => $this->tpid[1].', '.$this->tpid[5]])
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('error', 'Die Teilnehmer passen nicht zum offenen Gleichstand ('.implode(', ', $tied).').');

        // A scope without any tie: no group of the phase is complete, so nobody is compared yet.
        $this->actingAs($admin)->post(route('tournament.admin.lot'), ['scope' => 'phase:G1:place:1', 'order' => $this->tpid[1].', '.$this->tpid[2]])
            ->assertSessionHas('error', 'In diesem Kontext gibt es derzeit keinen offenen Gleichstand – ein Losentscheid ist nicht nötig.');

        $this->assertNull($this->tournament->fresh()->lot_orders);

        // The exact tie is accepted, in any order.
        $this->actingAs($admin)->post(route('tournament.admin.lot'), ['scope' => 'group:A', 'order' => $this->tpid[4].' '.$this->tpid[1]])
            ->assertSessionHas('success', 'Losentscheid gespeichert: group:A');
        $this->assertSame(json_encode(['group:A' => [$this->tpid[4], $this->tpid[1]]]), $this->tournament->fresh()->lot_orders);
    }

    #[Test]
    public function a_fitting_order_is_applied_as_before(): void
    {
        $this->championship();
        $this->tieInGroupA();
        $this->storeOrders(['group:A' => [$this->tpid[4], $this->tpid[1]]]);

        $m = $this->app->make(TournamentManager::class);
        $table = $m->table((int) $this->tournament->tid, 'A');

        $this->assertSame([$this->tpid[4], $this->tpid[1], $this->tpid[5]], array_column($table, 'tpid'));
        $this->assertSame([], $m->staleLotScopes((int) $this->tournament->tid));
    }

    #[Test]
    public function a_mismatching_order_no_longer_breaks_the_pages(): void
    {
        $user = $this->member();
        $this->championship([1 => $user]);
        $this->tieInGroupA();
        // A typo (seed 5 instead of seed 4) and an entry for a comparison nobody is in yet.
        $this->storeOrders([
            'group:A' => [$this->tpid[1], $this->tpid[5]],
            'phase:G1:place:2' => [$this->tpid[1], $this->tpid[2]],
        ]);

        foreach ([route('tournament.groups', 'A'), route('tournament.groups'), route('tournament.index'), route('tournament.mine'), route('tournament.bracket')] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }

        $m = $this->app->make(TournamentManager::class);
        $tid = (int) $this->tournament->tid;
        // The stale entry is ignored: without an executed draw the tie stays open (seed order, needs_lot).
        $table = $m->table($tid, 'A');
        $this->assertSame([$this->tpid[1], $this->tpid[4], $this->tpid[5]], array_column($table, 'tpid'));
        $this->assertTrue($table[0]['needs_lot']);
        $m->promotion($tid, 'G1');
        $this->assertEqualsCanonicalizing(['group:A', 'phase:G1:place:2'], $m->staleLotScopes($tid));
    }

    #[Test]
    public function an_order_made_stale_by_a_result_change_is_ignored(): void
    {
        $user = $this->member();
        $this->championship([1 => $user]);
        $this->tieInGroupA();
        $this->storeOrders(['group:A' => [$this->tpid[4], $this->tpid[1]]]);

        // 1 now beats 4: the tie is gone, the entry no longer fits.
        $this->beat(1, 4);

        $this->actingAs($user)->get(route('tournament.groups', 'A'))->assertOk();
        $table = $this->app->make(TournamentManager::class)->table((int) $this->tournament->tid, 'A');
        $this->assertSame($this->tpid[1], $table[0]['tpid']);
    }

    #[Test]
    public function the_admin_page_shows_the_stale_scope_and_clears_it(): void
    {
        $this->championship();
        $this->tieInGroupA();
        $this->storeOrders(['group:A' => [$this->tpid[1], $this->tpid[5]], 'group:B' => [$this->tpid[2], $this->tpid[3], $this->tpid[6]]]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('tournament.admin'))
            ->assertOk()
            ->assertSee('Manueller Losentscheid passt nicht mehr: group:A')
            ->assertDontSee('Manueller Losentscheid passt nicht mehr: group:B')
            ->assertSee('Losentscheid offen: Gruppe A')
            ->assertSee('Eintrag entfernen');

        $this->actingAs($admin)->post(route('tournament.admin.lot'), ['scope' => 'group:A', 'clear' => '1'])
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('success', 'Losentscheid entfernt: group:A');
        $this->assertSame(json_encode(['group:B' => [$this->tpid[2], $this->tpid[3], $this->tpid[6]]]), $this->tournament->fresh()->lot_orders);

        $this->actingAs($admin)->post(route('tournament.admin.lot'), ['scope' => 'group:A', 'clear' => '1'])
            ->assertSessionHas('error', 'Für diesen Kontext ist kein Losentscheid gespeichert.');

        $this->actingAs($admin)->get(route('tournament.admin'))
            ->assertOk()
            ->assertDontSee('Manueller Losentscheid passt nicht mehr');
    }

    private function beat(int $winnerSeed, int $loserSeed): void
    {
        $a = $this->tpid[$winnerSeed];
        $b = $this->tpid[$loserSeed];
        $match = TournamentMatch::query()->where('tid', $this->tournament->tid)
            ->where(fn ($q) => $q->where(['tpid_a' => $a, 'tpid_b' => $b])->orWhere(fn ($q) => $q->where(['tpid_a' => $b, 'tpid_b' => $a])))
            ->firstOrFail();
        $aWins = (int) $match->tpid_a === $a;

        $match->update([
            'played' => '2027-05-03', 'played_time' => '17:00:00',
            'score' => $aWins ? '6:4 6:3' : '4:6 3:6',
            's1a' => $aWins ? 6 : 4, 's1b' => $aWins ? 4 : 6, 's2a' => $aWins ? 6 : 3, 's2b' => $aWins ? 3 : 6,
            'sets_a' => $aWins ? 2 : 0, 'sets_b' => $aWins ? 0 : 2, 'games_a' => $aWins ? 12 : 7, 'games_b' => $aWins ? 7 : 12,
            'winner' => $a,
        ]);
        $this->app->forgetScopedInstances();
    }
}
