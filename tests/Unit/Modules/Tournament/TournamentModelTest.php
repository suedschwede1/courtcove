<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tournament;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TournamentModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function datetimes_stay_raw_strings(): void
    {
        $t = Tournament::factory()->create(['created' => '2026-03-01 10:00:00'])->fresh();

        $this->assertSame('2026-03-01 10:00:00', $t->created);
        $this->assertIsString($t->created);
    }

    #[Test]
    public function relations_are_ordered(): void
    {
        $t = Tournament::factory()->create();
        TournamentPlayer::factory()->create(['tid' => $t->tid, 'seed' => 2]);
        $b = TournamentPlayer::factory()->create(['tid' => $t->tid, 'seed' => 1]);
        $c = TournamentPlayer::factory()->create(['tid' => $t->tid, 'seed' => 1]);
        TournamentPlayer::factory()->create(['tid' => $t->tid + 1, 'seed' => 1]);

        $players = $t->players()->get();
        $this->assertCount(3, $players);
        $this->assertSame([$b->tpid, $c->tpid], $players->take(2)->pluck('tpid')->all());
        $this->assertSame(2, $players[2]->seed);

        TournamentMatch::factory()->create(['tid' => $t->tid, 'phase' => 'KO', 'grp' => null, 'slot' => 1, 'mkey' => 'k1']);
        $m2 = TournamentMatch::factory()->create(['tid' => $t->tid, 'phase' => 'group', 'grp' => 'B', 'slot' => 1, 'mkey' => 'B-1']);
        $m3 = TournamentMatch::factory()->create(['tid' => $t->tid, 'phase' => 'group', 'grp' => 'A', 'slot' => 2, 'mkey' => 'A-2']);
        $m4 = TournamentMatch::factory()->create(['tid' => $t->tid, 'phase' => 'group', 'grp' => 'A', 'slot' => 1, 'mkey' => 'A-1']);

        $this->assertSame(
            [$m4->tmid, $m3->tmid, $m2->tmid],
            $t->matches()->where('phase', 'group')->pluck('tmid')->all(),
        );

        TournamentLine::factory()->create(['tid' => $t->tid, 'phase' => 'KO_B', 'line' => 1]);
        TournamentLine::factory()->create(['tid' => $t->tid, 'phase' => 'KO_A', 'line' => 2]);
        TournamentLine::factory()->create(['tid' => $t->tid, 'phase' => 'KO_A', 'line' => 1]);
        $this->assertSame(
            [['KO_A', 1], ['KO_A', 2], ['KO_B', 1]],
            $t->lines()->get()->map(fn (TournamentLine $l): array => [$l->phase, $l->line])->all(),
        );
    }

    #[Test]
    public function draws_are_ordered_by_did_and_scoped_by_tid(): void
    {
        $t = Tournament::factory()->create();
        $second = Draw::factory()->create(['tid' => $t->tid]);
        $third = Draw::factory()->create(['tid' => $t->tid]);
        Draw::factory()->create(['tid' => $t->tid + 1]);

        $this->assertSame([$second->did, $third->did], $t->draws()->pluck('did')->all());
    }

    #[Test]
    public function lot_orders_array_tolerates_garbage(): void
    {
        $this->assertSame([], Tournament::factory()->make(['lot_orders' => 'x'])->lotOrdersArray());
        $this->assertSame([], Tournament::factory()->make(['lot_orders' => null])->lotOrdersArray());
        $this->assertSame([], Tournament::factory()->make(['lot_orders' => '5'])->lotOrdersArray());
        $this->assertSame(
            ['group:A' => [3, 1, 2]],
            Tournament::factory()->withLotOrders(['group:A' => [3, 1, 2]])->make()->lotOrdersArray(),
        );
    }

    #[Test]
    public function real_scope_excludes_test_copies(): void
    {
        $real = Tournament::factory()->create();
        Tournament::factory()->test()->create();

        $this->assertSame([$real->tid], Tournament::query()->real()->pluck('tid')->all());
    }

    #[Test]
    public function is_played_means_winner_set(): void
    {
        $this->assertFalse(TournamentMatch::factory()->make()->isPlayed());
        $this->assertTrue(TournamentMatch::factory()->played(7, [[6, 3], [6, 4]])->make()->isPlayed());
    }

    #[Test]
    public function factories_build_fictional_data(): void
    {
        $user = User::factory()->create();
        $p = TournamentPlayer::factory()->forUser($user)->seeded(4)->inGroup('B', 2)->create();
        $m = TournamentMatch::factory()->group('B', 1, 3)->played(9, [[6, 3], [4, 6], [10, 7]])->confirmed($user->uid)->create();

        $this->assertSame($user->uid, $p->uid);
        $this->assertSame([4, 'B', 2], [$p->seed, $p->grp, $p->grp_pos]);
        $this->assertStringStartsWith('Demo Spieler', $p->label);
        $this->assertSame(['B-3', 2, 1, 10, 7], [$m->mkey, $m->sets_a, $m->sets_b, $m->mtba, $m->mtbb]);
        $this->assertSame(11, $m->games_a);
        $this->assertSame($user->uid, $m->confirmed_uid);
        $this->assertSame('Demo-Meisterschaft', Tournament::factory()->make()->title);
    }
}
