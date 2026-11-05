<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidEvent;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Club decision (deviation from ZF2): a ladder can only be closed once no challenge is open or scheduled.
 */
class AdminCloseBlockedTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PyramidLadder $ladder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ladder = PyramidLadder::factory()->asDefault()->withRanking(12)->create();
    }

    private function admin(): User
    {
        return User::factory()->status('admin')->create();
    }

    private function player(int $position): PyramidPlayer
    {
        return PyramidPlayer::query()->where('ladder_id', $this->ladder->id)->where('position', $position)->firstOrFail();
    }

    private function challenge(int $challengerPos, int $opponentPos, string $status): PyramidChallenge
    {
        return PyramidChallenge::factory()->create([
            'ladder_id' => $this->ladder->id,
            'challenger_id' => $this->player($challengerPos)->id,
            'opponent_id' => $this->player($opponentPos)->id,
            'challenger_pos' => $challengerPos,
            'status' => $status,
        ]);
    }

    private function closeUrl(): string
    {
        return '/pyramide/admin/ladder/'.$this->ladder->id.'/close';
    }

    #[Test]
    public function close_is_refused_and_lists_the_open_and_scheduled_challenges(): void
    {
        $open = $this->challenge(8, 5, 'open');
        $scheduled = $this->challenge(9, 6, 'scheduled');
        $this->challenge(10, 7, 'played');
        $this->challenge(11, 8, 'declined');

        $response = $this->actingAs($this->admin())->post($this->closeUrl());

        $response->assertRedirect('/pyramide/admin/ladder/'.$this->ladder->id);
        $message = (string) session('error');
        $this->assertStringStartsWith('Die Saison kann noch nicht abgeschlossen werden', $message);
        foreach ([$open, $scheduled] as $challenge) {
            $this->assertStringContainsString($challenge->challenger->label.' – '.$challenge->opponent->label, $message);
        }
        $this->assertStringNotContainsString($this->player(10)->label.' – ', $message);
        $this->assertStringNotContainsString('weitere', $message);
        $this->assertSame('running', $this->ladder->fresh()->status);
        $this->assertSame(0, PyramidEvent::query()->where('ladder_id', $this->ladder->id)->where('type', 'admin')->count());
    }

    #[Test]
    public function a_long_list_is_cut_after_ten_entries(): void
    {
        foreach (range(2, 12) as $pos) {
            $this->challenge($pos, 1, 'open');
        }
        $this->challenge(2, 3, 'scheduled');

        $this->actingAs($this->admin())->post($this->closeUrl());

        $message = (string) session('error');
        $this->assertStringContainsString('und 2 weitere', $message);
        $this->assertSame(10, substr_count($message, ' – '));
        $this->assertSame('running', $this->ladder->fresh()->status);
    }

    #[Test]
    public function close_works_once_the_challenges_are_resolved_and_stays_idempotent(): void
    {
        $this->challenge(8, 5, 'played');
        $this->challenge(9, 6, 'expired');

        $this->actingAs($this->admin())->post($this->closeUrl())->assertSessionHas('success', 'Saison abgeschlossen.');
        $this->assertSame('closed', $this->ladder->fresh()->status);

        // A legacy closed ladder with a stuck challenge still answers the second close quietly.
        $this->challenge(2, 1, 'open');
        $this->actingAs($this->admin())->post($this->closeUrl())->assertSessionHas('info', 'Die Saison war bereits abgeschlossen.');
        $this->assertSame(1, PyramidEvent::query()->where('ladder_id', $this->ladder->id)->where('type', 'admin')->count());
    }

    #[Test]
    public function the_ladder_page_shows_the_note_and_disables_the_button(): void
    {
        $this->actingAs($this->admin());
        $url = '/pyramide/admin/ladder/'.$this->ladder->id;

        $this->get($url)->assertOk()
            ->assertDontSee('Die Saison kann noch nicht abgeschlossen werden');

        $challenge = $this->challenge(8, 5, 'open');
        $html = $this->get($url)->assertOk()
            ->assertSee('Die Saison kann noch nicht abgeschlossen werden')
            ->assertSee($challenge->challenger->label.' – '.$challenge->opponent->label)
            ->getContent();
        $this->assertMatchesRegularExpression('/<button[^>]*disabled[^>]*>\s*<span>Saison abschließen<\/span>/', $html);
    }
}
