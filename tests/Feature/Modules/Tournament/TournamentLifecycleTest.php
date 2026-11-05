<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\TournamentBase\Support\TestModeSession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

class TournamentLifecycleTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    private function selectedTid(): ?int
    {
        $state = session(TestModeSession::KEY);

        return is_array($state) && isset($state['tid']) ? (int) $state['tid'] : null;
    }

    #[Test]
    public function create_copies_format_and_optionally_players(): void
    {
        $this->championship([], ['points_per_win' => 3, 'tiebreak_scope' => 'all', 'runnerup_mode' => 'points', 'itn_default' => 9.5]);
        TournamentPlayer::query()->whereKey($this->tpid[3])->update(['partner_tpid' => $this->tpid[4], 'note' => 'bis 02.08. auf Urlaub', 'phone' => '+43 660 0000000']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('tournament.admin.create'), [
            'title' => '  Demo-Meisterschaft 2028 ', 'season' => '2028', 'source_tid' => (string) $this->tournament->tid, 'with_players' => '1',
        ])->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('success', 'Turnier "Demo-Meisterschaft 2028" (2028) angelegt, Format von "Demo-Meisterschaft" übernommen, 6 Teilnehmer kopiert. Es ist noch NICHT aktiv - erst prüfen, dann aktiv setzen.');

        $new = Tournament::query()->where('season', 2028)->firstOrFail();
        $this->assertSame('Demo-Meisterschaft 2028', $new->title);
        $this->assertSame('setup', $new->status);
        $this->assertSame(0, (int) $new->active);
        $this->assertSame(0, (int) $new->is_test);
        $this->assertSame($this->tournament->format, $new->format);
        $this->assertSame(3, (int) $new->points_per_win);
        $this->assertSame('all', $new->tiebreak_scope);
        $this->assertSame('points', $new->runnerup_mode);
        $this->assertSame(9.5, (float) $new->itn_default);
        $this->assertNull($new->lot_orders);
        $this->assertSame((int) $new->tid, $this->selectedTid());
        $this->assertSame(1, (int) $this->tournament->fresh()->active);

        $copies = TournamentPlayer::query()->where('tid', $new->tid)->orderBy('seed')->get();
        $this->assertCount(6, $copies);
        foreach ($copies as $copy) {
            $this->assertNull($copy->grp);
            $this->assertNull($copy->grp_pos);
            $this->assertNull($copy->partner_tpid);
            $this->assertNull($copy->note);
        }
        $this->assertSame('Demo Spieler 03', $copies[2]->label);
        $this->assertSame('+43 660 0000000', $copies[2]->phone);
        $this->assertSame(0, TournamentMatch::query()->where('tid', $new->tid)->count());

        // Without the box, no participants are copied.
        $this->actingAs($admin)->post(route('tournament.admin.create'), [
            'title' => 'Demo-Meisterschaft 2029', 'season' => '2029', 'source_tid' => (string) $this->tournament->tid,
        ])->assertSessionHas('success', 'Turnier "Demo-Meisterschaft 2029" (2029) angelegt, Format von "Demo-Meisterschaft" übernommen. Es ist noch NICHT aktiv - erst prüfen, dann aktiv setzen.');
        $this->assertSame(0, TournamentPlayer::query()->where('tid', Tournament::query()->where('season', 2029)->value('tid'))->count());
    }

    #[Test]
    public function title_is_required(): void
    {
        $this->championship();
        $admin = $this->admin();
        $count = Tournament::query()->count();

        $this->actingAs($admin)->post(route('tournament.admin.create'), ['title' => '   ', 'season' => '2028', 'source_tid' => (string) $this->tournament->tid])
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('error', 'Bitte einen Titel angeben.');
        $this->actingAs($admin)->post(route('tournament.admin.create'), ['title' => ['x'], 'season' => '2028', 'source_tid' => (string) $this->tournament->tid])
            ->assertSessionHas('error', 'Bitte einen Titel angeben.');
        $this->actingAs($admin)->post(route('tournament.admin.create'), ['title' => 'X', 'season' => '1999', 'source_tid' => (string) $this->tournament->tid])
            ->assertSessionHas('error', 'Bitte eine Saison zwischen 2000 und 2100 angeben.');
        $this->actingAs($admin)->post(route('tournament.admin.create'), ['title' => 'X', 'season' => ['2028'], 'source_tid' => (string) $this->tournament->tid])
            ->assertSessionHas('error', 'Bitte eine Saison zwischen 2000 und 2100 angeben.');
        $this->actingAs($admin)->post(route('tournament.admin.create'), ['title' => 'X', 'season' => '2028', 'source_tid' => '999999'])
            ->assertSessionHas('error', 'Das Vorlagenturnier gibt es nicht.');
        $this->actingAs($admin)->post(route('tournament.admin.create'), ['title' => 'X', 'season' => '2028', 'source_tid' => ['1']])
            ->assertSessionHas('error', 'Ohne Vorlage fehlt das Format. Bitte ein bestehendes Turnier als Vorlage wählen.');
        $this->actingAs($admin)->post(route('tournament.admin.create'), ['title' => 'X', 'season' => '2028', 'source_tid' => '99999999999999999999'])
            ->assertSessionHas('error', 'Ohne Vorlage fehlt das Format. Bitte ein bestehendes Turnier als Vorlage wählen.');

        $this->assertSame($count, Tournament::query()->count());

        // An overlong title is cut to the column length, as in ZF2.
        $this->actingAs($admin)->post(route('tournament.admin.create'), ['title' => str_repeat('ä', 200), 'season' => '2030', 'source_tid' => (string) $this->tournament->tid])
            ->assertSessionHas('success');
        $this->assertSame(str_repeat('ä', 160), Tournament::query()->where('season', 2030)->value('title'));
    }

    #[Test]
    public function activate_sets_exactly_one_active(): void
    {
        $this->championship();
        $other = Tournament::factory()->create(['title' => 'Demo-Meisterschaft 2028', 'season' => 2028]);
        Tournament::factory()->active()->create(['title' => 'Altlast aktiv']);

        $this->actingAs($this->admin())->post(route('tournament.admin.activate'), ['tid' => (string) $other->tid])
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('success', '"Demo-Meisterschaft 2028" ist jetzt aktiv - das sehen die Mitglieder.');

        $this->assertSame([(int) $other->tid], Tournament::query()->where('active', 1)->pluck('tid')->map(fn ($t) => (int) $t)->all());

        // The members' current tournament follows.
        $this->app->forgetScopedInstances();
        $this->actingAs($this->member())->get(route('tournament.index'))->assertOk()->assertSee('Demo-Meisterschaft 2028');
    }

    #[Test]
    public function unknown_tournament_cannot_be_activated(): void
    {
        $this->championship();
        $admin = $this->admin();

        foreach (['999999', '', 'abc', ['1'], '-1'] as $tid) {
            $this->actingAs($admin)->post(route('tournament.admin.activate'), ['tid' => $tid])
                ->assertSessionHas('error', 'Turnier nicht gefunden.');
        }

        $this->assertSame(1, (int) $this->tournament->fresh()->active);
    }

    #[Test]
    public function test_tournament_cannot_be_activated(): void
    {
        $this->championship();
        $test = Tournament::factory()->test()->create();

        $this->actingAs($this->admin())->post(route('tournament.admin.activate'), ['tid' => (string) $test->tid])
            ->assertSessionHas('error', 'Ein Testturnier kann nicht aktiv gesetzt werden - seine Ergebnisse sind erfunden.');

        $this->assertSame(0, (int) $test->fresh()->active);
        $this->assertSame(1, (int) $this->tournament->fresh()->active);
    }

    #[Test]
    public function clone_marks_test_and_remaps_partners(): void
    {
        $this->championship();
        TournamentPlayer::query()->whereKey($this->tpid[3])->update(['partner_tpid' => $this->tpid[6], 'note' => 'Notiz 03']);
        TournamentPlayer::query()->whereKey($this->tpid[6])->update(['partner_tpid' => $this->tpid[3]]);
        TournamentPlayer::query()->whereKey($this->tpid[5])->update(['partner_tpid' => 999999]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('tournament.admin.clone-test'))
            ->assertRedirect(route('tournament.admin'));

        $copy = Tournament::query()->where('is_test', 1)->firstOrFail();
        $this->assertSame('TEST Demo-Meisterschaft', $copy->title);
        $this->assertSame((int) $this->tournament->tid, (int) $copy->cloned_from);
        $this->assertSame('setup', $copy->status);
        $this->assertSame(0, (int) $copy->active);
        $this->assertSame($this->tournament->format, $copy->format);
        $this->assertSame((int) $copy->tid, $this->selectedTid());
        $this->assertSame(0, TournamentMatch::query()->where('tid', $copy->tid)->count());

        $this->actingAs($admin)->get(route('tournament.admin'))
            ->assertSee('Testturnier angelegt (tid '.$copy->tid.') mit 6 Teilnehmern und ist jetzt gewählt. Mitglieder sehen weiterhin das echte Turnier.');

        $bySeed = TournamentPlayer::query()->where('tid', $copy->tid)->get()->keyBy(fn ($p) => (int) $p->seed);
        // Heads (A1 = seed 1, B1 = seed 2) keep their group, everybody else is back in the pot.
        $this->assertSame(['A', 1], [$bySeed[1]->grp, (int) $bySeed[1]->grp_pos]);
        $this->assertSame(['B', 1], [$bySeed[2]->grp, (int) $bySeed[2]->grp_pos]);
        foreach ([3, 4, 5, 6] as $seed) {
            $this->assertNull($bySeed[$seed]->grp);
            $this->assertNull($bySeed[$seed]->grp_pos);
        }
        $this->assertSame((int) $bySeed[6]->tpid, (int) $bySeed[3]->partner_tpid);
        $this->assertSame((int) $bySeed[3]->tpid, (int) $bySeed[6]->partner_tpid);
        $this->assertNull($bySeed[5]->partner_tpid);
        $this->assertSame('Notiz 03', $bySeed[3]->note);

        // A test tournament cannot be copied again.
        $this->actingAs($admin)->post(route('tournament.admin.clone-test'))
            ->assertSessionHas('error', 'Ein Testturnier lässt sich nicht erneut kopieren - wähle zuerst das echte.');
        $this->assertSame(1, Tournament::query()->where('is_test', 1)->count());
    }

    #[Test]
    public function select_switches_and_stops_simulation(): void
    {
        $this->championship();
        $test = Tournament::factory()->test()->create(['title' => 'TEST Demo']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession([TestModeSession::KEY => ['tid' => null, 'mode' => 'member', 'tpid' => null]])
            ->post(route('tournament.admin.select'), ['tid' => (string) $test->tid])
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('success', 'Turnier gewechselt: TEST Demo (Testturnier)');
        $this->assertSame((int) $test->tid, $this->selectedTid());
        $this->assertNull(session(TestModeSession::KEY)['mode']);

        $this->actingAs($admin)->post(route('tournament.admin.select'), ['tid' => (string) $this->tournament->tid])
            ->assertSessionHas('success', 'Turnier gewechselt: Demo-Meisterschaft');

        $this->actingAs($admin)->post(route('tournament.admin.select'), ['tid' => '999999'])
            ->assertSessionHas('error', 'Turnier nicht gefunden.');
        $this->assertSame((int) $this->tournament->tid, $this->selectedTid());

        $this->actingAs($admin)->post(route('tournament.admin.select'), ['tid' => ''])
            ->assertSessionHas('success', 'Zurück auf das aktuelle Turnier.');
        $this->assertNull($this->selectedTid());
    }

    #[Test]
    public function failure_rolls_back(): void
    {
        $this->championship();
        $admin = $this->admin();
        $count = Tournament::query()->count();
        TournamentPlayer::creating(function (): void {
            throw new RuntimeException('Spielerzeile abgelehnt');
        });

        $this->actingAs($admin)->post(route('tournament.admin.create'), [
            'title' => 'Demo-Meisterschaft 2028', 'season' => '2028', 'source_tid' => (string) $this->tournament->tid, 'with_players' => '1',
        ])->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('error', 'Turnier konnte nicht angelegt werden: interner Fehler, Details stehen im Fehlerprotokoll.');

        $this->assertSame($count, Tournament::query()->count());
        $this->assertNull($this->selectedTid());
    }

    #[Test]
    public function clone_failure_leaves_no_copy(): void
    {
        $this->championship();
        $count = Tournament::query()->count();
        TournamentPlayer::creating(function (): void {
            throw new RuntimeException('Spielerzeile abgelehnt');
        });

        $this->actingAs($this->admin())->post(route('tournament.admin.clone-test'))->assertServerError();

        $this->assertSame($count, Tournament::query()->count());
        $this->assertNull($this->selectedTid());
    }

    #[Test]
    public function get_on_the_actions_redirects_to_the_admin_page(): void
    {
        $admin = $this->admin();

        foreach (['create', 'activate', 'clone-test', 'select-tournament'] as $action) {
            $this->actingAs($admin)->get('/tournament/admin/'.$action)->assertRedirect(route('tournament.admin'));
        }
    }

    #[Test]
    public function a_real_login_session_can_switch_the_tournament(): void
    {
        $this->championship();
        $test = Tournament::factory()->test()->create(['title' => 'TEST Demo']);
        $admin = User::factory()->create(['status' => 'admin', 'email' => 'leitung@example.test', 'pw' => bcrypt('geheim-123')]);

        $this->post('/login', ['email' => 'leitung@example.test', 'password' => 'geheim-123']);
        $this->assertAuthenticatedAs($admin);

        $this->post(route('tournament.admin.select'), ['tid' => (string) $test->tid])->assertRedirect(route('tournament.admin'));
        $this->get(route('tournament.admin'))->assertOk()->assertSee('TEST Demo');
    }
}
