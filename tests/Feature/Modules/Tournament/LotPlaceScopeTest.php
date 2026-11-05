<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawService;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\TournamentManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins the scope strings of the cross-group lot decisions at the manager level (Review Focus 1).
 * Three groups of three are complete with identical results, so the three winners tie and the three
 * runners-up tie. The seed was searched so that, for both tied sets, the order under the right scope
 * differs from the order under every wrong scope below and under swapped HMAC arguments.
 */
class LotPlaceScopeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const SEED_LABEL = 'promotion-seed-16';

    /** @return array<string, mixed> */
    private function format(): array
    {
        return json_decode((string) file_get_contents(base_path('tests/Fixtures/tournament/formats/three-groups-with-bye.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function seedTournament(?string $lotOrders = null): TournamentManager
    {
        Tournament::factory()->withFormat($this->format())->create(['tid' => 1, 'lot_orders' => $lotOrders]);
        Draw::factory()->create(['did' => 1, 'tid' => 1, 'final_seed' => hash('sha256', self::SEED_LABEL)]);

        $groups = ['A' => [1, 2, 3], 'B' => [4, 5, 6], 'C' => [7, 8, 9]];
        $tmid = 0;
        foreach ($groups as $grp => $tpids) {
            foreach ($tpids as $pos => $tpid) {
                DB::table('bs_tournament_players')->insert([
                    'tpid' => $tpid, 'tid' => 1, 'label' => 'Demo Spieler '.$tpid, 'itn_rated' => 10.0, 'seed' => $tpid, 'grp' => $grp, 'grp_pos' => $pos + 1,
                ]);
            }
            foreach ([[0, 1], [0, 2], [1, 2]] as $slot => [$a, $b]) {
                DB::table('bs_tournament_matches')->insert([
                    'tmid' => ++$tmid, 'tid' => 1, 'phase' => 'group', 'mkey' => $grp.'-'.$slot, 'grp' => $grp, 'slot' => $slot + 1,
                    'tpid_a' => $tpids[$a], 'tpid_b' => $tpids[$b], 'winner' => $tpids[$a],
                    'sets_a' => 2, 'sets_b' => 0, 'games_a' => 12, 'games_b' => 6, 'played' => '2027-05-02',
                ]);
            }
        }

        return $this->app->make(TournamentManager::class);
    }

    /** @param  list<int>  $tied */
    private function derived(array $tied, string $scope): array
    {
        return (new DrawService)->shuffle($tied, hash_hmac('sha256', $scope, hash('sha256', self::SEED_LABEL)));
    }

    #[Test]
    public function promotion_ties_are_decided_by_the_place_scopes(): void
    {
        $m = $this->seedTournament();

        $promotion = $m->promotion(1, 'G');

        $this->assertSame([], $promotion['pending']);
        foreach ([1 => [1, 4, 7], 2 => [2, 5, 8]] as $place => $tied) {
            $rows = $place === 1 ? $promotion['winners'] : $promotion['seconds'];
            $right = $this->derived($tied, "phase:G:place:$place");

            $this->assertSame($right, array_column($rows, 'tpid'), "place $place order");
            $this->assertSame([false, false, false], array_column($rows, 'needs_lot'));

            // the seed discriminates: no wrong scope and no swapped arguments gives the same order
            $seed = hash('sha256', self::SEED_LABEL);
            foreach (['phase:G:place:'.(3 - $place), 'phase:G:place', "phase:G:place:$place ", 'group:A', "phase:DG:place:$place"] as $wrong) {
                $this->assertNotSame($right, $this->derived($tied, $wrong), "guard: scope '$wrong'");
            }
            $this->assertNotSame($right, (new DrawService)->shuffle($tied, hash_hmac('sha256', $seed, "phase:G:place:$place")), 'guard: swapped arguments');
            $this->assertNotSame($tied, $right, 'guard: not the identity order');
        }
    }

    #[Test]
    public function a_manual_place_order_overrides_the_derived_one_in_promotion(): void
    {
        $m = $this->seedTournament(json_encode(['phase:G:place:1' => [7, 1, 4]]));

        $promotion = $m->promotion(1, 'G');

        $this->assertSame([7, 1, 4], array_column($promotion['winners'], 'tpid'));
        $this->assertSame($this->derived([2, 5, 8], 'phase:G:place:2'), array_column($promotion['seconds'], 'tpid'), 'place 2 stays derived');
    }

    #[Test]
    public function a_manual_group_order_overrides_the_derived_one_in_the_table(): void
    {
        // group A: make it a three-way tie (each beats the next in a circle, equal scores)
        $m = $this->seedTournament(json_encode(['group:A' => [3, 2, 1]]));
        DB::table('bs_tournament_matches')->where('mkey', 'A-1')->update(['winner' => 3, 'sets_a' => 0, 'sets_b' => 2, 'games_a' => 6, 'games_b' => 12]);
        $m->forget(1);

        $table = $m->table(1, 'A');

        $this->assertSame([3, 2, 1], array_column($table, 'tpid'));
        $this->assertSame([false, false, false], array_column($table, 'needs_lot'));
    }
}
