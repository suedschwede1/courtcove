<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\TokenEligibility;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\TournamentManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Draw\Concerns\BuildsTokenDraw;
use Tests\TestCase;

class TokenEligibilityTest extends TestCase
{
    use BuildsTokenDraw;
    use LazilyRefreshDatabase;

    /** @return array<int, true> */
    private function blocked(int $seed): array
    {
        $m = app(TournamentManager::class);
        $tid = (int) $this->tournament->tid;

        return app(TokenEligibility::class)->blockedTokens((int) $this->draw->did, $m->player($tid, $this->tpid[$seed]), $m, $tid);
    }

    #[Test]
    public function no_partner_blocks_nothing(): void
    {
        $this->tokenDraw();

        $this->assertSame([], $this->blocked(3));
        $this->assertSame([], app(TokenEligibility::class)->blockedTokens((int) $this->draw->did, null, app(TournamentManager::class), (int) $this->tournament->tid));
    }

    #[Test]
    public function partner_group_tokens_are_blocked(): void
    {
        $this->tokenDraw();
        // Seed 3 is the partner of the group head of B (seed 2): the free B tokens are blocked.
        $this->partners(3, 2);

        $this->assertEqualsCanonicalizing(
            [(int) $this->tokenWithSlot('B2')->dtid, (int) $this->tokenWithSlot('B3')->dtid],
            array_keys($this->blocked(3)),
        );
        $this->assertSame([true, true], array_values($this->blocked(3)));
    }

    #[Test]
    public function claimed_tokens_of_the_partner_group_are_not_listed(): void
    {
        $this->tokenDraw();
        $this->partners(3, 2);
        $this->claimDirectly($this->tokenWithSlot('B3'), 5);

        $this->assertSame([(int) $this->tokenWithSlot('B2')->dtid], array_keys($this->blocked(3)));
    }

    #[Test]
    public function partner_block_is_dropped_when_nothing_else_is_free(): void
    {
        $this->tokenDraw();
        $this->partners(3, 2);
        // Both A tokens are gone: only B tokens are free, so the rule gives way.
        $this->claimDirectly($this->tokenWithSlot('A2'), 4);
        $this->claimDirectly($this->tokenWithSlot('A3'), 5);

        $this->assertSame([], $this->blocked(3));
    }

    #[Test]
    public function partner_claimed_token_slot_counts_as_partner_group(): void
    {
        $this->tokenDraw();
        // Seeds 3 and 4 are both in the pot; seed 4 picked A3 (player row not updated yet).
        $this->partners(3, 4);
        $this->claimDirectly($this->tokenWithSlot('A3'), 4);
        $this->assertNull(TournamentPlayer::query()->findOrFail($this->tpid[4])->grp);

        $this->assertSame([(int) $this->tokenWithSlot('A2')->dtid], array_keys($this->blocked(3)));
    }

    #[Test]
    public function tokens_without_a_slot_are_never_blocked(): void
    {
        $this->tokenDraw();
        $this->partners(3, 2);
        DrawToken::query()->whereKey($this->tokenWithSlot('B2')->dtid)->update(['slot' => null]);

        $this->assertSame([(int) $this->tokenWithSlot('B3')->dtid], array_keys($this->blocked(3)));
    }
}
