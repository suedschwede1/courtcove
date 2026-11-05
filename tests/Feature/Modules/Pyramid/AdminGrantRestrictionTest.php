<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Club decision (deviation from ZF2): ladder rights are granted and revoked by global Pyramid admins only.
 */
class AdminGrantRestrictionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function holderOf(PyramidLadder $ladder): User
    {
        $holder = User::factory()->create();
        PyramidGrant::query()->create(['ladder_id' => $ladder->id, 'uid' => $holder->uid, 'role' => 'admin', 'created' => '2027-01-01 00:00:00']);

        return $holder;
    }

    #[Test]
    public function a_ladder_admin_with_only_a_grant_may_neither_grant_nor_revoke(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(3)->create();
        $holder = $this->holderOf($ladder);
        $member = User::factory()->create();
        $url = '/pyramide/admin/ladder/'.$ladder->id;

        $this->actingAs($holder)->post($url.'/grant', ['uid' => (string) $member->uid])->assertForbidden();
        $this->assertSame(0, PyramidGrant::query()->where('ladder_id', $ladder->id)->where('uid', $member->uid)->count());

        $this->actingAs($holder)->post($url.'/revoke', ['uid' => (string) $holder->uid])->assertForbidden();
        $this->assertSame(1, PyramidGrant::query()->where('ladder_id', $ladder->id)->where('uid', $holder->uid)->count());

        // Everything else on the granted ladder still works for the holder.
        $this->actingAs($holder)->get($url)->assertOk();
    }

    #[Test]
    public function the_grants_card_is_only_shown_to_global_admins(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(3)->create();
        $holder = $this->holderOf($ladder);
        $url = '/pyramide/admin/ladder/'.$ladder->id;

        $this->actingAs($holder)->get($url)
            ->assertOk()
            ->assertDontSee('Verwaltungsrechte')
            ->assertDontSee($url.'/grant');

        $this->actingAs(User::factory()->status('admin')->create())->get($url)
            ->assertOk()
            ->assertSee('Verwaltungsrechte')
            ->assertSee($url.'/grant');
    }
}
