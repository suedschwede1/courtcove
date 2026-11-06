<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->status('admin')->create();
    }

    #[Test]
    public function guest_is_sent_to_login_with_the_admin_flash(): void
    {
        $response = $this->get('/pyramide/admin');

        $response->assertRedirect()
            ->assertSessionHas('error', 'Für die Verwaltung der Forderungspyramide musst du angemeldet sein.');
        $this->assertStringContainsString('login', (string) $response->headers->get('Location'));

    }

    #[Test]
    public function grant_holder_without_global_right_gets_403(): void
    {
        $ladder = PyramidLadder::factory()->create();
        $holder = User::factory()->create();
        PyramidGrant::query()->create(['ladder_id' => $ladder->id, 'uid' => $holder->uid, 'role' => 'admin', 'created' => '2027-01-01 00:00:00']);

        $response = $this->actingAs($holder)->get('/pyramide/admin');

        $response->assertForbidden();
        $this->assertSame('Für die Verwaltung der Pyramide fehlt dir die Berechtigung.', $response->exception->getMessage());
        $this->actingAs($holder)->post('/pyramide/admin/create', ['title' => 'Neu'])->assertForbidden();
        $this->assertSame(1, PyramidLadder::query()->count());
    }

    #[Test]
    public function admin_sees_ladders_by_season(): void
    {
        $older = PyramidLadder::factory()->create(['title' => 'Herren Altsaison', 'season' => 2026, 'category' => 'Herren']);
        $newer = PyramidLadder::factory()->withRanking(3)->create(['title' => 'Damen Neu', 'season' => 2027, 'category' => null, 'status' => 'running']);
        PyramidPlayer::factory()->create(['ladder_id' => $newer->id, 'label' => 'Wartend', 'position' => null, 'status' => 'waiting', 'uid' => null, 'joined' => '2027-01-01 00:00:00']);

        $response = $this->actingAs($this->admin())->get('/pyramide/admin');

        $response->assertOk()
            ->assertSee('Saison 2027')
            ->assertSee('Saison 2026')
            ->assertSeeInOrder(['Saison 2027', 'Damen Neu', 'Saison 2026', 'Herren Altsaison'])
            ->assertSee('3 Teilnehmer')
            ->assertSee('0 Teilnehmer')
            ->assertSee('pyramide/admin/ladder/'.$older->id, false)
            ->assertSee('Neue Rangliste')
            ->assertSee('Forderungspyramide verwalten')
            ->assertSee('name="season" id="pyramid-admin-season" value="'.date('Y').'"', false);
    }

    #[Test]
    public function overdue_challenges_are_listed_to_do_and_open_ones_are_not(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(4)->create(['title' => 'Aktive Liga']);
        $players = PyramidPlayer::query()->where('ladder_id', $ladder->id)->orderBy('position')->get();

        $overdue = PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id,
            'challenger_id' => $players[3]->id,
            'opponent_id' => $players[1]->id,
            'challenger_pos' => 4,
            'opponent_pos' => 2,
            'status' => 'open',
            'deadline' => now()->subDays(2)->format('Y-m-d 23:59:59'),
        ]);
        PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id,
            'challenger_id' => $players[2]->id,
            'opponent_id' => $players[0]->id,
            'challenger_pos' => 3,
            'opponent_pos' => 1,
            'status' => 'open',
            'deadline' => now()->format('Y-m-d 23:59:59'),
        ]);

        $response = $this->actingAs($this->admin())->get('/pyramide/admin');

        $response->assertOk()
            ->assertSee('Zu erledigen')
            ->assertSee('Überfällige Forderung')
            ->assertSee('Spieler 4 fordert Spieler 2')
            ->assertSee(now()->subDays(2)->format('d.m.Y'))
            ->assertSee('pyramide/forderung/'.$overdue->id, false)
            ->assertDontSee('Spieler 3 fordert Spieler 1');
    }

    #[Test]
    public function empty_state_without_ladders_and_without_todos(): void
    {
        $this->actingAs($this->admin())->get('/pyramide/admin')
            ->assertOk()
            ->assertSee('Es ist noch keine Rangliste angelegt.')
            ->assertDontSee('Zu erledigen');
    }

    #[Test]
    public function create_starts_in_setup(): void
    {
        $response = $this->actingAs($this->admin())->post('/pyramide/admin/create', [
            'title' => '  Herren <b>2027</b>  ',
            'season' => '2027',
            'category' => 'Herren',
        ]);

        $ladder = PyramidLadder::query()->firstOrFail();
        $response->assertRedirect('/pyramide/admin/ladder/'.$ladder->id);
        $this->assertSame('Herren <b>2027</b>', $ladder->title);
        $this->assertSame(2027, (int) $ladder->season);
        $this->assertSame('Herren', $ladder->category);
        $this->assertSame('setup', $ladder->status);
        $this->assertEqualsWithDelta(10.0, (float) $ladder->itn_default, 0.0001);
        $this->assertSame(7, (int) $ladder->days_to_play);
        $this->assertSame(3, (int) $ladder->winner_protected_days);
        $this->assertSame(7, (int) $ladder->loser_cooldown_days);
        $this->assertSame(0, (int) $ladder->is_default);
    }

    #[Test]
    public function create_without_a_title_returns_to_the_overview_with_the_error(): void
    {
        $this->actingAs($this->admin())->post('/pyramide/admin/create', ['title' => '   ', 'season' => '2027'])
            ->assertRedirect('/pyramide/admin')
            ->assertSessionHas('error', 'Bitte einen Titel angeben.');

        $this->assertSame(0, PyramidLadder::query()->count());
    }

    #[Test]
    #[DataProvider('invalidSeasons')]
    public function create_rejects_a_season_outside_2000_to_2100(string $season): void
    {
        $this->actingAs($this->admin())->post('/pyramide/admin/create', ['title' => 'Herren', 'season' => $season])
            ->assertRedirect('/pyramide/admin')
            ->assertSessionHas('error', 'Die Saison muss zwischen 2000 und 2100 liegen.');

        $this->assertSame(0, PyramidLadder::query()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSeasons(): array
    {
        return ['zero' => ['0'], 'negative' => ['-5'], 'too early' => ['1999'], 'too late' => ['2101'], 'text' => ['abc'], 'huge' => ['99999999']];
    }

    #[Test]
    public function create_accepts_the_season_bounds_and_defaults_a_blank_season_to_this_year(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/pyramide/admin/create', ['title' => 'Alt', 'season' => '2000'])->assertRedirectContains('/ladder/');
        $this->actingAs($admin)->post('/pyramide/admin/create', ['title' => 'Neu', 'season' => '2100'])->assertRedirectContains('/ladder/');
        $this->actingAs($admin)->post('/pyramide/admin/create', ['title' => 'Leer', 'season' => ''])->assertRedirectContains('/ladder/');

        $this->assertSame([2000, 2100, (int) date('Y')], PyramidLadder::query()->orderBy('id')->pluck('season')->map(fn ($s): int => (int) $s)->all());
    }

    #[Test]
    public function overview_counts_the_ranking_of_all_ladders_with_one_query(): void
    {
        PyramidLadder::factory()->withRanking(3)->create(['title' => 'Liga A']);
        PyramidLadder::factory()->withRanking(2)->create(['title' => 'Liga B']);
        $admin = $this->admin();
        $this->actingAs($admin)->get('/pyramide/admin')->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get('/pyramide/admin')->assertOk()->assertSee('3 Teilnehmer')->assertSee('2 Teilnehmer');
        // The sidebar's own lookup of the viewer's participant row ("Mein Profil") is not part of the overview.
        $playerQueries = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'bs_pyramid_players')
            && ! str_contains($q['query'], '"uid" = ?'))->count();
        DB::disableQueryLog();

        $this->assertSame(1, $playerQueries);
    }

    #[Test]
    public function get_create_redirects_to_the_overview(): void
    {
        $this->actingAs($this->admin())->get('/pyramide/admin/create')->assertRedirect('/pyramide/admin');
        $this->assertSame(0, PyramidLadder::query()->count());
    }

    #[Test]
    public function sidebar_links_the_module_for_admins(): void
    {
        $link = '#<a href="[^"]*/pyramide/admin"[^>]*>\s*Forderungspyramide\s*</a>#';

        $this->assertMatchesRegularExpression($link, $this->actingAs($this->admin())->get('/admin')->assertOk()->getContent());
        // In the tournament area the module's own entry "Verwaltung" leads there and is the current page.
        $this->assertMatchesRegularExpression('#<a href="[^"]*/pyramide/admin" class="ds-admin-sidebar__link" aria-current="page">Verwaltung</a>#',
            $this->actingAs($this->admin())->get('/pyramide/admin')->assertOk()->getContent());
    }

    #[Test]
    public function sidebar_hides_the_module_without_the_global_right(): void
    {
        $this->actingAs(User::factory()->status('assist')->create())->get('/admin')
            ->assertDontSee('/pyramide/admin"', false);
    }
}
