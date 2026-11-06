<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\TokenClaimer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Draw\Concerns\BuildsTokenDraw;
use Tests\TestCase;

class TokenClaimerTest extends TestCase
{
    use BuildsTokenDraw;
    use LazilyRefreshDatabase;

    #[Test]
    public function mysql_claim_sql_joins_the_draw_status(): void
    {
        $claimer = app(TokenClaimer::class);

        $this->assertSame(
            'UPDATE bs_draw_tokens AS t INNER JOIN bs_draws AS d ON d.did = t.did '
            .'SET t.claimed_tpid = ?, t.claimed_uid = ?, t.claimed = ?, t.assigned_by = ? '
            .'WHERE t.did = ? AND t.dtid = ? AND t.claimed_tpid IS NULL '
            .'AND d.status = ?',
            $claimer->claimSql('mysql'),
        );
        $this->assertSame($claimer->claimSql('mysql'), $claimer->claimSql('mariadb'));

        $sqlite = $claimer->claimSql('sqlite');
        $this->assertStringContainsString('EXISTS', $sqlite);
        $this->assertStringContainsString('d.status = ?', $sqlite);
        $this->assertStringContainsString('claimed_tpid IS NULL', $sqlite);
    }

    #[Test]
    public function lock_draw_reads_status_and_discarded(): void
    {
        $this->tokenDraw();
        $claimer = app(TokenClaimer::class);

        $row = $claimer->lockDraw((int) $this->draw->did);
        $this->assertNotNull($row);
        $this->assertSame([(int) $this->draw->did, 'picking', null], [(int) $row->did, $row->status, $row->discarded]);
        $this->assertNull($claimer->lockDraw(999999));
        $this->assertSame('SELECT did, status, discarded FROM bs_draws WHERE did = ? FOR UPDATE', $claimer->lockSql('mysql'));
        $this->assertSame('SELECT did, status, discarded FROM bs_draws WHERE did = ?', $claimer->lockSql('sqlite'));
    }

    #[Test]
    public function claim_while_picking_succeeds_once(): void
    {
        $this->tokenDraw();
        $claimer = app(TokenClaimer::class);
        $token = $this->tokenWithSlot('B2');

        $this->assertTrue($claimer->claimWhilePicking((int) $this->draw->did, (int) $token->dtid, $this->tpid[3], (int) $this->users[3]->uid));
        $this->assertFalse($claimer->claimWhilePicking((int) $this->draw->did, (int) $token->dtid, $this->tpid[4], (int) $this->users[4]->uid));

        $fresh = $token->fresh();
        $this->assertSame([$this->tpid[3], (int) $this->users[3]->uid, 'pick'], [(int) $fresh->claimed_tpid, (int) $fresh->claimed_uid, $fresh->assigned_by]);
        $this->assertNotNull($fresh->claimed);
    }

    #[Test]
    public function claim_while_picking_is_scoped_to_the_draw(): void
    {
        $this->tokenDraw();
        $other = Draw::factory()->token(4, 'AC', ['A2', 'B2', 'A3', 'B3'])->executed()->create();
        $foreign = DrawToken::query()->where('did', $other->did)->orderBy('position')->firstOrFail();

        $this->assertFalse(app(TokenClaimer::class)->claimWhilePicking((int) $this->draw->did, (int) $foreign->dtid, $this->tpid[3], 1));
        $this->assertNull($foreign->fresh()->claimed_tpid);
    }

    #[Test]
    public function claim_is_refused_when_draw_not_picking(): void
    {
        $this->tokenDraw();
        Draw::query()->whereKey($this->draw->did)->update(['status' => 'closed']);
        $token = $this->tokenWithSlot('A2');
        $before = $token->fresh()->getAttributes();

        $this->assertFalse(app(TokenClaimer::class)->claimWhilePicking((int) $this->draw->did, (int) $token->dtid, $this->tpid[3], (int) $this->users[3]->uid));
        $this->assertSame($before, $token->fresh()->getAttributes());
    }

    #[Test]
    public function a_player_cannot_hold_two_tokens(): void
    {
        $this->tokenDraw();
        $claimer = app(TokenClaimer::class);

        $this->assertTrue($claimer->claimWhilePicking((int) $this->draw->did, (int) $this->tokenWithSlot('A2')->dtid, $this->tpid[3], 1));

        $this->expectException(UniqueConstraintViolationException::class);
        $claimer->claimWhilePicking((int) $this->draw->did, (int) $this->tokenWithSlot('B2')->dtid, $this->tpid[3], 1);
    }

    #[Test]
    public function fallback_claim_takes_only_a_free_token(): void
    {
        $this->tokenDraw();
        Draw::query()->whereKey($this->draw->did)->update(['status' => 'closed']);
        $claimer = app(TokenClaimer::class);
        $token = $this->tokenWithSlot('A3');

        // The plain claim does not look at the draw status (close runs it after the status change).
        $this->assertTrue($claimer->claim((int) $token->dtid, $this->tpid[5], null, 'fallback'));
        $this->assertFalse($claimer->claim((int) $token->dtid, $this->tpid[6], (int) $this->users[6]->uid, 'fallback'));

        $fresh = $token->fresh();
        $this->assertSame([$this->tpid[5], 0, 'fallback'], [(int) $fresh->claimed_tpid, (int) $fresh->claimed_uid, $fresh->assigned_by]);
    }
}
