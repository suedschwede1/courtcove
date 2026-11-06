<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Detail pages (challenge, player, confirm) carry a back bar instead of the ladder header, chips and tabs.
 */
class DetailPagesLayoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // These tests pin the public behaviour (config off); TournamentLoginRequiredTest covers the default.
        config(['tournament-base.require_login' => false]);
    }

    private function ladder(): PyramidLadder
    {
        return PyramidLadder::factory()->asDefault()->withRanking(10)->create();
    }

    private function playerAt(PyramidLadder $ladder, int $position): PyramidPlayer
    {
        return PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', $position)->firstOrFail();
    }

    /**
     * @return array{0: PyramidLadder, 1: User, 2: PyramidChallenge}
     */
    private function scheduledChallenge(): array
    {
        $ladder = $this->ladder();
        $viewer = User::factory()->create();
        $this->playerAt($ladder, 9)->update(['uid' => $viewer->uid, 'label' => 'Anna Beispiel']);
        $this->playerAt($ladder, 6)->update(['label' => 'Berta Muster']);

        $challenge = PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id,
            'challenger_id' => $this->playerAt($ladder, 9)->id,
            'opponent_id' => $this->playerAt($ladder, 6)->id,
            'challenger_pos' => 9,
            'opponent_pos' => 6,
            'status' => 'scheduled',
            'scheduled_at' => '2027-03-07 10:00:00',
            'deadline' => '2027-03-18',
        ]);

        return [$ladder, $viewer, $challenge];
    }

    #[Test]
    public function the_challenge_page_has_a_back_bar_and_no_ladder_tabs(): void
    {
        [$ladder, $viewer, $challenge] = $this->scheduledChallenge();

        $this->actingAs($viewer)
            ->get('/pyramide/forderung/'.$challenge->id)
            ->assertOk()
            ->assertSee('Anna Beispiel fordert Berta Muster')
            ->assertSee('So, 7. Mär 10:00')
            ->assertSee('Frist 18.03.2027')
            ->assertSee('href="'.url('/pyramide/'.$ladder->id).'"', false)
            ->assertDontSee('aria-label="Pyramide"', false)
            ->assertDontSee('Pyramide wählen');
    }

    #[Test]
    public function detail_pages_bring_the_phone_top_bar_which_replaces_the_site_header_on_phones(): void
    {
        [$ladder, $viewer, $challenge] = $this->scheduledChallenge();
        $back = 'href="'.url('/pyramide/'.$ladder->id).'"';
        // The confirm page needs a participant without a running challenge.
        $free = User::factory()->create();
        $this->playerAt($ladder, 8)->update(['uid' => $free->uid]);

        $pages = [
            '/pyramide/forderung/'.$challenge->id => ['Forderung', $viewer],
            '/pyramide/spieler/'.$this->playerAt($ladder, 6)->id => ['Spielerprofil', $viewer],
            '/pyramide/fordern/'.$this->playerAt($ladder, 5)->id => ['Forderung aussprechen', $free],
        ];

        foreach ($pages as $url => [$title, $user]) {
            $html = (string) $this->actingAs($user)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('<div class="ds-header-host--phone-bar">', $html, $url);
            $this->assertMatchesRegularExpression('#<header class="ds-phone-bar ds-on-chrome no-print">.*?'.preg_quote($back, '#').'.*?<h1 class="ds-phone-bar__title">'.preg_quote($title, '#').'</h1>#s', $html, $url);
            // The desktop head stays, hidden on phones.
            $this->assertStringContainsString('<div class="flex items-center gap-2 max-md:hidden">', $html, $url);
        }
    }

    #[Test]
    public function the_ladder_page_keeps_the_plain_site_header(): void
    {
        $this->ladder();

        $html = (string) $this->get('/pyramide')->assertOk()->getContent();

        $this->assertStringNotContainsString('ds-phone-bar', $html);
        $this->assertStringNotContainsString('ds-header-host--phone-bar', $html);
    }

    #[Test]
    public function the_result_form_heads_its_columns_with_the_abbreviated_players(): void
    {
        [, $viewer, $challenge] = $this->scheduledChallenge();

        $html = $this->actingAs($viewer)->get('/pyramide/forderung/'.$challenge->id)->assertOk()->getContent();

        $this->assertStringContainsString('title="Anna Beispiel">A. Beispiel</span>', $html);
        $this->assertStringContainsString('title="Berta Muster">B. Muster</span>', $html);
    }

    #[Test]
    public function a_result_form_never_uses_the_last_word_of_a_numbered_label(): void
    {
        [, $viewer, $challenge] = $this->scheduledChallenge();
        PyramidPlayer::query()->whereKey($challenge->challenger_id)->update(['label' => 'Demo Spieler 09']);

        $html = $this->actingAs($viewer)->get('/pyramide/forderung/'.$challenge->id)->assertOk()->getContent();

        $this->assertStringContainsString('title="Demo Spieler 09">D. Spieler 09</span>', $html);
        $this->assertStringNotContainsString('>09</span>', $html);
    }

    #[Test]
    public function the_player_page_has_a_back_bar_and_no_ladder_tabs(): void
    {
        $ladder = $this->ladder();
        $target = $this->playerAt($ladder, 5);
        $target->update(['label' => 'Berta Muster']);

        $this->get('/pyramide/spieler/'.$target->id)
            ->assertOk()
            ->assertSee('Spielerprofil')
            ->assertSee('Berta Muster')
            ->assertSee('href="'.url('/pyramide/'.$ladder->id).'"', false)
            ->assertDontSee('aria-label="Pyramide"', false);
    }

    #[Test]
    public function the_confirm_page_has_a_back_bar_and_no_ladder_tabs(): void
    {
        $ladder = $this->ladder();
        $viewer = User::factory()->create();
        $this->playerAt($ladder, 8)->update(['uid' => $viewer->uid]);

        $this->actingAs($viewer)
            ->get('/pyramide/fordern/'.$this->playerAt($ladder, 5)->id)
            ->assertOk()
            ->assertSee('Forderung aussprechen')
            ->assertDontSee('aria-label="Pyramide"', false);
    }
}
