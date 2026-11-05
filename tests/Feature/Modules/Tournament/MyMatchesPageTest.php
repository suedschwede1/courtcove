<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\TournamentBase\Support\TestModeSession;
use App\Modules\TournamentBase\Support\WhatsApp;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

/** The viewer plays seed 1 (group A position 1): A-0 against seed 4, A-1 against seed 5. */
class MyMatchesPageTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->member();
        $this->championship([1 => $this->user]);
        $this->tournament->players()->where('seed', 4)->update(['phone' => '0664 1234567']);
        $this->app->forgetScopedInstances();
    }

    private function cards(string $html): int
    {
        return substr_count($html, '<article class="ds-card');
    }

    #[Test]
    public function participant_sees_own_matches_only(): void
    {
        $html = $this->actingAs($this->user)->get('/tournament/mine')->assertOk()->getContent();

        $this->assertSame(2, $this->cards($html));
        $this->assertStringContainsString('gegen Demo Spieler 04', $html);
        $this->assertStringContainsString('gegen Demo Spieler 05', $html);
        $this->assertStringNotContainsString('Demo Spieler 02', $html);
        $this->assertStringContainsString('Demo Spieler 01', $html);
        // own ITN, group and competition; no ITN of the opponents
        $this->assertStringContainsString('ITN 4,1 · Gruppe A · Gruppenphase', $html);
        $this->assertStringNotContainsString('ITN 4,4', $html);
        $this->assertStringContainsString('href="'.e(WhatsApp::url('0664 1234567', 'Hallo Demo! Wann passt Dir unser Vereinsmeisterschafts-Spiel in Gruppe A?')).'"', $html);
    }

    #[Test]
    public function results_show_win_loss_and_confirmation(): void
    {
        $this->play('A-0', 'a'); // seed 1 (position 1) wins against seed 4
        $this->play('A-1', 'b', ['retired' => 'ret_a', 'confirmed_uid' => 99, 'confirmed' => '2027-05-04 10:00:00']);

        $html = $this->actingAs($this->user)->get('/tournament/mine')->assertOk()->getContent();

        $this->assertStringContainsString('Sieg 6:4 6:3', $html);
        $this->assertStringContainsString('nicht bestätigt', $html);
        $this->assertStringContainsString('Niederlage 4:6 3:6 (w.o.)', $html);
        $this->assertStringContainsString('ds-badge--good', $html);
        $this->assertStringContainsString('ds-badge--problem', $html);
        $this->assertSame(2, substr_count($html, 'Ergebnis ansehen'));
        $this->assertMatchesRegularExpression('#Siege</p>\s*<p class="ds-kpi__value">1:1</p>#', $html);
    }

    #[Test]
    public function simulated_player_sees_that_players_matches(): void
    {
        $admin = $this->admin();
        $this->app->make(TestModeSession::class)->simulate($admin, 'player', $this->tpid[2]);

        $html = $this->actingAs($admin)->get('/tournament/mine')->assertOk()->getContent();

        $this->assertStringContainsString('Ansicht von Demo Spieler 02', $html);
        $this->assertSame(2, $this->cards($html));
        $this->assertStringContainsString('gegen Demo Spieler 03', $html);
        $this->assertStringContainsString('gegen Demo Spieler 06', $html);
        // read only in a real tournament: no entry buttons
        $this->assertStringNotContainsString('Termin eintragen', $html);
    }

    #[Test]
    public function member_sees_the_not_participating_hint(): void
    {
        $html = $this->actingAs($this->member())->get('/tournament/mine')->assertOk()->getContent();

        $this->assertStringContainsString('Du bist nicht als Teilnehmer der Vereinsmeisterschaft gemeldet.', $html);
        $this->assertSame(0, $this->cards($html));
    }

    #[Test]
    public function next_match_is_highlighted(): void
    {
        $this->schedule('A-0', '2027-05-10', '18:00:00');

        $html = $this->actingAs($this->user)->get('/tournament/mine')->assertOk()->getContent();

        // matches without a date come first: A-1 (against seed 5) is the next match
        $this->assertSame(1, substr_count($html, 'ds-card--highlight'));
        $this->assertMatchesRegularExpression('#ds-card--highlight.*?gegen Demo Spieler 05.*?</article>#s', $html);
        $this->assertStringContainsString('Dein nächstes Spiel', $html);
    }
}
