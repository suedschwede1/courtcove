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
 * Club decision (deviation from ZF2): only global Pyramid admins decide which ladder /pyramide shows.
 */
class AdminDefaultFlagTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const DAYS = ['days_to_play' => 14, 'winner_protected_days' => 7, 'loser_cooldown_days' => 3];

    private function holderOf(PyramidLadder $ladder): User
    {
        $holder = User::factory()->create();
        PyramidGrant::query()->create(['ladder_id' => $ladder->id, 'uid' => $holder->uid, 'role' => 'admin', 'created' => '2027-01-01 00:00:00']);

        return $holder;
    }

    #[Test]
    public function a_ladder_admin_can_neither_set_nor_clear_the_default_flag(): void
    {
        $default = PyramidLadder::factory()->create(['is_default' => 1]);
        $mine = PyramidLadder::factory()->create(['is_default' => 0, 'title' => 'Alt']);
        $holder = $this->holderOf($mine);

        // Ticking the box on their own ladder must not take the default away from the other one.
        $this->actingAs($holder)->post('/pyramide/admin/ladder/'.$mine->id.'/config', ['title' => 'Neu', 'is_default' => '1'] + self::DAYS)
            ->assertSessionHas('success', 'Einstellungen gespeichert.');
        $this->assertSame(0, (int) $mine->fresh()->is_default);
        $this->assertSame(1, (int) $default->fresh()->is_default);
        $this->assertSame('Neu', $mine->fresh()->title);
        $this->assertSame(14, (int) $mine->fresh()->days_to_play);

        // Saving without the box (as the hidden field does) must not clear the flag of a default ladder either.
        $holderOfDefault = $this->holderOf($default);
        $this->actingAs($holderOfDefault)->post('/pyramide/admin/ladder/'.$default->id.'/config', ['title' => 'Standard'] + self::DAYS)
            ->assertSessionHas('success');
        $this->assertSame(1, (int) $default->fresh()->is_default);
        $this->assertSame('Standard', $default->fresh()->title);
    }

    #[Test]
    public function a_global_admin_still_moves_the_default_flag(): void
    {
        $default = PyramidLadder::factory()->create(['is_default' => 1]);
        $other = PyramidLadder::factory()->create(['is_default' => 0]);

        $this->actingAs(User::factory()->status('admin')->create())
            ->post('/pyramide/admin/ladder/'.$other->id.'/config', ['title' => 'Neu', 'is_default' => '1'] + self::DAYS)
            ->assertSessionHas('success');

        $this->assertSame(1, (int) $other->fresh()->is_default);
        $this->assertSame(0, (int) $default->fresh()->is_default);
    }

    #[Test]
    public function the_default_checkbox_is_only_offered_to_global_admins(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(3)->create();
        $url = '/pyramide/admin/ladder/'.$ladder->id;

        $this->actingAs($this->holderOf($ladder))->get($url)
            ->assertOk()
            ->assertDontSee('name="is_default"', false)
            ->assertDontSee('Unter /pyramide zeigen');

        $this->actingAs(User::factory()->status('admin')->create())->get($url)
            ->assertOk()
            ->assertSee('name="is_default"', false);
    }
}
