<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\Draws\DrawApplier;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\TournamentBase\Support\ActionRefused;
use Closure;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Draw\Concerns\BuildsTokenDraw;
use Tests\Feature\Modules\Tournament\Concerns\RebuildsTournamentMysqlTables;
use Tests\MysqlTestCase;
use Tests\Support\SecondConnection;

/**
 * Review Focus 3 on a real MySQL server: a parallel apply that marks the draw first must roll back every
 * row update of the other apply; the mark itself serialises two applies on the draw row.
 */
#[Group('mysql')]
class ApplyRaceMysqlTest extends MysqlTestCase
{
    use BuildsTokenDraw;
    use RebuildsTournamentMysqlTables;

    private User $admin;

    /** The token draw closed with every token claimed: seeds 3..6 in slot order A2, B2, A3, B3. */
    private function closedTokenDraw(): Draw
    {
        $this->admin = User::factory()->create(['status' => 'admin']);
        $this->tokenDraw();
        foreach (['A2' => 3, 'B2' => 4, 'A3' => 5, 'B3' => 6] as $slot => $seed) {
            $this->claimDirectly($this->tokenWithSlot($slot), $seed);
        }
        Draw::query()->whereKey($this->draw->did)->update(['status' => 'closed', 'closed' => '2027-03-01 20:00:00']);

        return $this->draw = $this->draw->fresh();
    }

    private function applierWithHook(Closure $hook): DrawApplier
    {
        return new class(app(TournamentManager::class), $hook) extends DrawApplier
        {
            public function __construct(TournamentManager $m, private readonly Closure $hook)
            {
                parent::__construct($m);
            }

            protected function beforeMark(int $did): void
            {
                ($this->hook)($did);
            }
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function players(): array
    {
        return TournamentPlayer::query()->where('tid', $this->tournament->tid)->orderBy('tpid')->get()->map->getAttributes()->all();
    }

    #[Test]
    public function parallel_apply_rolls_back_every_row_update(): void
    {
        $draw = $this->closedTokenDraw();
        $before = $this->players();
        $second = SecondConnection::make();
        $fired = false;

        // B marks the draw and commits after A validated and wrote its rows, before A's conditional mark.
        $applier = $this->applierWithHook(function (int $did) use ($second, &$fired): void {
            $fired = true;
            $this->assertSame(1, $second->table('bs_draws')->where('did', $did)->whereNull('applied_at')
                ->update(['applied_at' => '2027-03-01 21:00:00', 'applied_uid' => 4711]));
        });

        try {
            $applier->apply($draw, (int) $this->tournament->tid, $this->admin);
            $this->fail('the parallel apply must be refused');
        } catch (ActionRefused $e) {
            $this->assertSame('tournament::admin.apply.parallel', $e->key);
        }

        $this->assertTrue($fired);
        $this->assertSame($before, $this->players(), 'no player row changed');
        $this->assertSame(4711, (int) $draw->fresh()->applied_uid, 'the mark of the other apply stays');
    }

    #[Test]
    public function an_open_parallel_mark_makes_the_apply_wait_and_roll_back(): void
    {
        $draw = $this->closedTokenDraw();
        $before = $this->players();
        $second = SecondConnection::make();
        SecondConnection::waitAtMost(1);

        // B is in the middle of its own apply: marked, not committed.
        $second->beginTransaction();
        $this->assertSame(1, $second->table('bs_draws')->where('did', $draw->did)->whereNull('applied_at')
            ->update(['applied_at' => '2027-03-01 21:00:00', 'applied_uid' => 4711]));

        try {
            app(DrawApplier::class)->apply($draw, (int) $this->tournament->tid, $this->admin);
            $this->fail('the mark must wait for the open apply');
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1]);
        }
        $this->assertSame($before, $this->players(), 'the waiting apply rolled its rows back');

        $second->commit();

        // A stale page (applied_at still empty in its model) gets the parallel refusal, nothing written.
        try {
            app(DrawApplier::class)->apply($draw, (int) $this->tournament->tid, $this->admin);
            $this->fail('refused after the commit');
        } catch (ActionRefused $e) {
            $this->assertSame('tournament::admin.apply.parallel', $e->key);
        }
        $this->assertSame($before, $this->players());
    }

    #[Test]
    public function a_plain_apply_writes_every_row_and_marks_once(): void
    {
        $draw = $this->closedTokenDraw();

        $this->assertSame(4, app(DrawApplier::class)->apply($draw, (int) $this->tournament->tid, $this->admin));

        foreach (['A2' => 3, 'B2' => 4, 'A3' => 5, 'B3' => 6] as $slot => $seed) {
            $player = TournamentPlayer::query()->findOrFail($this->tpid[$seed]);
            $this->assertSame($slot, $player->grp.$player->grp_pos);
        }
        $this->assertSame((int) $this->admin->uid, (int) $draw->fresh()->applied_uid);
    }
}
