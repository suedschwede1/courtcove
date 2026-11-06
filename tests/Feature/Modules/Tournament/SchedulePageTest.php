<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

/**
 * Group matches A-0..A-2, B-0..B-2: A-0 played, A-1 and B-0 scheduled, A-2, B-1, B-2 without a date.
 * The viewer plays seed 1 (group A position 1: A-0 and A-1). KO matches have no players yet and stay out.
 */
class SchedulePageTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->member();
        $this->championship([1 => $this->user]);
        $this->play('A-0');
        $this->schedule('A-1', '2027-05-10', '18:00:00', '2');
        $this->schedule('B-0', '2027-05-12', '17:00:00');
    }

    private function cards(string $html): int
    {
        return substr_count($html, '<article class="ds-card');
    }

    /** @return list<string> context labels of the cards in page order */
    private function contexts(string $html): array
    {
        preg_match_all('#<p class="text-label uppercase text-ink-3">([^<]+)</p>#', $html, $m);

        return array_map('html_entity_decode', $m[1]);
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function filters(): array
    {
        return [
            'alle' => ['alle', 6],
            'offen' => ['offen', 5],
            'geplant' => ['geplant', 2],
            'ohnetermin' => ['ohnetermin', 3],
            'gespielt' => ['gespielt', 1],
            'meine' => ['meine', 2],
        ];
    }

    #[Test]
    #[DataProvider('filters')]
    public function each_filter_slug_counts_like_zf2(string $slug, int $count): void
    {
        $html = $this->actingAs($this->user)->get('/tournament/schedule/'.$slug)->assertOk()->getContent();

        $this->assertSame($count, $this->cards($html), $slug);
        $this->assertStringContainsString($count.' von 6 Partien', $html);
        $this->assertStringContainsString('1 gespielt, 2 geplant, 3 ohne Termin', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('tournament.schedule', ['filter' => $slug]), '#').'"[^>]*aria-current="true"#', $html);
    }

    #[Test]
    public function without_a_slug_all_matches_show_newest_date_first_then_undated_in_order(): void
    {
        $html = $this->actingAs($this->user)->get('/tournament/schedule')->assertOk()->getContent();

        $this->assertSame(6, $this->cards($html));
        $this->assertSame([
            'Gruppe B · Spiel 1', 'Gruppe A · Spiel 2', 'Gruppe A · Spiel 1',
            'Gruppe A · Spiel 3', 'Gruppe B · Spiel 2', 'Gruppe B · Spiel 3',
        ], $this->contexts($html));
    }

    #[Test]
    public function meine_is_offered_only_to_participants(): void
    {
        $this->assertStringContainsString('>Meine</a>', (string) $this->actingAs($this->user)->get('/tournament/schedule')->getContent());
        $this->assertStringNotContainsString('>Meine</a>', (string) $this->actingAs($this->member())->get('/tournament/schedule')->getContent());
        // a member asking for "meine" gets nothing (no own player), as in ZF2
        $this->assertSame(0, $this->cards((string) $this->actingAs($this->member())->get('/tournament/schedule/meine')->getContent()));
    }

    #[Test]
    public function own_matches_are_tinted_like_zf2(): void
    {
        $html = (string) $this->actingAs($this->user)->get('/tournament/schedule')->getContent();

        // A-0 and A-1 are the viewer's matches
        $this->assertSame(2, substr_count($html, 'data-mine="1"'));
        $this->assertMatchesRegularExpression('#<article class="ds-card[^"]*bg-clay-soft[^"]*"[^>]*data-mine="1"#', $html);
        $this->assertSame(0, substr_count((string) $this->actingAs($this->member())->get('/tournament/schedule')->getContent(), 'data-mine="1"'));
    }

    #[Test]
    public function group_query_combines_with_filter(): void
    {
        $html = $this->actingAs($this->user)->get('/tournament/schedule/offen?grp=A')->assertOk()->getContent();

        $this->assertSame(2, $this->cards($html));
        $this->assertStringContainsString('2 von 6 Partien · Gruppe A', $html);
        // the status chips keep the group, the group chips keep the status
        $this->assertStringContainsString('href="'.route('tournament.schedule', ['filter' => 'gespielt']).'?grp=A"', $html);
        $this->assertStringContainsString('href="'.route('tournament.schedule', ['filter' => 'offen']).'?grp=B"', $html);
        $this->assertStringContainsString('href="'.route('tournament.schedule', ['filter' => 'offen']).'"', $html);

        // an unknown area is ignored
        $this->assertSame(5, $this->cards((string) $this->get('/tournament/schedule/offen?grp=Q')->getContent()));
    }

    #[Test]
    public function unknown_filter_behaves_like_zf2(): void
    {
        $user = $this->user;

        $html = $this->actingAs($user)->get('/tournament/schedule/irgendwas')->assertOk()->getContent();

        $this->assertSame(6, $this->cards($html));
        preg_match('#<nav aria-label="Status".*?</nav>#s', $html, $status);
        $this->assertStringNotContainsString('aria-current="true"', $status[0] ?? 'missing');
        $this->assertStringContainsString('>Alle</a>', $status[0] ?? '');
        $this->actingAs($user)->get('/tournament/schedule/Offen')->assertNotFound();
    }

    #[Test]
    public function match_cards_link_to_the_match_page_only_when_writable(): void
    {
        $link = 'href="'.url('/tournament/match/');

        // member: read only
        $this->assertSame(0, substr_count((string) $this->actingAs($this->member())->get('/tournament/schedule')->getContent(), $link));

        // participant: his unplayed match A-1 only (A-0 is played)
        $html = (string) $this->actingAs($this->user)->get('/tournament/schedule')->getContent();
        $this->assertSame(1, substr_count($html, $link));
        $this->assertStringContainsString('href="'.route('tournament.match', ['tmid' => $this->matchRow('A-1')->tmid]).'"', $html);
        $this->assertStringContainsString('Ergebnis eintragen', $html);

        // admin: every unplayed match
        $this->assertSame(5, substr_count((string) $this->actingAs($this->admin())->get('/tournament/schedule')->getContent(), $link));
    }
}
