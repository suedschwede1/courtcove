<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Mail\PyramidNotificationMail;
use App\Modules\Pyramid\Models\PyramidEvent;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminPlayerActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->status('admin')->create();
    }

    private function ladderUrl(PyramidLadder $ladder, string $action = ''): string
    {
        return '/pyramide/admin/ladder/'.$ladder->id.($action === '' ? '' : '/'.$action);
    }

    private function playerUrl(PyramidPlayer $player, string $action): string
    {
        return '/pyramide/admin/player/'.$player->id.'/'.$action;
    }

    private function waiting(PyramidLadder $ladder, array $attributes = []): PyramidPlayer
    {
        return PyramidPlayer::factory()->waiting()->create($attributes + ['ladder_id' => $ladder->id, 'label' => 'Demo Spieler 90']);
    }

    #[Test]
    public function seed_flow(): void
    {
        $ladder = PyramidLadder::factory()->setup()->create();
        $this->waiting($ladder, ['label' => 'Spieler 1', 'itn' => '8.0']);
        $this->waiting($ladder, ['label' => 'Spieler 2', 'itn' => '6.0']);

        $this->actingAs($this->admin())->post($this->ladderUrl($ladder, 'seed'))
            ->assertRedirect($this->ladderUrl($ladder))
            ->assertSessionHas('success', '2 Teilnehmer nach ITN gereiht, die Pyramide läuft.');

        $this->assertSame('running', $ladder->fresh()->status);
        $this->assertSame(1, (int) PyramidPlayer::query()->where('label', 'Spieler 2')->value('position'));

        $this->actingAs($this->admin())->post($this->ladderUrl($ladder, 'seed'))
            ->assertSessionHas('error', 'Diese Pyramide hat schon eine Rangliste. Einzelne Spieler bitte über die Warteliste aufnehmen.');
    }

    #[Test]
    public function seed_without_players_flashes_the_error(): void
    {
        $ladder = PyramidLadder::factory()->setup()->create();

        $this->actingAs($this->admin())->post($this->ladderUrl($ladder, 'seed'))
            ->assertRedirect($this->ladderUrl($ladder))
            ->assertSessionHas('error', 'Es gibt noch keine Teilnehmer.');
    }

    #[Test]
    public function add_player_with_email_match(): void
    {
        $ladder = PyramidLadder::factory()->create();
        $member = User::factory()->create(['alias' => 'Demo Spieler 07', 'email' => 'demo07@example.test']);

        $this->actingAs($this->admin())->post($this->ladderUrl($ladder, 'add-player'), [
            'label' => 'Falscher Name', 'uid' => '0', 'email' => 'demo07@example.test', 'itn' => '7,5',
        ])->assertRedirect($this->ladderUrl($ladder))
            ->assertSessionHas('success', 'Demo Spieler 07 zur Warteliste hinzugefügt.');

        $player = PyramidPlayer::query()->where('ladder_id', $ladder->id)->firstOrFail();
        $this->assertSame((int) $member->uid, (int) $player->uid);
        $this->assertSame('Demo Spieler 07', $player->label);
        $this->assertSame('waiting', $player->status);

        $this->actingAs($this->admin())->post($this->ladderUrl($ladder, 'add-player'), ['label' => 'x', 'email' => 'demo07@example.test'])
            ->assertSessionHas('error', 'Dieses Mitglied ist bereits Teilnehmer dieser Rangliste.');
        $this->actingAs($this->admin())->post($this->ladderUrl($ladder, 'add-player'), ['label' => '  '])
            ->assertSessionHas('error', 'Bitte einen Namen angeben.');
        $this->assertSame(1, PyramidPlayer::query()->where('ladder_id', $ladder->id)->count());
    }

    #[Test]
    public function add_player_strips_4_byte_characters(): void
    {
        $ladder = PyramidLadder::factory()->create();

        $this->actingAs($this->admin())->post($this->ladderUrl($ladder, 'add-player'), ['label' => 'Demo 🎾'])
            ->assertSessionHas('success');

        // The emoji is gone before the service sees the label; the service then trims the remaining space like ZF2.
        $this->assertSame('Demo', PyramidPlayer::query()->where('ladder_id', $ladder->id)->value('label'));
    }

    #[Test]
    public function admit_mails_the_player(): void
    {
        Mail::fake();
        $ladder = PyramidLadder::factory()->withRanking(3)->create();
        $user = User::factory()->create(['email' => 'neu@example.test']);
        $player = $this->waiting($ladder, ['uid' => $user->uid]);

        $this->actingAs($this->admin())->post($this->playerUrl($player, 'admit'), ['itn' => '9,5'])
            ->assertRedirect($this->ladderUrl($ladder))
            ->assertSessionHas('success', 'Demo Spieler 90 steigt auf Platz 4 ein.');

        $fresh = $player->fresh();
        $this->assertSame('active', $fresh->status);
        $this->assertSame(4, (int) $fresh->position);
        $this->assertEquals(9.5, (float) $fresh->itn);
        Mail::assertSent(PyramidNotificationMail::class, 1);

        $this->actingAs($this->admin())->post($this->playerUrl($player, 'admit'))
            ->assertSessionHas('error', 'Dieser Spieler steht nicht auf der Warteliste.');
        Mail::assertSent(PyramidNotificationMail::class, 1);
    }

    #[Test]
    public function release_mails_the_player(): void
    {
        Mail::fake();
        $ladder = PyramidLadder::factory()->withRanking(3)->create();
        $user = User::factory()->create(['email' => 'neu@example.test']);
        $player = $this->waiting($ladder, ['uid' => $user->uid]);

        $this->actingAs($this->admin())->post($this->playerUrl($player, 'release-entry'), ['release' => '1'])
            ->assertRedirect($this->ladderUrl($ladder))
            ->assertSessionHas('success', 'Demo Spieler 90 darf jetzt einen aktiven Spieler einfordern.');

        $this->assertSame('entering', $player->fresh()->status);
        Mail::assertSent(PyramidNotificationMail::class, 1);
        $this->assertSame(
            '{"message":"Demo Spieler 90 ist zum Einfordern freigegeben."}',
            PyramidEvent::query()->where('type', 'admin')->firstOrFail()->payload,
        );

        $this->actingAs($this->admin())->post($this->playerUrl($player, 'release-entry'), ['release' => '0'])
            ->assertSessionHas('success', 'Freigabe zurückgenommen.');
        $this->assertSame('waiting', $player->fresh()->status);
        Mail::assertSent(PyramidNotificationMail::class, 1);
    }

    #[Test]
    public function remove_normalises(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(4)->create();
        $second = PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 2)->firstOrFail();

        $this->actingAs($this->admin())->post($this->playerUrl($second, 'status'), ['status' => 'removed'])
            ->assertRedirect($this->ladderUrl($ladder))
            ->assertSessionHas('success', 'Status von '.$second->label.' geändert.');

        $this->assertNull($second->fresh()->position);
        $this->assertSame([1, 2, 3], PyramidPlayer::query()->where('ladder_id', $ladder->id)->whereNotNull('position')->orderBy('position')->pluck('position')->map(fn ($p) => (int) $p)->all());
    }

    #[Test]
    public function unknown_status_is_a_422(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(2)->create();
        $player = PyramidPlayer::query()->where('ladder_id', $ladder->id)->firstOrFail();

        $this->actingAs($this->admin())->post($this->playerUrl($player, 'status'), ['status' => 'banned'])
            ->assertStatus(422);
        $this->assertSame('active', $player->fresh()->status);
    }

    #[Test]
    public function update_moves_the_player(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(4)->create();
        $fourth = PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 4)->firstOrFail();

        $this->actingAs($this->admin())->post($this->playerUrl($fourth, 'update'), [
            'label' => 'Demo Spieler 44', 'itn' => '8,5', 'gender' => 'f', 'age' => '33', 'phone' => '+43 000 000 0044', 'note' => 'Notiz', 'position' => '1',
        ])->assertRedirect($this->ladderUrl($ladder))
            ->assertSessionHas('success', 'Demo Spieler 44 aktualisiert.');

        $fresh = $fourth->fresh();
        $this->assertSame(1, (int) $fresh->position);
        $this->assertSame('Demo Spieler 44', $fresh->label);
        $this->assertSame('f', $fresh->gender);
        $this->assertSame(33, (int) $fresh->age);
        $this->assertSame('Notiz', $fresh->note);

        $this->actingAs($this->admin())->post($this->playerUrl($fourth, 'update'), ['label' => ' '])
            ->assertSessionHas('error', 'Bitte einen Namen angeben.');
    }

    #[Test]
    public function copy_reports_the_count(): void
    {
        $source = PyramidLadder::factory()->withRanking(3)->create(['title' => 'Quelle 2026']);
        $target = PyramidLadder::factory()->setup()->create();

        $this->actingAs($this->admin())->post($this->ladderUrl($target, 'copy-players'), ['sourceLadderId' => $source->id])
            ->assertRedirect($this->ladderUrl($target))
            ->assertSessionHas('success', '3 Teilnehmer aus "Quelle 2026" auf die Warteliste übernommen.');
        $this->assertSame(3, PyramidPlayer::query()->where('ladder_id', $target->id)->where('status', 'waiting')->count());

        $this->actingAs($this->admin())->post($this->ladderUrl($target, 'copy-players'), ['sourceLadderId' => $target->id])
            ->assertSessionHas('error', 'Bitte eine andere Rangliste als Quelle wählen.');
        $this->actingAs($this->admin())->post($this->ladderUrl($target, 'copy-players'), ['sourceLadderId' => 9999])
            ->assertSessionHas('error', 'Bitte eine andere Rangliste als Quelle wählen.');
    }

    #[Test]
    public function normalize_renumbers(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(3)->create();
        PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 3)->update(['position' => 7]);

        $this->actingAs($this->admin())->post($this->ladderUrl($ladder, 'normalize'))
            ->assertRedirect($this->ladderUrl($ladder))
            ->assertSessionHas('success', 'Rangliste neu durchnummeriert.');

        $this->assertSame(3, (int) PyramidPlayer::query()->where('ladder_id', $ladder->id)->max('position'));
    }

    #[Test]
    public function actions_on_another_ladder_are_forbidden_for_grant_holders(): void
    {
        Mail::fake();
        $granted = PyramidLadder::factory()->withRanking(3)->create();
        $other = PyramidLadder::factory()->withRanking(3)->create();
        $holder = User::factory()->create();
        PyramidGrant::query()->create(['ladder_id' => $granted->id, 'uid' => $holder->uid, 'role' => 'admin', 'created' => '2027-01-01 00:00:00']);

        $foreign = PyramidPlayer::query()->where('ladder_id', $other->id)->where('position', 1)->firstOrFail();
        $foreignWaiting = $this->waiting($other);

        $this->actingAs($holder)->post($this->ladderUrl($other, 'normalize'))->assertForbidden();
        $this->actingAs($holder)->post($this->ladderUrl($other, 'seed'))->assertForbidden();
        $this->actingAs($holder)->post($this->ladderUrl($other, 'add-player'), ['label' => 'Eindringling'])->assertForbidden();
        $this->actingAs($holder)->post($this->ladderUrl($other, 'copy-players'), ['sourceLadderId' => $granted->id])->assertForbidden();
        $this->actingAs($holder)->post($this->playerUrl($foreign, 'status'), ['status' => 'removed'])->assertForbidden();
        $this->actingAs($holder)->post($this->playerUrl($foreign, 'update'), ['label' => 'Gekapert'])->assertForbidden();
        $this->actingAs($holder)->post($this->playerUrl($foreignWaiting, 'admit'))->assertForbidden();
        $this->actingAs($holder)->post($this->playerUrl($foreignWaiting, 'release-entry'), ['release' => '1'])->assertForbidden();

        $this->assertSame('active', $foreign->fresh()->status);
        $this->assertSame('waiting', $foreignWaiting->fresh()->status);
        $this->assertSame(0, PyramidPlayer::query()->where('label', 'Eindringling')->count());
        Mail::assertNothingSent();

        // Reading from a ladder the holder does not administer would leak its roster.
        $this->actingAs($holder)->post($this->ladderUrl($granted, 'copy-players'), ['sourceLadderId' => $other->id])
            ->assertSessionHas('error', 'Bitte eine andere Rangliste als Quelle wählen.');
        $this->assertSame(0, PyramidPlayer::query()->where('ladder_id', $granted->id)->where('status', 'waiting')->count());

        // On the granted ladder everything works.
        $own = $this->waiting($granted);
        $this->actingAs($holder)->post($this->playerUrl($own, 'admit'))->assertSessionHas('success');
    }

    #[Test]
    public function unknown_ids_are_404_and_get_is_not_allowed(): void
    {
        $ladder = PyramidLadder::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/pyramide/admin/ladder/9999/seed')->assertNotFound();
        $this->actingAs($admin)->post('/pyramide/admin/player/9999/status', ['status' => 'paused'])->assertNotFound();
        $this->actingAs($admin)->get($this->ladderUrl($ladder, 'seed'))->assertStatus(405);
    }

    #[Test]
    public function plain_member_is_turned_away(): void
    {
        $ladder = PyramidLadder::factory()->create();

        $this->actingAs(User::factory()->create())->post($this->ladderUrl($ladder, 'normalize'))->assertForbidden();
    }

    #[Test]
    public function guest_is_sent_to_the_login(): void
    {
        $ladder = PyramidLadder::factory()->create();

        $this->post($this->ladderUrl($ladder, 'normalize'))->assertRedirect();
    }
}
