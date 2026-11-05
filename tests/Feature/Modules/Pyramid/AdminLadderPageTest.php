<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Models\UserMeta;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminLadderPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->status('admin')->create();
    }

    private function grantTo(User $user, PyramidLadder $ladder): void
    {
        PyramidGrant::query()->create(['ladder_id' => $ladder->id, 'uid' => $user->uid, 'role' => 'admin', 'created' => '2027-01-01 00:00:00']);
    }

    #[Test]
    public function grant_holder_opens_only_the_granted_ladder(): void
    {
        $granted = PyramidLadder::factory()->withRanking(3)->create(['title' => 'Freigegebene Liga']);
        $other = PyramidLadder::factory()->withRanking(3)->create(['title' => 'Fremde Liga']);
        $holder = User::factory()->create();
        $this->grantTo($holder, $granted);

        $this->actingAs($holder)->get('/pyramide/admin/ladder/'.$granted->id)
            ->assertOk()
            ->assertSee('Freigegebene Liga · Saison 2027');
        $this->actingAs($holder)->get('/pyramide/admin/ladder/'.$other->id)->assertForbidden();
        $this->actingAs($holder)->getJson('/pyramide/admin/ladder/'.$other->id.'/mitglieder?q=ab')->assertForbidden();
        $this->actingAs($holder)->getJson('/pyramide/admin/ladder/'.$granted->id.'/mitglieder?q=ab')->assertOk();
    }

    #[Test]
    public function guest_is_sent_to_login_and_unknown_ladder_is_404(): void
    {
        $ladder = PyramidLadder::factory()->create();

        $this->get('/pyramide/admin/ladder/'.$ladder->id)
            ->assertRedirect()
            ->assertSessionHas('error', 'Für die Verwaltung der Forderungspyramide musst du angemeldet sein.');
        $this->actingAs($this->admin())->get('/pyramide/admin/ladder/9999')->assertNotFound();
    }

    #[Test]
    public function page_does_not_embed_member_emails(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(3)->create();
        $users = User::factory()->count(30)->create();

        foreach ($users as $index => $user) {
            UserMeta::query()->create(['uid' => $user->uid, 'key' => 'phone', 'value' => '+43 000 000 '.str_pad((string) $index, 4, '0', STR_PAD_LEFT)]);
        }

        $html = $this->actingAs($this->admin())->get('/pyramide/admin/ladder/'.$ladder->id)->assertOk()->getContent();

        foreach ($users as $index => $user) {
            $this->assertStringNotContainsString((string) $user->email, $html);
            $this->assertStringNotContainsString('+43 000 000 '.str_pad((string) $index, 4, '0', STR_PAD_LEFT), $html);
        }

        $this->assertStringContainsString('/mitglieder', $html);
    }

    #[Test]
    public function players_are_filtered_and_paginated(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(15)->create();
        PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 12)->update(['status' => 'paused']);
        $admin = $this->admin();
        $base = '/pyramide/admin/ladder/'.$ladder->id;

        $first = $this->actingAs($admin)->get($base)->assertOk()->assertSee('Seite 1 von 2 (15 Teilnehmer)');
        $this->assertSame(10, substr_count($first->getContent(), 'data-player-row'));

        $second = $this->actingAs($admin)->get($base.'?page=2')->assertOk()->assertSee('Seite 2 von 2 (15 Teilnehmer)');
        $this->assertSame(5, substr_count($second->getContent(), 'data-player-row'));

        $clamped = $this->actingAs($admin)->get($base.'?page=9')->assertOk()->assertSee('Seite 2 von 2 (15 Teilnehmer)');
        $this->assertSame(5, substr_count($clamped->getContent(), 'data-player-row'));

        $named = $this->actingAs($admin)->get($base.'?q=spieler+1')->assertOk();
        $this->assertSame(7, $this->rowCount($named->getContent()));
        $named->assertSee('Seite 1 von 1 (7 Teilnehmer)');

        $paused = $this->actingAs($admin)->get($base.'?status=paused')->assertOk()
            ->assertSee('(1 Teilnehmer)')
            ->assertSee('Spieler 12')
            ->assertSee('Aktivieren')
            ->assertDontSee('Spieler 11');
        $this->assertSame(1, $this->rowCount($paused->getContent()));

        $this->actingAs($admin)->get($base.'?q=gibtesnicht')->assertOk()->assertSee('Keine Teilnehmer gefunden.');
    }

    private function rowCount(string $html): int
    {
        return substr_count($html, 'data-player-row');
    }

    #[Test]
    public function page_without_a_ranking_offers_the_seed_and_lists_the_waiting(): void
    {
        $ladder = PyramidLadder::factory()->setup()->create();
        PyramidPlayer::factory()->waiting()->create(['ladder_id' => $ladder->id, 'label' => 'Wartend <b>Eins</b>', 'itn' => '7.4', 'gender' => 'f', 'age' => 34]);
        PyramidPlayer::factory()->entering()->create(['ladder_id' => $ladder->id, 'label' => 'Frei Gegeben']);

        $this->actingAs($this->admin())->get('/pyramide/admin/ladder/'.$ladder->id)
            ->assertOk()
            ->assertSee('Startfeld nach ITN reihen')
            ->assertSee('Wartend &lt;b&gt;Eins&lt;/b&gt;', false)
            ->assertDontSee('Wartend <b>Eins</b>', false)
            ->assertSee('Unten aufnehmen')
            ->assertSee('Zum Einfordern freigeben')
            ->assertSee('Freigabe zurücknehmen')
            ->assertSee('7,4');
    }

    #[Test]
    public function overdue_and_problems_are_listed(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(4)->create(['title' => 'Aktive Liga']);
        $players = PyramidPlayer::query()->where('ladder_id', $ladder->id)->orderBy('position')->get();
        PyramidPlayer::factory()->create(['ladder_id' => $ladder->id, 'label' => 'Ohne Platz', 'status' => 'active', 'position' => null]);

        $overdue = PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id, 'challenger_id' => $players[3]->id, 'opponent_id' => $players[1]->id,
            'challenger_pos' => 4, 'opponent_pos' => 2, 'status' => 'open',
            'deadline' => now()->subDays(2)->format('Y-m-d 23:59:59'),
        ]);
        PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id, 'challenger_id' => $players[2]->id, 'opponent_id' => $players[0]->id,
            'challenger_pos' => 3, 'opponent_pos' => 1, 'status' => 'open',
            'deadline' => now()->format('Y-m-d 23:59:59'),
        ]);
        PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id, 'challenger_id' => $players[2]->id, 'opponent_id' => $players[1]->id,
            'challenger_pos' => 3, 'opponent_pos' => 2, 'status' => 'played', 'winner_id' => $players[2]->id,
            'score' => '6:4 6:3', 'resolved' => 'kaputt', 'deadline' => null,
        ]);

        $response = $this->actingAs($this->admin())->get('/pyramide/admin/ladder/'.$ladder->id)->assertOk();

        $response->assertSee('Überfällige Forderungen (1)')
            ->assertSee('Spieler 4 fordert Spieler 2')
            ->assertSee('Frist war '.now()->subDays(2)->format('d.m.Y'))
            ->assertDontSee('Spieler 3 fordert Spieler 1')
            ->assertSee('pyramide/admin/challenge/'.$overdue->id.'/override', false)
            ->assertSee('Frist verlängern bis')
            ->assertSee('Geforderter nicht angetreten (Regel 5.1)')
            ->assertSee('Herausforderer nicht angetreten')
            ->assertSee('ohne Wertung verfallen')
            ->assertSee('name="new_deadline"', false)
            ->assertSee('value="'.now()->addDays(7)->format('Y-m-d').'"', false)
            ->assertSee('Begründung (Pflicht)')
            ->assertSee('Rangliste prüfen')
            ->assertSee('1 aktive oder pausierte Teilnehmer ohne Position')
            ->assertSee('Verbuchte Forderungen richtigstellen')
            ->assertSee('6:4 6:3');
    }

    #[Test]
    public function without_overdue_or_problems_those_cards_stay_away(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(3)->create();

        $this->actingAs($this->admin())->get('/pyramide/admin/ladder/'.$ladder->id)
            ->assertOk()
            ->assertDontSee('Überfällige Forderungen')
            ->assertDontSee('Rangliste prüfen')
            ->assertDontSee('Verbuchte Forderungen richtigstellen')
            ->assertDontSee('Teilnehmer übernehmen')
            ->assertSee('Rangliste neu durchnummerieren')
            ->assertSee('Einstellungen')
            ->assertSee('Verwaltungsrechte')
            ->assertSee('Saison abschließen')
            ->assertSee('Niemand wartet auf Aufnahme.');
    }

    #[Test]
    public function copy_card_and_ladder_chips_appear_with_other_ladders(): void
    {
        $ladder = PyramidLadder::factory()->create(['title' => 'Ziel', 'category' => 'Herren', 'season' => 2027]);
        PyramidLadder::factory()->closed()->create(['title' => 'Quelle Alt', 'category' => 'Damen', 'season' => 2026]);

        $this->actingAs($this->admin())->get('/pyramide/admin/ladder/'.$ladder->id)
            ->assertOk()
            ->assertSee('Teilnehmer übernehmen')
            ->assertSee('Quelle Alt (2026)')
            ->assertSee('Herren 2027 · Laufend · 0')
            ->assertSee('Damen 2026 · Abgeschlossen · 0')
            ->assertSee('Öffentliche Ansicht')
            ->assertSee('Neue Rangliste');
    }

    #[Test]
    public function ladder_chips_list_only_ladders_the_user_administers(): void
    {
        $a = PyramidLadder::factory()->create(['title' => 'Liga A', 'category' => 'Herren', 'season' => 2027]);
        PyramidLadder::factory()->create(['title' => 'Liga B', 'category' => 'Damen', 'season' => 2026]);
        $holder = User::factory()->create();
        $this->grantTo($holder, $a);

        $this->actingAs($holder)->get('/pyramide/admin/ladder/'.$a->id)
            ->assertOk()
            ->assertDontSee('Damen 2026')
            ->assertDontSee('Liga B');

        $this->actingAs($this->admin())->get('/pyramide/admin/ladder/'.$a->id)
            ->assertOk()
            ->assertSee('Herren 2027 · Laufend · 0')
            ->assertSee('Damen 2026 · Laufend · 0');
    }

    #[Test]
    public function copy_dropdown_lists_only_ladders_the_user_administers(): void
    {
        $target = PyramidLadder::factory()->create(['title' => 'Ziel', 'season' => 2027]);
        $own = PyramidLadder::factory()->create(['title' => 'Eigene Quelle', 'season' => 2026]);
        PyramidLadder::factory()->create(['title' => 'Fremde Quelle', 'season' => 2026]);
        $holder = User::factory()->create();
        $this->grantTo($holder, $target);
        $this->grantTo($holder, $own);

        $this->actingAs($holder)->get('/pyramide/admin/ladder/'.$target->id)
            ->assertOk()
            ->assertSee('Teilnehmer übernehmen')
            ->assertSee('Eigene Quelle (2026)')
            ->assertDontSee('Fremde Quelle (2026)');
    }

    #[Test]
    public function copy_card_is_hidden_when_the_user_administers_no_other_ladder(): void
    {
        $target = PyramidLadder::factory()->create(['title' => 'Ziel']);
        PyramidLadder::factory()->create(['title' => 'Fremde Quelle', 'season' => 2026]);
        $holder = User::factory()->create();
        $this->grantTo($holder, $target);

        $this->actingAs($holder)->get('/pyramide/admin/ladder/'.$target->id)
            ->assertOk()
            ->assertDontSee('Teilnehmer übernehmen')
            ->assertDontSee('Fremde Quelle (2026)');
    }

    #[Test]
    public function stored_values_are_escaped_and_the_edit_form_carries_them(): void
    {
        $ladder = PyramidLadder::factory()->create(['title' => '<script>alert(1)</script>']);
        PyramidPlayer::factory()->create(['ladder_id' => $ladder->id, 'label' => '"><img src=x>', 'position' => 1, 'phone' => '+43 000 000 0001', 'note' => '<i>n</i>']);

        $this->actingAs($this->admin())->get('/pyramide/admin/ladder/'.$ladder->id)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x>', false)
            ->assertDontSee('<i>n</i>', false)
            ->assertSee('pyramide/admin/player/'.PyramidPlayer::query()->firstOrFail()->id.'/update', false)
            ->assertSee('+43 000 000 0001');
    }

    #[Test]
    public function member_search_is_admin_only_and_uncached(): void
    {
        $ladder = PyramidLadder::factory()->create();
        User::factory()->create(['alias' => 'Demo Spieler 07', 'email' => 'demo07@example.test']);
        User::factory()->create(['alias' => 'Anderer Name', 'email' => 'anderer@example.test']);
        $url = '/pyramide/admin/ladder/'.$ladder->id.'/mitglieder';

        $this->actingAs(User::factory()->create())->getJson($url.'?q=demo')->assertForbidden();

        $response = $this->actingAs($this->admin())->getJson($url.'?q=demo')->assertOk();
        $response->assertJsonCount(1)->assertJsonStructure([['uid', 'alias', 'email']])
            ->assertJsonPath('0.alias', 'Demo Spieler 07')
            ->assertJsonPath('0.email', 'demo07@example.test');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->actingAs($this->admin())->getJson($url.'?q=d')->assertOk()->assertExactJson([]);
        $this->actingAs($this->admin())->getJson($url)->assertOk()->assertExactJson([]);
    }

    #[Test]
    public function every_player_has_an_edit_link_that_works_without_javascript(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(3)->create();
        $second = PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 2)->firstOrFail();
        $first = PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 1)->firstOrFail();
        $base = '/pyramide/admin/ladder/'.$ladder->id;

        $closed = $this->actingAs($this->admin())->get($base)->assertOk()->getContent();
        $this->assertStringContainsString($base.'?edit='.$second->id, html_entity_decode($closed));

        $open = $this->actingAs($this->admin())->get($base.'?edit='.$second->id)->assertOk()->getContent();
        // The requested row is rendered open (no x-cloak, so it shows without scripts), the others stay closed.
        $this->assertSame(substr_count($closed, 'x-cloak') - 1, substr_count($open, 'x-cloak'));
        $this->assertMatchesRegularExpression('#id="pyramid-pe-row-'.$second->id.'"(?![^>]*x-cloak)#', $open);
        $this->assertMatchesRegularExpression('#id="pyramid-pe-row-'.$first->id.'"[^>]*x-cloak#', $open);
        // Cancel is a link back to the list for the no-script case.
        $this->assertStringContainsString('href="'.url($base).'"', $open);
    }

    #[Test]
    public function the_edit_control_is_not_part_of_the_status_form(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(2)->create();

        $html = $this->actingAs($this->admin())->get('/pyramide/admin/ladder/'.$ladder->id)->assertOk()->getContent();

        preg_match_all('#<form[^>]*player/\d+/status"[^>]*>.*?</form>#s', $html, $forms);
        $this->assertCount(2, $forms[0]);
        foreach ($forms[0] as $form) {
            $this->assertStringNotContainsString('Bearbeiten', $form);
        }
    }

    #[Test]
    public function member_search_is_rate_limited(): void
    {
        $ladder = PyramidLadder::factory()->create();
        $admin = $this->admin();
        $url = '/pyramide/admin/ladder/'.$ladder->id.'/mitglieder?q=demo';

        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($admin)->getJson($url)->assertOk();
        }

        $this->actingAs($admin)->getJson($url)->assertStatus(429);
    }
}
