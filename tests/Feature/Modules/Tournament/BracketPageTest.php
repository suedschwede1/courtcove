<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\GenerationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

/**
 * Small format KO "KO-Runde" (4 lines): line 1 seed 1, line 4 seed 2, lines 2 and 3 from the pool "w"
 * (codes W3.., group winners W3-W4, best runners-up W5-W6). Matches KO-R1-1 (lines 1/2), KO-R1-2 (3/4),
 * KO-R2-1 (final).
 */
class BracketPageTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    private function line(int $no): TournamentLine
    {
        return TournamentLine::query()->where('tid', $this->tournament->tid)->where('phase', 'KO')->where('line', $no)->firstOrFail();
    }

    #[Test]
    public function unresolved_lines_show_placeholders(): void
    {
        $this->championship();
        $this->line(2)->update(['placeholder' => 'W3']);

        $html = $this->actingAs($this->member())->get('/tournament/bracket')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#title="Aufsteiger 3 aus A–B: der beste Gruppensieger"[^>]*>W3</span>#u', $html);
        $this->assertMatchesRegularExpression('#title="Setznummer 1"[^>]*>S1</span>#', $html);
        $this->assertStringContainsString('gesetzt 1', $html);
        // line 3 is neither drawn nor resolved
        $this->assertStringContainsString('title="Diese Linie ist noch nicht ausgelost – die Turnierleitung muss die Auslosung anwenden."', $html);
        // rounds as columns with the format's round names
        $this->assertStringContainsString('id="runde-1"', $html);
        $this->assertStringContainsString('id="runde-2"', $html);
        $this->assertStringContainsString('4er-Raster · 4 Spieler · 2 gesetzt · 0 Freilose', $html);
        $this->assertStringContainsString('Wer kommt auf welche Linie?', $html);
        $this->assertStringContainsString('die 2 besten Gruppenzweiten aus A–B', $html);
    }

    #[Test]
    public function resolved_lines_show_players_and_winners_advance(): void
    {
        $this->championship();
        foreach ([1 => 1, 2 => 4, 3 => 2, 4 => 3] as $line => $seed) {
            $this->line($line)->update(['tpid' => $this->tpid[$seed]]);
        }
        $semi = $this->matchRow('KO-R1-1');
        $semi->update(['tpid_a' => $this->tpid[1], 'tpid_b' => $this->tpid[4]]);
        $this->play('KO-R1-1', 'b');
        $this->matchRow('KO-R2-1')->update(['tpid_a' => $this->tpid[4]]);
        $this->app->forgetScopedInstances();

        $html = $this->actingAs($this->member())->get('/tournament/bracket/KO')->assertOk()->getContent();

        preg_match('#id="runde-1".*?id="runde-2"#s', $html, $round1);
        preg_match('#id="runde-2".*#s', $html, $round2);
        $this->assertMatchesRegularExpression('#font-bold[^"]*"\s*>Demo Spieler 04</span>#', $round1[0]);
        $this->assertStringContainsString('Demo Spieler 01', $round1[0]);
        $this->assertStringContainsString('6 6', $round1[0]);
        $this->assertStringContainsString('Demo Spieler 04', $round2[0]);
        $this->assertStringContainsString('noch offen', $round2[0]);
    }

    #[Test]
    public function bye_line_shows_freilos(): void
    {
        $this->championship();
        $this->line(2)->update(['kind' => 'bye']);

        $html = $this->actingAs($this->member())->get('/tournament/bracket')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#title="Diese Linie bleibt leer – der Gegner steigt ohne Spiel in die nächste Runde auf."[^>]*>Freilos</span>#u', $html);
    }

    #[Test]
    public function unknown_phase_falls_back_like_zf2(): void
    {
        $this->championship();
        $user = $this->member();

        $this->actingAs($user)->get('/tournament/bracket/KO_X')->assertOk()->assertSee('KO-Runde')->assertSee('id="runde-1"', false);
        $this->actingAs($user)->get('/tournament/bracket/KO1')->assertNotFound();
    }

    #[Test]
    public function phase_tabs_list_bracket_phases(): void
    {
        $t = Tournament::factory()->active()->create();
        $heads = [1 => 'A', 2 => 'B', 3 => 'C', 11 => 'D', 12 => 'E', 13 => 'F', 14 => 'G'];
        for ($seed = 1; $seed <= 24; $seed++) {
            TournamentPlayer::factory()->create(['tid' => $t->tid, 'seed' => $seed] + (isset($heads[$seed]) ? ['grp' => $heads[$seed], 'grp_pos' => 1] : []));
        }
        $this->app->make(GenerationService::class)->generate((int) $t->tid);
        $this->app->forgetScopedInstances();

        $html = $this->actingAs($this->member())->get('/tournament/bracket/KO_B')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('tournament.bracket', ['phase' => 'KO_A']), '#').'"[^>]*>KO-Bewerb A</a>#', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('tournament.bracket', ['phase' => 'KO_B']), '#').'"[^>]*aria-current="true"[^>]*>KO-Bewerb B</a>#', $html);
        // KO_B: 8 lines, 3 rounds, REST codes
        $this->assertStringContainsString('id="runde-3"', $html);
        $this->assertStringContainsString('die weiteren Nichtaufgestiegenen aus D–G – die Zahl ist der Rang im Restfeld', $html);
    }

    #[Test]
    public function no_bracket_phase_shows_a_notice(): void
    {
        $format = json_decode((string) file_get_contents(base_path('tests/Fixtures/tournament/formats/small-two-groups.json')), true);
        $format['phases'] = [$format['phases'][0]];
        Tournament::factory()->active()->withFormat($format)->create();

        $this->actingAs($this->member())->get('/tournament/bracket')->assertOk()->assertSee('Das KO-Raster ist noch nicht ausgelost.');
    }
}
