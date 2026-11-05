<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Live check of the score entered on the challenge page: same validator as the submit, JSON only, parties only.
 */
class ScoreCheckEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PyramidLadder $ladder;

    private User $challengerUser;

    private User $opponentUser;

    private PyramidChallenge $challenge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ladder = PyramidLadder::factory()->asDefault()->withRanking(10)->create();
        $this->challengerUser = $this->userAt(8, 'Anna Beispiel');
        $this->opponentUser = $this->userAt(5, 'Berta Muster');

        $this->challenge = PyramidChallenge::factory()->create([
            'ladder_id' => $this->ladder->id,
            'challenger_id' => $this->playerAt(8)->id,
            'opponent_id' => $this->playerAt(5)->id,
            'challenger_pos' => 8,
            'opponent_pos' => 5,
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);
    }

    private function playerAt(int $position): PyramidPlayer
    {
        return PyramidPlayer::query()->where('ladder_id', $this->ladder->id)->where('position', $position)->firstOrFail();
    }

    private function userAt(int $position, string $label): User
    {
        $user = User::factory()->create();
        $this->playerAt($position)->update(['uid' => $user->uid, 'label' => $label]);

        return $user;
    }

    /**
     * @param  array<string, string>  $score
     */
    private function url(array $score, ?int $challengeId = null): string
    {
        return '/pyramide/forderung/'.($challengeId ?? $this->challenge->id).'/score-check?'.http_build_query($score);
    }

    #[Test]
    public function the_route_sits_in_the_participant_group(): void
    {
        $route = Route::getRoutes()->getByName('pyramide.challenge.score-check');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertContains('tournament-base.login:pyramid::ui.login_to_play', $route->gatherMiddleware());
    }

    #[Test]
    public function a_guest_is_sent_to_the_login(): void
    {
        $this->get($this->url(['s1a' => '6', 's1b' => '4']))->assertRedirect();
    }

    #[Test]
    public function an_outsider_gets_a_403_and_an_unknown_challenge_a_404(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->getJson($this->url(['s1a' => '6']))->assertForbidden();
        $this->actingAs($this->challengerUser)->getJson($this->url(['s1a' => '6'], 9999))->assertNotFound();
    }

    #[Test]
    public function a_valid_two_set_score_names_the_winner(): void
    {
        $this->actingAs($this->challengerUser)
            ->getJson($this->url(['s1a' => '6', 's1b' => '4', 's2a' => '6', 's2b' => '3']))
            ->assertOk()
            ->assertExactJson([
                'valid' => true,
                'state' => 'valid',
                'winner' => 'Anna Beispiel',
                'score' => '6:4 6:3',
                'message' => 'Gültig · Sieger: Anna Beispiel',
            ]);
    }

    #[Test]
    public function a_match_tiebreak_win_of_the_opponent_names_the_opponent(): void
    {
        $this->actingAs($this->opponentUser)
            ->getJson($this->url(['s1a' => '6', 's1b' => '4', 's2a' => '3', 's2b' => '6', 'mtba' => '7', 'mtbb' => '10']))
            ->assertOk()
            ->assertJson(['valid' => true, 'winner' => 'Berta Muster', 'message' => 'Gültig · Sieger: Berta Muster']);
    }

    #[Test]
    public function an_invalid_set_returns_the_message_of_the_submit(): void
    {
        $this->actingAs($this->challengerUser)
            ->getJson($this->url(['s1a' => '6', 's1b' => '5', 's2a' => '6', 's2b' => '3']))
            ->assertOk()
            ->assertExactJson([
                'valid' => false,
                'state' => 'invalid',
                'message' => 'Satz 1: 6:5 ist kein gültiges Satzergebnis. Möglich sind 6:0 bis 6:4, 7:5 und 7:6.',
            ]);
    }

    #[Test]
    public function an_unfinished_entry_is_incomplete_not_invalid(): void
    {
        $this->actingAs($this->challengerUser)
            ->getJson($this->url(['s1a' => '6', 's1b' => '4']))
            ->assertOk()
            ->assertJson(['valid' => false, 'state' => 'incomplete', 'message' => 'Satz 1 und Satz 2 müssen vollständig eingetragen werden.']);

        $this->actingAs($this->challengerUser)
            ->getJson($this->url(['s1a' => '6', 's1b' => '4', 's2a' => '3', 's2b' => '6']))
            ->assertOk()
            ->assertJson(['valid' => false, 'state' => 'incomplete']);
    }

    #[Test]
    public function an_empty_entry_has_no_message(): void
    {
        $this->actingAs($this->challengerUser)
            ->getJson($this->url([]))
            ->assertOk()
            ->assertExactJson(['valid' => false, 'state' => 'empty']);
    }

    #[Test]
    public function the_message_follows_the_session_language(): void
    {
        $this->actingAs($this->challengerUser)
            ->withUnencryptedCookie('locale', 'en')
            ->withCredentials()
            ->getJson($this->url(['s1a' => '6', 's1b' => '4', 's2a' => '6', 's2b' => '3']))
            ->assertOk()
            ->assertJson(['message' => 'Valid · Winner: Anna Beispiel']);
    }

    #[Test]
    public function the_check_writes_nothing(): void
    {
        $this->actingAs($this->challengerUser)
            ->getJson($this->url(['s1a' => '6', 's1b' => '4', 's2a' => '6', 's2b' => '3']))
            ->assertOk();

        $this->assertSame('scheduled', $this->challenge->fresh()->status);
        $this->assertNull($this->challenge->fresh()->score);
    }

    #[Test]
    public function the_result_form_wires_the_live_check_and_keeps_a_live_region(): void
    {
        $html = $this->actingAs($this->challengerUser)->get('/pyramide/forderung/'.$this->challenge->id)->assertOk()->getContent();

        $this->assertStringContainsString('score-check', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('x-text="message"', $html);
        // Without scripts the form still posts to the result route.
        $this->assertStringContainsString('action="'.route('pyramide.challenge.result', ['challengeId' => $this->challenge->id]).'"', $html);
    }
}
