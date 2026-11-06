<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Draw\Models\Draw;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\TournamentManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

class PlayerAdminTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    private function partnerOf(int $seed): ?int
    {
        $value = TournamentPlayer::query()->whereKey($this->tpid[$seed])->value('partner_tpid');

        return $value === null ? null : (int) $value;
    }

    #[Test]
    public function partner_link_is_symmetric_and_unlink_clears_both(): void
    {
        $this->championship();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('tournament.admin.partner'), ['tpid_a' => (string) $this->tpid[3], 'tpid_b' => (string) $this->tpid[4]])
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('success', 'Demo Spieler 03 und Demo Spieler 04 werden nicht in dieselbe Gruppe gelost.');
        $this->assertSame($this->tpid[4], $this->partnerOf(3));
        $this->assertSame($this->tpid[3], $this->partnerOf(4));

        // A new couple clears the old one on both sides: no orphaned 4 -> 3 stays behind.
        $this->actingAs($admin)->post(route('tournament.admin.partner'), ['tpid_a' => (string) $this->tpid[3], 'tpid_b' => (string) $this->tpid[5]]);
        $this->assertSame($this->tpid[5], $this->partnerOf(3));
        $this->assertSame($this->tpid[3], $this->partnerOf(5));
        $this->assertNull($this->partnerOf(4));

        // Unlink clears both directions, including an old one-sided reference to the same player.
        TournamentPlayer::query()->whereKey($this->tpid[6])->update(['partner_tpid' => $this->tpid[3]]);
        $this->actingAs($admin)->post(route('tournament.admin.partner'), ['tpid_a' => (string) $this->tpid[3]])
            ->assertSessionHas('success', 'Partnerverweis gelöst.');
        foreach ([3, 5, 6] as $seed) {
            $this->assertNull($this->partnerOf($seed));
        }
    }

    #[Test]
    public function partner_refusals_change_nothing(): void
    {
        $this->championship();
        $admin = $this->admin();
        $foreign = TournamentPlayer::factory()->create(['tid' => Tournament::factory()->create()->tid, 'label' => 'Fremder Spieler']);

        foreach ([
            [['tpid_a' => (string) $this->tpid[3], 'tpid_b' => (string) $this->tpid[3]], 'Bitte zwei verschiedene Spieler wählen.'],
            [['tpid_b' => (string) $this->tpid[3]], 'Bitte zwei verschiedene Spieler wählen.'],
            [['tpid_a' => ['1'], 'tpid_b' => ['2']], 'Bitte zwei verschiedene Spieler wählen.'],
            [['tpid_a' => (string) $this->tpid[3], 'tpid_b' => (string) $foreign->tpid], 'Mindestens einer der Spieler gehört nicht zu diesem Turnier.'],
            [['tpid_a' => (string) $foreign->tpid], 'Mindestens einer der Spieler gehört nicht zu diesem Turnier.'],
            [['tpid_a' => '99999999999'], 'Bitte zwei verschiedene Spieler wählen.'],
        ] as [$input, $message]) {
            $this->actingAs($admin)->post(route('tournament.admin.partner'), $input)->assertSessionHas('error', $message);
        }

        $this->assertSame(0, TournamentPlayer::query()->whereNotNull('partner_tpid')->count());
    }

    #[Test]
    public function comment_is_limited_to_160(): void
    {
        $this->championship();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('tournament.admin.comment', ['tpid' => $this->tpid[2]]), ['note' => '  '.str_repeat('ö', 200).'  '])
            ->assertRedirect(route('tournament.players'))
            ->assertSessionHas('success', 'Kommentar gespeichert.');
        $this->assertSame(str_repeat('ö', 160), TournamentPlayer::query()->whereKey($this->tpid[2])->value('note'));

        // An array where the text belongs is refused and keeps the comment.
        $this->actingAs($admin)->post(route('tournament.admin.comment', ['tpid' => $this->tpid[2]]), ['note' => ['x']])
            ->assertRedirect(route('tournament.players'))
            ->assertSessionHas('error', 'Der Kommentar ist ungültig und wurde nicht gespeichert.');
        $this->assertSame(str_repeat('ö', 160), TournamentPlayer::query()->whereKey($this->tpid[2])->value('note'));

        $this->actingAs($admin)->post(route('tournament.admin.comment', ['tpid' => $this->tpid[2]]), ['note' => 'bis 02.08. auf Urlaub']);
        $this->actingAs($admin)->post(route('tournament.admin.comment', ['tpid' => $this->tpid[3]]), ['note' => '   ']);
        $this->assertSame('bis 02.08. auf Urlaub', TournamentPlayer::query()->whereKey($this->tpid[2])->value('note'));
        $this->assertNull(TournamentPlayer::query()->whereKey($this->tpid[3])->value('note'));
    }

    #[Test]
    public function comment_resolves_the_player_through_the_selected_tournament(): void
    {
        $this->championship();
        $admin = $this->admin();
        $foreign = TournamentPlayer::factory()->create(['tid' => Tournament::factory()->create()->tid, 'note' => 'unberührt']);

        $this->actingAs($admin)->post(route('tournament.admin.comment', ['tpid' => $foreign->tpid]), ['note' => 'x'])->assertNotFound();
        $this->actingAs($admin)->post('/tournament/admin/comment/999999', ['note' => 'x'])->assertNotFound();
        $this->actingAs($admin)->post('/tournament/admin/comment/99999999999', ['note' => 'x'])->assertNotFound();
        $this->actingAs($admin)->post('/tournament/admin/comment/x', ['note' => 'x'])->assertNotFound();
        $this->assertSame('unberührt', $foreign->fresh()->note);

        // Members are refused before the id is looked at (403, not 404).
        $this->actingAs($this->member())->post('/tournament/admin/comment/999999', ['note' => 'x'])->assertForbidden();

        $this->actingAs($admin)->get('/tournament/admin/comment/'.$this->tpid[1])->assertRedirect(route('tournament.players'));
    }

    #[Test]
    public function the_players_page_offers_the_comment_form_to_admins_only(): void
    {
        $this->championship();
        TournamentPlayer::query()->whereKey($this->tpid[1])->update(['note' => 'Notiz der Leitung']);

        $this->actingAs($this->admin())->get(route('tournament.players'))
            ->assertOk()
            ->assertSee('action="'.route('tournament.admin.comment', ['tpid' => $this->tpid[1]]).'"', false)
            ->assertSee('Kommentar der Turnierleitung')
            ->assertSee('>Notiz der Leitung</textarea>', false);

        $this->actingAs($this->member())->get(route('tournament.players'))
            ->assertOk()
            ->assertDontSee('Kommentar der Turnierleitung')
            ->assertSee('Notiz der Leitung');
    }

    #[Test]
    public function lot_scope_must_be_known(): void
    {
        $this->championship();
        $admin = $this->admin();
        $order = $this->tpid[1].', '.$this->tpid[4];

        foreach (['group:Z', 'group:a', 'phase:G1:place:3', 'phase:KO:place:1', 'phase:XX:place:2', '', 'group:A;DROP'] as $scope) {
            $this->actingAs($admin)->post(route('tournament.admin.lot'), ['scope' => $scope, 'order' => $order])
                ->assertSessionHas('error', 'Ungültiger Loskontext.');
        }
        $this->actingAs($admin)->post(route('tournament.admin.lot'), ['scope' => ['group:A'], 'order' => $order])
            ->assertSessionHas('error', 'Ungültiger Loskontext.');
        $this->assertNull($this->tournament->fresh()->lot_orders);

        // Known scopes pass the scope check and reach the tie check: these two players are not the open tie.
        foreach (['group:A', ' group:B ', 'phase:G1:place:1', 'phase:G1:place:2'] as $scope) {
            $this->actingAs($admin)->post(route('tournament.admin.lot'), ['scope' => $scope, 'order' => $order])
                ->assertSessionMissing('success')
                ->assertSessionHas('error', fn (string $m) => $m !== 'Ungültiger Loskontext.');
        }
        $this->assertNull($this->tournament->fresh()->lot_orders);
    }

    #[Test]
    public function lot_needs_two_unique_members_of_the_tournament(): void
    {
        $this->championship();
        $admin = $this->admin();
        $foreign = TournamentPlayer::factory()->create(['tid' => Tournament::factory()->create()->tid]);

        foreach ([(string) $this->tpid[1], $this->tpid[1].','.$this->tpid[1], '', 'abc def', '0, 0', ['1', '2']] as $order) {
            $this->actingAs($admin)->post(route('tournament.admin.lot'), ['scope' => 'group:A', 'order' => $order])
                ->assertSessionHas('error', 'Mindestens zwei eindeutige Teilnehmernummern angeben.');
        }

        $this->actingAs($admin)->post(route('tournament.admin.lot'), ['scope' => 'group:A', 'order' => $this->tpid[1].' '.$foreign->tpid])
            ->assertSessionHas('error', 'Teilnehmer '.$foreign->tpid.' gehört nicht zum Turnier.');
        $this->actingAs($admin)->post(route('tournament.admin.lot'), ['scope' => 'group:A', 'order' => $this->tpid[1].';999999'])
            ->assertSessionHas('error', 'Teilnehmer 999999 gehört nicht zum Turnier.');

        $this->assertNull($this->tournament->fresh()->lot_orders);
    }

    #[Test]
    public function stored_lot_order_is_zf2_json_and_wins_over_derived(): void
    {
        $this->championship();
        // Group A: seeds 1 and 4 both beat seed 5 by the same score and have not met: a full tie.
        $this->beat(1, 5);
        $this->beat(4, 5);
        Draw::factory()->executed()->create(['tid' => $this->tournament->tid]);
        $this->app->forgetScopedInstances();

        $m = $this->app->make(TournamentManager::class);
        $derived = $m->derivedLotOrder((int) $this->tournament->tid, 'group:A', $this->tableWithoutLot());
        $this->assertCount(2, $derived);
        $manual = array_reverse($derived);
        $this->app->forgetScopedInstances();

        // A manual order of another context stays when this one is added.
        $this->tournament->update(['lot_orders' => json_encode(['group:B' => [$this->tpid[2], $this->tpid[3], $this->tpid[6]]])]);
        $this->app->forgetScopedInstances();

        $this->actingAs($this->admin())->post(route('tournament.admin.lot'), ['scope' => 'group:A', 'order' => implode(', ', $manual)])
            ->assertSessionHas('success', 'Losentscheid gespeichert: group:A');

        $this->assertSame(
            json_encode(['group:B' => [$this->tpid[2], $this->tpid[3], $this->tpid[6]], 'group:A' => $manual]),
            $this->tournament->fresh()->lot_orders,
        );

        $this->app->forgetScopedInstances();
        $html = (string) $this->actingAs($this->member())->get(route('tournament.groups', 'A'))->assertOk()->getContent();
        $first = $manual[0] === $this->tpid[1] ? 'Demo Spieler 01' : 'Demo Spieler 04';
        $second = $first === 'Demo Spieler 01' ? 'Demo Spieler 04' : 'Demo Spieler 01';
        $this->assertLessThan(strpos($html, $second), strpos($html, $first));
    }

    #[Test]
    public function get_on_partner_and_lot_redirects(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/tournament/admin/partner')->assertRedirect(route('tournament.admin'));
        $this->actingAs($admin)->get('/tournament/admin/lot')->assertRedirect(route('tournament.admin'));
    }

    /** @return list<array<string, mixed>> group A ranked without any lot resolution */
    private function tableWithoutLot(): array
    {
        $m = $this->app->make(TournamentManager::class);
        $tid = (int) $this->tournament->tid;
        $players = array_map(fn (array $p) => ['tpid' => (int) $p['tpid'], 'label' => $p['label'], 'seed' => (int) $p['seed'], 'itn_rated' => $p['itn_rated'], 'points_adjust' => 0], $m->groupPlayers($tid, 'A'));
        $matches = array_values(array_filter($m->groupMatches($tid, 'A'), fn (array $x) => (bool) $x['winner']));

        return $m->standings($tid)->rank($players, $matches);
    }

    private function beat(int $winnerSeed, int $loserSeed): void
    {
        $a = $this->tpid[$winnerSeed];
        $b = $this->tpid[$loserSeed];
        $match = TournamentMatch::query()->where('tid', $this->tournament->tid)
            ->where(fn ($q) => $q->where(['tpid_a' => $a, 'tpid_b' => $b])->orWhere(fn ($q) => $q->where(['tpid_a' => $b, 'tpid_b' => $a])))
            ->firstOrFail();
        $aWins = (int) $match->tpid_a === $a;

        $match->update([
            'played' => '2027-05-03', 'played_time' => '17:00:00',
            'score' => $aWins ? '6:4 6:3' : '4:6 3:6',
            's1a' => $aWins ? 6 : 4, 's1b' => $aWins ? 4 : 6, 's2a' => $aWins ? 6 : 3, 's2b' => $aWins ? 3 : 6,
            'sets_a' => $aWins ? 2 : 0, 'sets_b' => $aWins ? 0 : 2, 'games_a' => $aWins ? 12 : 7, 'games_b' => $aWins ? 7 : 12,
            'winner' => $a,
        ]);
        $this->app->forgetScopedInstances();
    }
}
