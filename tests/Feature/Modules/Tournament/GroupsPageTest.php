<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Draw\Models\Draw;
use App\Modules\Tournament\Services\TournamentManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

class GroupsPageTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    /** Every player of group A wins exactly one match 6:4 6:3 (circular): a complete tie in all criteria. */
    private function circularTie(): void
    {
        $p = [$this->tpid[1], $this->tpid[4], $this->tpid[5]];
        $beats = [$p[0] => $p[1], $p[1] => $p[2], $p[2] => $p[0]];

        foreach (['A-0', 'A-1', 'A-2'] as $mkey) {
            $m = $this->matchRow($mkey);
            $this->play($mkey, ($beats[(int) $m->tpid_a] ?? null) === (int) $m->tpid_b ? 'a' : 'b');
        }
    }

    /** @return list<string> labels in the order of the table rows */
    private function rowLabels(string $html): array
    {
        preg_match('#<table.*?</table>#s', $html, $table);
        preg_match_all('#<span class="font-bold text-ink">(Demo Spieler \d+)</span>#', $table[0] ?? '', $m);

        return $m[1];
    }

    #[Test]
    public function tables_follow_the_standings_service(): void
    {
        $this->championship();
        $this->play('A-1', 'b');
        $this->play('A-2', 'a');

        $html = $this->actingAs($this->member())->get('/tournament/groups/A')->assertOk()->getContent();
        $expected = array_map(fn (array $row): string => (string) $row['label'], $this->app->make(TournamentManager::class)->table((int) $this->tournament->tid, 'A'));

        $this->assertSame($expected, $this->rowLabels($html));
        $this->assertStringContainsString('Spiele der Gruppe', $html);
        $this->assertStringContainsString('2 gespielt, 0 geplant, 1 ohne Termin', $html);
        $this->assertStringContainsString('Reihung: Punkte, direktes Spiel, Satzdifferenz, Gamedifferenz, Los.', $html);
        // members see no ITN of other players
        $this->assertStringNotContainsString('ITN', $html);
    }

    #[Test]
    public function lot_tie_shows_the_lot_badge_and_explanation(): void
    {
        $this->championship();
        $this->circularTie();

        $html = $this->actingAs($this->member())->get('/tournament/groups/A')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, '>Los</span>'));
        $this->assertStringContainsString('gleichauf in allen Kriterien – hier muss gelost werden', $html);
        $this->assertStringContainsString('ds-badge--attention', $html);
    }

    #[Test]
    public function derived_lot_order_is_applied(): void
    {
        $this->championship();
        $this->circularTie();
        Draw::factory()->executed()->create(['tid' => $this->tournament->tid]);
        $this->app->forgetScopedInstances();

        $html = $this->actingAs($this->member())->get('/tournament/groups/A')->assertOk()->getContent();
        $table = $this->app->make(TournamentManager::class)->table((int) $this->tournament->tid, 'A');

        $this->assertFalse(collect($table)->contains(fn (array $r): bool => ! empty($r['needs_lot'])));
        $this->assertSame(array_map(fn (array $r): string => (string) $r['label'], $table), $this->rowLabels($html));
        $this->assertStringNotContainsString('>Los</span>', $html);
        $this->assertStringContainsString('gleichauf in allen Kriterien – durch das Los entschieden', $html);
    }

    #[Test]
    public function group_filter_limits_to_one_group(): void
    {
        $this->championship();

        $html = $this->actingAs($this->member())->get('/tournament/groups/B')->assertOk()->getContent();

        foreach ([2, 3, 6] as $seed) {
            $this->assertStringContainsString(sprintf('Demo Spieler %02d', $seed), $html);
        }
        foreach ([1, 4, 5] as $seed) {
            $this->assertStringNotContainsString(sprintf('Demo Spieler %02d', $seed), $html);
        }
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('tournament.groups', ['group' => 'B']), '#').'"[^>]*aria-current="true"#', $html);

        // without a letter: the first group of the format (ZF2)
        $this->assertStringContainsString('Demo Spieler 01', (string) $this->get('/tournament/groups')->assertOk()->getContent());
    }

    #[Test]
    public function unknown_group_letter_404s(): void
    {
        $this->championship();
        $user = $this->member();

        $this->actingAs($user)->get('/tournament/groups/a')->assertNotFound();
        $this->actingAs($user)->get('/tournament/groups/AB')->assertNotFound();
        // an unknown upper-case letter falls back to the first group, as in ZF2
        $this->actingAs($user)->get('/tournament/groups/Z')->assertOk()->assertSee('Demo Spieler 01');
    }

    #[Test]
    public function own_row_is_highlighted_for_participants(): void
    {
        $user = $this->member();
        $this->championship([4 => $user]);

        $html = $this->actingAs($user)->get('/tournament/groups/A')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<tr class="bg-clay-soft">.*?Demo Spieler 04.*?>du</span>#s', $html);
        $this->assertSame(1, substr_count($html, 'bg-clay-soft'));
        // the own ITN is not shown to the player either on this page (only admins see ITN)
        $this->assertStringNotContainsString('ITN', $html);
    }

    #[Test]
    public function admins_see_the_itn_and_complete_groups_mark_the_winner(): void
    {
        $this->championship();
        $this->play('A-1');
        $this->play('A-2');
        $this->play('A-0');

        $html = $this->actingAs($this->admin())->get('/tournament/groups/A')->assertOk()->getContent();

        $this->assertStringContainsString('ITN 4,1', $html);
        $this->assertStringContainsString('bg-court-soft', $html);
    }
}
