<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidEvent;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JoinControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function english_session_gets_the_english_flash(): void
    {
        $ladder = PyramidLadder::factory()->asDefault()->withRanking(3)->create();
        $user = User::factory()->create();

        $this->actingAs($user)->withUnencryptedCookie('locale', 'en')
            ->post('/pyramide/anmelden', ['ladderId' => $ladder->id])
            ->assertRedirect('/pyramide/'.$ladder->id)
            ->assertSessionHas('success', 'Sign-up saved. The administration will admit you to the ladder.');
    }

    #[Test]
    public function a_double_click_hitting_the_unique_index_shows_the_already_registered_info(): void
    {
        $ladder = PyramidLadder::factory()->asDefault()->withRanking(3)->create();
        $user = User::factory()->create();

        // The first click's row appears after both duplicate checks of the second click (controller,
        // service) and just before its insert: only the unique index stops it.
        $raced = false;
        PyramidPlayer::creating(function () use ($ladder, $user, &$raced): void {
            if ($raced) {
                return;
            }
            $raced = true;
            DB::table('bs_pyramid_players')->insert([
                'ladder_id' => $ladder->id, 'uid' => $user->uid, 'label' => 'Demo Spieler 99', 'status' => 'waiting',
                'itn_rated' => 10, 'joined' => now()->toDateTimeString(),
            ]);
        });

        $this->actingAs($user)->post('/pyramide/anmelden', ['ladderId' => $ladder->id])
            ->assertRedirect('/pyramide/'.$ladder->id)
            ->assertSessionHas('info', 'Du bist bereits angemeldet.')
            ->assertSessionMissing('success');

        $this->assertTrue($raced);
        $this->assertSame(0, PyramidEvent::query()->where('type', 'join')->count());
    }

    #[Test]
    public function the_player_row_is_rolled_back_when_the_join_event_cannot_be_written(): void
    {
        $ladder = PyramidLadder::factory()->asDefault()->withRanking(3)->create();
        $user = User::factory()->create();

        PyramidEvent::creating(fn () => throw new \RuntimeException('event write failed'));
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->post('/pyramide/anmelden', ['ladderId' => $ladder->id]);
            $this->fail('exception expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('event write failed', $e->getMessage());
        }

        $this->assertSame(0, PyramidPlayer::query()->where('uid', $user->uid)->count());
    }
}
